<?php

use App\Enums\EventType;
use App\Enums\Period;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
});

function specialEventInScenario(object $test): Event
{
    $saturday = CarbonImmutable::parse($test->scenario->event->date)->subDay();

    $event = Event::factory()->create([
        'name' => 'Conferência',
        'type' => EventType::Special,
        'date' => $saturday->toDateString(),
        'period' => Period::Afternoon,
        'arrival_at' => LocalTime::toUtc($saturday, '14:00'),
        'starts_at' => LocalTime::toUtc($saturday, '15:00'),
        'ends_at' => LocalTime::toUtc($saturday, '17:00'),
        'teardown_until' => LocalTime::toUtc($saturday, '17:30'),
    ]);
    $event->positions()->create(['team_role_id' => $test->scenario->camera->id, 'quantity' => 1]);

    return $event;
}

test('an event can get a schedule of its own inside a period that already has one', function () {
    $event = specialEventInScenario($this);

    $this->actingAs($this->coordinator)
        ->post(route('coord.events.schedule', $event))
        ->assertRedirect();

    $own = Schedule::query()->where('event_id', $event->id)->firstOrFail();

    expect($own->events()->pluck('id')->all())->toBe([$event->id])
        ->and($this->scenario->schedule->events()->pluck('id')->all())->toBe([$this->scenario->event->id]);
});

test('opening it again reuses the same schedule', function () {
    $event = specialEventInScenario($this);

    $this->actingAs($this->coordinator)->post(route('coord.events.schedule', $event));
    $this->actingAs($this->coordinator)->post(route('coord.events.schedule', $event));

    expect(Schedule::query()->where('event_id', $event->id)->count())->toBe(1);
});

test('people picked by hand go into the event schedule', function () {
    $event = specialEventInScenario($this);
    $volunteer = $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.events.schedule', $event));
    $own = Schedule::query()->where('event_id', $event->id)->firstOrFail();

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $event->positions()->value('id'),
        'volunteer_id' => $volunteer->id,
    ])->assertSessionHasNoErrors();

    expect(Assignment::query()->sole()->schedule_id)->toBe($own->id);
});

test('an event already staffed in its period schedule is not split off', function () {
    $this->scenario->volunteer();
    $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));

    $this->actingAs($this->coordinator)
        ->post(route('coord.events.schedule', $this->scenario->event))
        ->assertSessionHasErrors('event');

    expect(Schedule::query()->whereNotNull('event_id')->count())->toBe(0);
});

test('an event without any schedule can be staffed on its own', function () {
    $later = CarbonImmutable::parse($this->scenario->schedule->ends_on)->addMonths(2);
    $event = Event::factory()->create(['date' => $later->toDateString()]);

    $this->actingAs($this->coordinator)
        ->post(route('coord.events.schedule', $event))
        ->assertRedirect();

    expect(Schedule::query()->where('event_id', $event->id)->value('starts_on')->toDateString())->toBe($later->toDateString());
});
