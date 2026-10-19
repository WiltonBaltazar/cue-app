<?php

use App\Enums\AssignmentStatus;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\ScheduleStatus;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SwapRequest;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;

beforeEach(function () {
    $this->coordinator = User::factory()->coordinator()->create();
    // Seeded catalogue (TeamRoleSeeder).
    $this->audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();
    $this->camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    // Default event: next week, evening.
    $this->weekday = LocalTime::today()->addWeek()->dayOfWeekIso;
});

test('the matrix counts active volunteers per weekday and period, filtered by role and level', function () {
    Volunteer::factory()->active()->withSkill($this->audio, Level::Advanced)->availableOn([[7, Period::Morning], [2, Period::Evening]])->create();
    Volunteer::factory()->active()->withSkill($this->audio)->availableOn([[7, Period::Morning]])->create();
    Volunteer::factory()->active()->withSkill($this->camera)->availableOn([[7, Period::Morning]])->create();
    Volunteer::factory()->pending()->withSkill($this->audio)->availableOn([[7, Period::Morning]])->create();

    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix'))
        ->assertOk()
        ->assertJsonPath('counts', ['2.evening' => 1, '7.morning' => 3])
        ->assertJsonPath('weekdays', [1, 2, 3, 4, 5, 6, 7])
        ->assertJsonPath('periods', ['morning', 'afternoon', 'evening']);

    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix', ['role' => 'audio']))
        ->assertJsonPath('counts', ['2.evening' => 1, '7.morning' => 2]);

    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix', ['role' => 'audio', 'minLevel' => 'intermediate']))
        ->assertJsonPath('counts', ['2.evening' => 1, '7.morning' => 1]);
});

test('a role is flagged as short when an upcoming event needs more people than are available', function () {
    Event::factory()->withPosition($this->audio, 2)->withPosition($this->camera, 1)->create();
    Volunteer::factory()->active()->withSkill($this->audio)->availableOn([[$this->weekday, Period::Evening]])->create();
    Volunteer::factory()->active()->withSkill($this->camera)->availableOn([[$this->weekday, Period::Evening]])->create();
    // Available, but on another day.
    Volunteer::factory()->active()->withSkill($this->audio)->availableOn([[($this->weekday % 7) + 1, Period::Evening]])->create();

    $key = "{$this->weekday}.evening";

    // Cell keys contain a dot, so they are read with json() rather than a JSON path.
    $all = $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix'))
        ->assertJsonPath('shortages', ['audio']);

    expect($all->json('shortageCells'))->toBe([$key => ['audio']])
        ->and($all->json('demand'))->toBe([$key => 3]);

    $camera = $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix', ['role' => 'operacao-de-camara']))
        ->assertJsonPath('shortages', []);

    expect($camera->json('demand'))->toBe([$key => 1]);
});

test('the minimum level of a position counts towards shortages', function () {
    Event::factory()->withPosition($this->audio, 1, Level::Advanced)->create();
    Volunteer::factory()->active()->withSkill($this->audio, Level::Beginner)->availableOn([[$this->weekday, Period::Evening]])->create();

    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-matrix'))
        ->assertJsonPath('shortages', ['audio']);
});

test('coverage shows filled and required places with the missing roles', function () {
    $event = Event::factory()->withPosition($this->audio, 2)->withPosition($this->camera, 1)->create();
    $schedule = Schedule::factory()->create();
    $schedule->forceFill(['status' => ScheduleStatus::Published])->save();
    $audioPosition = $event->positions()->where('team_role_id', $this->audio->id)->firstOrFail();
    $cameraPosition = $event->positions()->where('team_role_id', $this->camera->id)->firstOrFail();

    Assignment::factory()->create(['schedule_id' => $schedule->id, 'position_id' => $audioPosition->id]);
    $absent = Assignment::factory()->create(['schedule_id' => $schedule->id, 'position_id' => $cameraPosition->id]);
    $absent->forceFill(['status' => AssignmentStatus::NeedsReplacement])->save();

    // Outside the default 30 days.
    Event::factory()->withPosition($this->audio)->create(['date' => LocalTime::today()->addDays(45)->toDateString()]);

    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.coverage'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.eventId', $event->id)
        ->assertJsonPath('data.0.filled', 1)
        ->assertJsonPath('data.0.required', 3)
        ->assertJsonPath('data.0.published', true)
        ->assertJsonPath('data.0.needsReplacement', 1)
        ->assertJsonPath('data.0.gaps', [
            ['role' => 'Operação de câmara', 'missing' => 1],
            ['role' => 'Áudio', 'missing' => 1],
        ]);
});

test('coverage ranges are limited to three months', function () {
    $this->actingAs($this->coordinator)
        ->getJson(route('app-api.coverage', ['from' => '2026-11-01', 'to' => '2027-06-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

test('overview endpoints are coordinator only', function () {
    $volunteer = User::factory()->volunteer()->create();

    $this->actingAs($volunteer)->getJson(route('app-api.availability-matrix'))->assertForbidden();
    $this->actingAs($volunteer)->getJson(route('app-api.coverage'))->assertForbidden();
});

test('the history shows shifts done, declines, swaps and real vs declared frequency', function () {
    $volunteer = Volunteer::factory()->active()->create();
    $volunteer->forceFill(['reviewed_at' => now()->subYear()])->save();
    $schedule = Schedule::factory()->create();
    $schedule->forceFill(['status' => ScheduleStatus::Published])->save();

    foreach ([10, 40, 70] as $daysAgo) {
        $event = Event::factory()->withPosition($this->audio)->create(['date' => LocalTime::today()->subDays($daysAgo)->toDateString()]);
        Assignment::factory()->create(['schedule_id' => $schedule->id, 'position_id' => $event->positions()->value('id'), 'volunteer_id' => $volunteer->id]);
    }

    $declinedEvent = Event::factory()->withPosition($this->audio)->create();
    $declined = Assignment::factory()->create(['schedule_id' => $schedule->id, 'position_id' => $declinedEvent->positions()->value('id'), 'volunteer_id' => $volunteer->id]);
    $declined->forceFill(['status' => AssignmentStatus::Declined, 'responded_at' => now()])->save();
    SwapRequest::query()->create(['assignment_id' => $declined->id, 'requester_id' => $volunteer->id]);

    $this->actingAs($this->coordinator)
        ->get(route('coord.volunteers.history', $volunteer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/volunteers/history')
            ->where('stats.shiftsDone', 3)
            ->where('stats.declines', 1)
            ->where('stats.swapsRequested', 1)
            ->where('frequency.months', 6)
            ->where('frequency.perMonth', 0.5)
            ->where('volunteer.declaredFrequency', $volunteer->max_frequency->label())
            ->has('shifts', 4));
});

test('the dashboard lists pending registrations, swaps and gaps in the next 30 days', function () {
    Volunteer::factory()->pending()->create();
    Event::factory()->withPosition($this->audio, 2)->create();

    $this->actingAs($this->coordinator)
        ->get(route('coord.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/dashboard')
            ->where('pending.count', 1)
            ->where('swapCount', 0)
            ->has('gaps', 1)
            ->where('gaps.0.required', 2)
            ->where('gaps.0.filled', 0));
});

test('overview pages render for coordinators only', function (string $route) {
    $this->actingAs($this->coordinator)->get(route($route))->assertOk();
    $this->actingAs(User::factory()->volunteer()->create())->get(route($route))->assertForbidden();
})->with(['coord.availability', 'coord.coverage']);
