<?php

use App\Enums\AssignmentStatus;
use App\Enums\UserRole;
use App\Enums\VolunteerStatus;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\ReplacementNeeded;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->volunteer = $this->scenario->volunteer(attributes: ['full_name' => 'Ana Machava']);

    $this->assign = fn () => $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $this->volunteer->id,
    ]);
});

test('a deactivated volunteer can not be picked, automatically or by hand', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.volunteers.deactivate', $this->volunteer))
        ->assertSessionHasNoErrors();

    expect($this->volunteer->refresh()->status)->toBe(VolunteerStatus::Deactivated)
        ->and($this->volunteer->paused_by)->toBe($this->coordinator->id);

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
    ($this->assign)()->assertSessionHasErrors('volunteer_id');

    expect(Assignment::query()->count())->toBe(0);
});

test('deactivating frees draft places', function () {
    ($this->assign)()->assertSessionHasNoErrors();

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer));

    expect(Assignment::query()->count())->toBe(0);
});

test('deactivating marks published shifts for replacement and alerts coordinators', function () {
    ($this->assign)();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer));

    expect(Assignment::query()->firstOrFail()->status)->toBe(AssignmentStatus::NeedsReplacement);
    Notification::assertSentTo($this->coordinator, ReplacementNeeded::class);
});

test('paused volunteers can be deactivated and a past return date does not bring them back', function () {
    $this->actingAs($this->coordinator)->post(route('coord.volunteers.pause', $this->volunteer), [
        'until' => now()->addDays(3)->toDateString(),
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer))->assertSessionHasNoErrors();

    $this->travel(5)->days();
    $this->artisan('volunteers:resume-paused')->assertSuccessful();

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Deactivated);
});

test('deactivated volunteers only see their status page and can not bring themselves back', function () {
    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer));
    $user = $this->volunteer->user->fresh();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('volunteer.status'));
    $this->actingAs($user)->get(route('volunteer.status'))
        ->assertInertia(fn ($page) => $page->component('volunteer/status')->where('status', 'deactivated'));
    $this->actingAs($user)->post(route('profile.resume'))->assertSessionHasErrors('volunteer');
    $this->actingAs($user)->post(route('coord.volunteers.reactivate', $this->volunteer))->assertForbidden();

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Deactivated);
});

test('a deactivated coordinator keeps the management area', function () {
    $this->volunteer->user->forceFill(['role' => UserRole::Coordinator])->save();
    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer));

    $this->actingAs($this->volunteer->user->fresh())->get(route('home'))->assertRedirect(route('coord.dashboard'));
});

test('coordinators find deactivated volunteers apart and reactivate them', function () {
    $ids = fn (array $query = []) => collect($this->actingAs($this->coordinator)
        ->get(route('coord.volunteers.index', $query))
        ->viewData('page')['props']['volunteers'])->pluck('id');

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $this->volunteer));

    expect($ids())->not->toContain($this->volunteer->id)
        ->and($ids(['filtro' => 'desactivados']))->toContain($this->volunteer->id);

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.reactivate', $this->volunteer))->assertSessionHasNoErrors();

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active)
        ->and($this->volunteer->fresh()->inactive_since)->toBeNull();

    ($this->assign)()->assertSessionHasNoErrors();
});

test('deleting erases the person and the login, and keeps past shifts anonymous', function () {
    ($this->assign)();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $userId = $this->volunteer->user_id;

    $this->actingAs($this->coordinator)
        ->delete(route('coord.volunteers.destroy', $this->volunteer), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('coord.volunteers.index'));

    $this->volunteer->refresh();

    expect($this->volunteer->status)->toBe(VolunteerStatus::Anonymized)
        ->and($this->volunteer->full_name)->toBe("Voluntário #{$this->volunteer->id}")
        ->and(User::query()->find($userId))->toBeNull()
        ->and(Assignment::query()->firstOrFail()->status)->toBe(AssignmentStatus::NeedsReplacement);

    ($this->assign)()->assertSessionHasErrors('volunteer_id');
});

test('deleting frees draft places', function () {
    ($this->assign)();

    $this->actingAs($this->coordinator)->delete(route('coord.volunteers.destroy', $this->volunteer), ['password' => 'password']);

    expect(Assignment::query()->count())->toBe(0);
});

test('deleting needs the acting coordinator\'s own password', function () {
    $this->actingAs($this->coordinator)
        ->delete(route('coord.volunteers.destroy', $this->volunteer), ['password' => 'errada'])
        ->assertSessionHasErrors('password');

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active);
});

test('coordinators can not be deleted while they have the role', function () {
    $this->volunteer->user->forceFill(['role' => UserRole::Coordinator])->save();

    $this->actingAs($this->coordinator)
        ->delete(route('coord.volunteers.destroy', $this->volunteer), ['password' => 'password'])
        ->assertSessionHasErrors('volunteer');

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active);
});

test('registrations in review are neither deactivated nor deleted here', function () {
    $pending = Volunteer::factory()->pending()->create();

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.deactivate', $pending))->assertSessionHasErrors('volunteer');
    $this->actingAs($this->coordinator)
        ->delete(route('coord.volunteers.destroy', $pending), ['password' => 'password'])
        ->assertForbidden();

    expect($pending->fresh()->status)->toBe(VolunteerStatus::Pending);
});

test('volunteers can not deactivate or delete anyone', function () {
    $other = $this->scenario->volunteer();

    $this->actingAs($this->volunteer->user)->post(route('coord.volunteers.deactivate', $other))->assertForbidden();
    $this->actingAs($this->volunteer->user)
        ->delete(route('coord.volunteers.destroy', $other), ['password' => 'password'])
        ->assertForbidden();
});
