<?php

use App\Enums\EventType;
use App\Enums\Period;
use App\Models\Absence;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\User;
use App\Notifications\AssignmentChanged;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
});

/**
 * An extraordinary event on the Saturday afternoon before the scenario's Sunday; scenario
 * volunteers are only available on Sunday mornings by default.
 */
function extraordinaryEvent(object $test, int $quantity = 1): Event
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
    $event->positions()->create(['team_role_id' => $test->scenario->camera->id, 'quantity' => $quantity]);

    return $event;
}

function generateEvent(object $test, Event $event)
{
    return $test->actingAs($test->coordinator)
        ->post(route('coord.schedules.generate-event', [$test->scenario->schedule, $event]));
}

test('an extraordinary event skips people who are not available that day', function () {
    $event = extraordinaryEvent($this);
    $this->scenario->volunteer(); // only available on Sunday mornings

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $event->id)->count())->toBe(0);
});

test('an extraordinary event takes people available that day', function () {
    $event = extraordinaryEvent($this);
    $volunteer = $this->scenario->volunteer(slots: [[6, Period::Afternoon]]);

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $event->id)->pluck('volunteer_id')->all())->toBe([$volunteer->id]);
});

test('an extraordinary event takes people who serve other days by arrangement', function () {
    $event = extraordinaryEvent($this);
    $volunteer = $this->scenario->volunteer(attributes: ['other_days_by_arrangement' => true]);

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $event->id)->pluck('volunteer_id')->all())->toBe([$volunteer->id]);
});

test('an absence still keeps people out of an extraordinary event', function () {
    $event = extraordinaryEvent($this);
    $volunteer = $this->scenario->volunteer();
    Absence::query()->create([
        'volunteer_id' => $volunteer->id,
        'starts_on' => $event->date->toDateString(),
        'ends_on' => $event->date->toDateString(),
    ]);

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $event->id)->count())->toBe(0);
});

test('recurring events still need weekly availability', function () {
    $this->scenario->volunteer(slots: [[6, Period::Evening]]);

    generateEvent($this, $this->scenario->event)->assertSessionHasNoErrors();

    expect(Assignment::query()->count())->toBe(0);
});

test('generating one event leaves the rest of the draft untouched', function () {
    $event = extraordinaryEvent($this);
    $this->scenario->volunteer(attributes: ['other_days_by_arrangement' => true]);
    $this->scenario->volunteer(attributes: ['other_days_by_arrangement' => true]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
    $sundayIds = Assignment::query()->where('event_id', $this->scenario->event->id)->pluck('id')->all();
    expect($sundayIds)->toHaveCount(2);

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $this->scenario->event->id)->pluck('id')->all())->toBe($sundayIds)
        ->and(Assignment::query()->where('event_id', $event->id)->count())->toBe(1);
});

test('on a published schedule only empty places are filled and those people are told', function () {
    $this->scenario->volunteer(attributes: ['other_days_by_arrangement' => true]);
    $this->scenario->volunteer(attributes: ['other_days_by_arrangement' => true]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $event = extraordinaryEvent($this);
    Notification::fake();

    generateEvent($this, $event)->assertSessionHasNoErrors();

    expect(Assignment::query()->where('event_id', $event->id)->count())->toBe(1)
        ->and(Assignment::query()->where('event_id', $this->scenario->event->id)->count())->toBe(2);
    Notification::assertSentTimes(AssignmentChanged::class, 1);
});

test('an event outside the schedule is not found', function () {
    $outside = Event::factory()->create(['date' => $this->scenario->schedule->ends_on->addMonth()->toDateString()]);

    generateEvent($this, $outside)->assertNotFound();
});
