<?php

use App\Enums\GuardianConsentMethod;
use App\Enums\Level;
use App\Enums\VolunteerStatus;
use App\Models\RejectedRegistration;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\RegistrationDecided;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();
});

test('coordinators see registrations awaiting review', function () {
    $pending = Volunteer::factory()->pending()->create();
    Volunteer::factory()->active()->create();

    $this->actingAs($this->coordinator)
        ->get(route('coord.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/registrations/index')
            ->has('registrations', 1)
            ->where('registrations.0.id', $pending->id));
});

test('approving activates the volunteer, sets levels per role and notifies them', function () {
    $volunteer = Volunteer::factory()->pending()->withSkill($this->audio, Level::Beginner)->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.approve', $volunteer), [
            'levels' => [$this->audio->id => Level::Advanced->value],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $volunteer->refresh();

    expect($volunteer->status)->toBe(VolunteerStatus::Active)
        ->and($volunteer->reviewed_by)->toBe($this->coordinator->id)
        ->and($volunteer->skills()->first()?->level)->toBe(Level::Advanced);

    Notification::assertSentTo($volunteer->user, RegistrationDecided::class);
    expect(Activity::query()->where('subject_type', Volunteer::class)->where('subject_id', $volunteer->id)->where('event', 'updated')->exists())->toBeTrue();
});

test('a minor can not be approved without recording guardian consent', function () {
    $volunteer = Volunteer::factory()->minor()->pending()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.approve', $volunteer))
        ->assertSessionHasErrors('guardian_consent_method');

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::Pending);

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.approve', $volunteer), [
            'guardian_consent_method' => GuardianConsentMethod::Phone->value,
        ])
        ->assertSessionHasNoErrors();

    $volunteer->refresh();

    expect($volunteer->status)->toBe(VolunteerStatus::Active)
        ->and($volunteer->guardian_consent_confirmed_by)->toBe($this->coordinator->id)
        ->and($volunteer->guardian_consent_method)->toBe(GuardianConsentMethod::Phone)
        ->and($volunteer->guardian_consent_confirmed_at)->not->toBeNull();
});

test('requesting a correction requires notes and keeps the registration under review', function () {
    $volunteer = Volunteer::factory()->pending()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.correction', $volunteer), ['notes' => ''])
        ->assertSessionHasErrors('notes');

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.correction', $volunteer), ['notes' => 'Falta a disponibilidade de terça.'])
        ->assertSessionHasNoErrors();

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::NeedsCorrection)
        ->and($volunteer->review_notes)->toBe('Falta a disponibilidade de terça.');

    Notification::assertSentTo($volunteer->user, RegistrationDecided::class);
});

test('the volunteer can resubmit after a correction request', function () {
    $volunteer = Volunteer::factory()->status(VolunteerStatus::NeedsCorrection)->create();

    $this->actingAs($volunteer->user)
        ->post(route('volunteer.resubmit'))
        ->assertRedirect(route('volunteer.status'));

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::Pending);
});

test('rejecting records the phone hash and can be reversed within 30 days', function () {
    $volunteer = Volunteer::factory()->pending()->create();
    $phone = $volunteer->user?->phone;

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.reject', $volunteer), ['notes' => ''])
        ->assertSessionHasNoErrors();

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::Rejected)
        ->and(RejectedRegistration::lastRejectionFor((string) $phone))->not->toBeNull();

    $this->travel(29)->days();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.reverse', $volunteer))
        ->assertSessionHasNoErrors();

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::Pending)
        ->and(RejectedRegistration::lastRejectionFor((string) $phone))->toBeNull();
});

test('a rejection can not be reversed after 30 days', function () {
    $volunteer = Volunteer::factory()->pending()->create();

    $this->actingAs($this->coordinator)->post(route('coord.registrations.reject', $volunteer));

    $this->travel(31)->days();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.reverse', $volunteer))
        ->assertSessionHasErrors('volunteer');

    expect($volunteer->refresh()->status)->toBe(VolunteerStatus::Rejected);
});

test('only registrations under review can be approved or rejected', function () {
    $volunteer = Volunteer::factory()->active()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.registrations.reject', $volunteer))
        ->assertSessionHasErrors('volunteer');
});

test('pending, correction and rejected volunteers only see their status page', function (VolunteerStatus $status) {
    $volunteer = Volunteer::factory()->status($status)->create();

    $this->actingAs($volunteer->user)
        ->get(route('home'))
        ->assertRedirect(route('volunteer.status'));

    $this->actingAs($volunteer->user)
        ->get(route('volunteer.status'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/status')
            ->where('status', $status->value));
})->with([VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection, VolunteerStatus::Rejected]);

test('rejected volunteers see the reason only if the coordinator wrote one', function () {
    $volunteer = Volunteer::factory()->pending()->create();

    $this->actingAs($this->coordinator)->post(route('coord.registrations.reject', $volunteer), ['notes' => '']);

    $this->actingAs($volunteer->user)
        ->get(route('volunteer.status'))
        ->assertInertia(fn ($page) => $page->where('status', 'rejected')->where('notes', null));
});
