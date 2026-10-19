<?php

use App\Actions\Registration\RejectVolunteer;
use App\Enums\AssignmentStatus;
use App\Enums\VolunteerStatus;
use App\Models\Assignment;
use App\Models\RejectedRegistration;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\DataRemovalRequested;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create(['name' => 'Coordenadora Rute']);
});

/**
 * A volunteer with one published shift in the future.
 */
function volunteerWithPublishedShift(User $coordinator): Volunteer
{
    $scenario = ScheduleScenario::create();
    $volunteer = $scenario->volunteer();

    test()->actingAs($coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $scenario->position->id,
        'volunteer_id' => $volunteer->id,
    ]);
    test()->actingAs($coordinator)->post(route('coord.schedules.publish', $scenario->schedule));

    return $volunteer->refresh();
}

/**
 * @return array<int, array<string, mixed>>
 */
function auditEntries(User $coordinator, array $query = []): array
{
    return test()->actingAs($coordinator)
        ->get(route('coord.audit', $query))
        ->assertOk()
        ->viewData('page')['props']['entries'];
}

test('rejected registrations are purged after 30 days, leaving only the phone hash', function () {
    $old = Volunteer::factory()->pending()->create();
    $recent = Volunteer::factory()->pending()->create();
    $phone = $old->user->phone;

    app(RejectVolunteer::class)->handle($old, $this->coordinator);
    app(RejectVolunteer::class)->handle($recent, $this->coordinator);
    $old->forceFill(['reviewed_at' => now()->subDays(31)])->save();

    $this->artisan('registrations:purge-rejected')->assertSuccessful();

    expect(Volunteer::query()->find($old->id))->toBeNull()
        ->and(User::query()->find($old->user_id))->toBeNull()
        ->and(RejectedRegistration::lastRejectionFor($phone))->not->toBeNull()
        ->and(Volunteer::query()->find($recent->id))->not->toBeNull();
});

test('a removal request needs the current password', function () {
    $volunteer = Volunteer::factory()->active()->create();

    $this->actingAs($volunteer->user)
        ->post(route('data-removal.store'), ['password' => 'errada'])
        ->assertSessionHasErrors('password');

    expect($volunteer->refresh()->removal_scheduled_for)->toBeNull();
});

test('a removal request schedules anonymisation in 7 days and frees upcoming shifts', function () {
    $volunteer = volunteerWithPublishedShift($this->coordinator);

    $this->actingAs($volunteer->user)
        ->post(route('data-removal.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $volunteer->refresh();

    expect($volunteer->removal_scheduled_for?->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and(Assignment::query()->firstOrFail()->status)->toBe(AssignmentStatus::NeedsReplacement)
        ->and(Volunteer::query()->schedulable()->whereKey($volunteer->id)->exists())->toBeFalse();

    Notification::assertSentTo($this->coordinator, DataRemovalRequested::class);
});

test('a removal request can be cancelled within the 7 days', function () {
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Marta Cossa']);

    $this->actingAs($volunteer->user)->post(route('data-removal.store'), ['password' => 'password']);
    $this->actingAs($volunteer->user)->delete(route('data-removal.destroy'))->assertSessionHasNoErrors();

    $this->travel(8)->days();
    $this->artisan('volunteers:process-removals')->assertSuccessful();

    $volunteer->refresh();

    expect($volunteer->removal_scheduled_for)->toBeNull()
        ->and($volunteer->status)->toBe(VolunteerStatus::Active)
        ->and($volunteer->full_name)->toBe('Marta Cossa')
        ->and($volunteer->user)->not->toBeNull();
});

test('after 7 days the volunteer is anonymised and their history kept', function () {
    $volunteer = volunteerWithPublishedShift($this->coordinator);
    $userId = $volunteer->user_id;

    $this->actingAs($volunteer->user)->post(route('data-removal.store'), ['password' => 'password']);

    $this->travel(6)->days();
    $this->artisan('volunteers:process-removals');
    expect($volunteer->refresh()->anonymized_at)->toBeNull();

    $this->travel(2)->days();
    $this->artisan('volunteers:process-removals')->assertSuccessful();

    $volunteer->refresh();

    expect($volunteer->status)->toBe(VolunteerStatus::Anonymized)
        ->and($volunteer->full_name)->toBe("Voluntário #{$volunteer->id}")
        ->and($volunteer->birth_date)->toBeNull()
        ->and($volunteer->neighbourhood)->toBeNull()
        ->and($volunteer->motivations)->toBeNull()
        ->and($volunteer->user_id)->toBeNull()
        ->and(User::query()->find($userId))->toBeNull()
        ->and($volunteer->availabilities()->count())->toBe(0)
        ->and($volunteer->assignments()->count())->toBe(1);

    $this->actingAs($this->coordinator)
        ->get(route('coord.volunteers.edit', $volunteer))
        ->assertForbidden();
});

test('an audit row for a profile edit contains no name, phone or e-mail values', function () {
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Lurdes Mondlane']);

    $this->actingAs($volunteer->user);
    $volunteer->update(['full_name' => 'Lurdes A. Mondlane', 'neighbourhood' => 'Malhangalene']);
    $volunteer->user->update(['phone' => '+258841112233', 'email' => 'lurdes@exemplo.com']);

    $json = Activity::query()->where('event', 'updated')->get()->toJson();

    expect($json)->not->toContain('Mondlane')
        ->and($json)->not->toContain('Malhangalene')
        ->and($json)->not->toContain('841112233')
        ->and($json)->not->toContain('lurdes@exemplo.com');
});

test('audit rows are unchanged by anonymisation and the page shows "Voluntário #id"', function () {
    $volunteer = Volunteer::factory()->pending()->create(['full_name' => 'Tomás Nhantumbo']);

    $this->actingAs($this->coordinator)->post(route('coord.registrations.approve', $volunteer));

    $snapshot = fn (?int $upTo = null) => Activity::query()
        ->when($upTo !== null, fn ($query) => $query->where('id', '<=', $upTo))
        ->orderBy('id')->get()
        ->map->only(['id', 'description', 'event', 'subject_type', 'subject_id', 'causer_id', 'properties', 'created_at'])
        ->toJson();
    $lastId = (int) Activity::query()->max('id');
    $before = $snapshot($lastId);

    expect(collect(auditEntries($this->coordinator, ['tipo' => 'volunteers']))->pluck('subject'))->toContain('Tomás Nhantumbo');

    $this->actingAs($volunteer->refresh()->user)->post(route('data-removal.store'), ['password' => 'password']);
    $this->travel(8)->days();
    $this->artisan('volunteers:process-removals');

    expect($snapshot($lastId))->toBe($before)
        ->and(Activity::query()->count())->toBeGreaterThan(substr_count($before, '"id"'));

    $entries = collect(auditEntries($this->coordinator, ['tipo' => 'volunteers']));
    $approval = $entries->first(fn (array $entry) => collect($entry['changes'])->contains(fn (array $change) => $change['field'] === 'status' && $change['new'] === 'active'));

    expect($entries->pluck('subject')->unique()->all())->toBe(["Voluntário #{$volunteer->id}"])
        ->and($approval['actor'])->toBe('Coordenadora Rute')
        ->and(json_encode($entries))->not->toContain('Nhantumbo');
});
