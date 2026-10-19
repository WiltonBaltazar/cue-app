<?php

use App\Enums\AssignmentStatus;
use App\Enums\VolunteerStatus;
use App\Models\Assignment;
use App\Models\User;
use App\Notifications\ReplacementNeeded;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->volunteer = $this->scenario->volunteer();
});

test('pausing marks upcoming published shifts for replacement and alerts coordinators', function () {
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $this->volunteer->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->volunteer->user)
        ->post(route('profile.pause'), ['until' => LocalTime::today()->addMonths(2)->toDateString()])
        ->assertSessionHasNoErrors();

    $this->volunteer->refresh();

    expect($this->volunteer->status)->toBe(VolunteerStatus::Inactive)
        ->and($this->volunteer->paused_by)->toBe($this->volunteer->user_id)
        ->and(Assignment::query()->firstOrFail()->status)->toBe(AssignmentStatus::NeedsReplacement);

    Notification::assertSentTo($this->coordinator, ReplacementNeeded::class);
});

test('paused volunteers are not scheduled', function () {
    $this->actingAs($this->volunteer->user)->post(route('profile.pause'));

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));

    expect(Assignment::query()->count())->toBe(0);
});

test('a pause with a return date ends automatically', function () {
    $this->actingAs($this->volunteer->user)->post(route('profile.pause'), [
        'until' => LocalTime::today()->addDays(3)->toDateString(),
    ]);

    $this->travel(4)->days();
    $this->artisan('volunteers:resume-paused')->assertSuccessful();

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active)
        ->and($this->volunteer->fresh()->inactive_until)->toBeNull();
});

test('volunteers resume themselves; coordinators can pause and resume anyone', function () {
    $this->actingAs($this->volunteer->user)->post(route('profile.pause'));
    $this->actingAs($this->volunteer->user)->post(route('profile.resume'))->assertSessionHasNoErrors();

    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active);

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.pause', $this->volunteer))->assertSessionHasNoErrors();
    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Inactive)
        ->and($this->volunteer->fresh()->paused_by)->toBe($this->coordinator->id);

    $this->actingAs($this->coordinator)->post(route('coord.volunteers.resume', $this->volunteer))->assertSessionHasNoErrors();
    expect($this->volunteer->fresh()->status)->toBe(VolunteerStatus::Active);
});

test('nobody is made inactive automatically; coordinators get a suggestion list instead', function () {
    $quiet = $this->scenario->volunteer();
    $this->travel(4)->months();

    $this->artisan('volunteers:resume-paused')->assertSuccessful();

    expect($quiet->fresh()->status)->toBe(VolunteerStatus::Active);

    $this->actingAs($this->coordinator)
        ->get(route('coord.volunteers.index', ['filtro' => 'sem-turnos']))
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'sem-turnos')
            ->where('volunteers', fn ($volunteers) => collect($volunteers)->pluck('id')->contains($quiet->id)));
});
