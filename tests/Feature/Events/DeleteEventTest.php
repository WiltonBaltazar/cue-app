<?php

use App\Enums\EventType;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\EventCancelled;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
});

function staffedSpecialEvent(object $test): Event
{
    $test->scenario->event->update(['type' => EventType::Special]);
    $test->scenario->volunteer();
    $test->scenario->volunteer();
    $test->actingAs($test->coordinator)->post(route('coord.schedules.generate', $test->scenario->schedule));

    return $test->scenario->event;
}

test('an extraordinary event is deleted with its places and people', function () {
    $event = staffedSpecialEvent($this);
    expect(Assignment::query()->count())->toBe(2);

    $this->actingAs($this->coordinator)
        ->delete(route('coord.events.destroy', $event))
        ->assertRedirect(route('coord.events.index'));

    expect(Event::query()->find($event->id))->toBeNull()
        ->and(Assignment::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('people in a published schedule are told', function () {
    $event = staffedSpecialEvent($this);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    Notification::fake();

    $this->actingAs($this->coordinator)->delete(route('coord.events.destroy', $event));

    Notification::assertSentTimes(EventCancelled::class, 2);
});

test('its own schedule goes with it', function () {
    $this->scenario->event->update(['type' => EventType::Special]);
    $this->scenario->schedule->delete();
    $this->actingAs($this->coordinator)->post(route('coord.events.schedule', $this->scenario->event));

    $this->actingAs($this->coordinator)->delete(route('coord.events.destroy', $this->scenario->event));

    expect(Schedule::query()->count())->toBe(0);
});

test('recurring events can not be deleted', function () {
    $this->actingAs($this->coordinator)
        ->delete(route('coord.events.destroy', $this->scenario->event))
        ->assertForbidden();

    expect(Event::query()->find($this->scenario->event->id))->not->toBeNull();
});

test('volunteers can not delete events', function () {
    $this->scenario->event->update(['type' => EventType::Special]);
    $volunteer = $this->scenario->volunteer();

    $this->actingAs($volunteer->user)
        ->delete(route('coord.events.destroy', $this->scenario->event))
        ->assertForbidden();
});
