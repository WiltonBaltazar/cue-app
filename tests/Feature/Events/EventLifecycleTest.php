<?php

use App\Enums\AssignmentStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\ActivityTemplate;
use App\Models\Event;
use App\Models\TeamRole;
use App\Models\User;
use App\Notifications\ShiftRescheduled;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    $this->coordinator = User::factory()->coordinator()->create();
    $this->camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    $this->audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function specialEventPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Casamento Machava',
        'date' => LocalTime::today()->addDays(10)->toDateString(),
        'arrival_time' => '13:00',
        'start_time' => '14:00',
        'end_time' => '17:00',
        'teardown_minutes' => 60,
        'location' => 'Salão principal',
        'positions' => [
            ['team_role_id' => test()->camera->id, 'quantity' => 2, 'min_level' => null],
            ['team_role_id' => test()->audio->id, 'quantity' => 1, 'min_level' => 'intermediate'],
        ],
    ], $overrides);
}

test('coordinators create a special event with arrival before the start', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.events.store'), specialEventPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $event = Event::query()->with('positions')->firstOrFail();

    expect($event->type)->toBe(EventType::Special)
        ->and(LocalTime::timeOf($event->arrival_at))->toBe('13:00')
        ->and(LocalTime::timeOf($event->starts_at))->toBe('14:00')
        ->and(LocalTime::timeOf($event->teardown_until))->toBe('18:00')
        ->and($event->period->value)->toBe('afternoon')
        ->and($event->requiredPeople())->toBe(3);
});

test('event times must be in order', function (array $times, string $field) {
    $this->actingAs($this->coordinator)
        ->post(route('coord.events.store'), specialEventPayload($times))
        ->assertSessionHasErrors($field);
})->with([
    'arrival after start' => [['arrival_time' => '14:30'], 'arrival_time'],
    'end before start' => [['end_time' => '13:30'], 'end_time'],
    'end equal to start' => [['end_time' => '14:00'], 'end_time'],
]);

test('events can not be created in the past', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.events.store'), specialEventPayload(['date' => LocalTime::today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('date');
});

test('a role appears at most once in the positions', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.events.store'), specialEventPayload([
            'positions' => [
                ['team_role_id' => $this->camera->id, 'quantity' => 1],
                ['team_role_id' => $this->camera->id, 'quantity' => 2],
            ],
        ]))
        ->assertSessionHasErrors('positions.1.team_role_id');
});

test('editing a generated occurrence marks it customised and leaves the template alone', function () {
    $template = ActivityTemplate::factory()->withPosition($this->camera, 3)->create();
    $event = Event::factory()->withPosition($this->camera, 3)->create([
        'activity_template_id' => $template->id,
        'type' => EventType::Recurring,
    ]);

    $this->actingAs($this->coordinator)
        ->put(route('coord.events.update', $event), specialEventPayload([
            'name' => $event->name,
            'date' => $event->date->toDateString(),
            'positions' => [['team_role_id' => $this->camera->id, 'quantity' => 1]],
        ]))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->is_customized)->toBeTrue()
        ->and($event->fresh()->requiredPeople())->toBe(1)
        ->and($template->fresh()->positions->first()?->quantity)->toBe(3);
});

test('cancelling records the reason and is audited', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.events.cancel', $event), ['reason' => 'Chuva forte'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('coord.events.show', $event));

    $event->refresh();

    expect($event->status)->toBe(EventStatus::Cancelled)
        ->and($event->cancel_reason)->toBe('Chuva forte')
        ->and($event->cancelled_at)->not->toBeNull()
        ->and(Activity::query()->where('subject_type', Event::class)->where('subject_id', $event->id)->where('event', 'updated')->exists())->toBeTrue();
});

test('a cancelled event can not be edited or cancelled again', function () {
    $event = Event::factory()->create();
    $this->actingAs($this->coordinator)->post(route('coord.events.cancel', $event));

    $this->actingAs($this->coordinator)
        ->post(route('coord.events.cancel', $event))
        ->assertSessionHasErrors('event');

    $this->actingAs($this->coordinator)
        ->put(route('coord.events.update', $event), specialEventPayload(['date' => $event->date->toDateString()]))
        ->assertSessionHasErrors('event');
});

test('the calendar lists the month and the agenda in local dates', function () {
    $date = LocalTime::today()->addDays(3);
    Event::factory()->create(['name' => 'Vigília', 'date' => $date->toDateString()]);

    $this->actingAs($this->coordinator)
        ->get(route('coord.events.index', ['mes' => $date->format('Y-m')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/events/index')
            ->where('month', $date->format('Y-m'))
            ->where('events.0.name', 'Vigília')
            ->where('events.0.date', $date->toDateString()));
});

test('team roles can be added, renamed and deactivated but not duplicated', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.team-roles.store'), ['name' => 'Fotografia', 'description' => null])
        ->assertSessionHasNoErrors();

    $role = TeamRole::query()->where('name', 'Fotografia')->firstOrFail();

    $this->actingAs($this->coordinator)
        ->put(route('coord.team-roles.update', $role), ['name' => 'Fotografia e vídeo curto', 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->is_active)->toBeFalse()
        ->and($role->fresh()->name)->toBe('Fotografia e vídeo curto');

    $this->actingAs($this->coordinator)
        ->post(route('coord.team-roles.store'), ['name' => 'Áudio'])
        ->assertSessionHasErrors('name');
});

test('templates can be created with positions and generate occurrences straight away', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.templates.store'), [
            'name' => 'Estudo Bíblico de Terça',
            'weekday' => 2,
            'interval_weeks' => 1,
            'starts_on' => LocalTime::today()->toDateString(),
            'arrival_time' => '17:30',
            'start_time' => '18:30',
            'end_time' => '20:00',
            'teardown_minutes' => 15,
            'location' => 'Templo sede',
            'is_active' => true,
            'positions' => [['team_role_id' => $this->audio->id, 'quantity' => 1, 'min_level' => null]],
        ])
        ->assertSessionHasNoErrors();

    $template = ActivityTemplate::query()->where('name', 'Estudo Bíblico de Terça')->firstOrFail();

    expect($template->period->value)->toBe('evening')
        ->and(Event::query()->where('activity_template_id', $template->id)->count())->toBeGreaterThan(10);
});

test('a template can repeat on the first Sunday of every month', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.templates.store'), [
            'name' => 'Santa Ceia',
            'weekday' => 7,
            'interval_weeks' => 1,
            'month_week' => 1,
            'starts_on' => LocalTime::today()->toDateString(),
            'arrival_time' => '07:30',
            'start_time' => '08:30',
            'end_time' => '11:30',
            'teardown_minutes' => 30,
            'location' => 'Templo sede',
            'is_active' => true,
            'positions' => [['team_role_id' => $this->audio->id, 'quantity' => 1, 'min_level' => null]],
        ])
        ->assertSessionHasNoErrors();

    $template = ActivityTemplate::query()->where('name', 'Santa Ceia')->firstOrFail();
    $dates = Event::query()->where('activity_template_id', $template->id)->pluck('date');

    expect($template->recurrenceLabel())->toBe('1.º domingo de cada mês')
        ->and($dates)->not->toBeEmpty()
        ->and($dates->every(fn ($date) => $date->dayOfWeekIso === 7 && $date->day <= 7))->toBeTrue();
});

test('the monthly week must be 1 to 4 or the last', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.templates.store'), ['month_week' => 5])
        ->assertSessionHasErrors('month_week');
});

describe('editing an event with people assigned', function () {
    beforeEach(function () {
        $this->scenario = ScheduleScenario::create();
        $this->event = $this->scenario->event;
        $this->scenario->volunteer();
        $this->scenario->volunteer();
        $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
        expect($this->event->assignments()->count())->toBe(2);

        $this->update = fn (array $overrides = []) => $this->put(route('coord.events.update', $this->event), specialEventPayload([
            'name' => 'Culto especial',
            'date' => $this->event->date->toDateString(),
            'positions' => [['team_role_id' => $this->camera->id, 'quantity' => 2, 'min_level' => null]],
            ...$overrides,
        ]));
    });

    test('keeps them in their places', function () {
        ($this->update)(['positions' => [
            ['team_role_id' => $this->audio->id, 'quantity' => 1, 'min_level' => null],
            ['team_role_id' => $this->camera->id, 'quantity' => 3, 'min_level' => 'beginner'],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('coord.events.show', $this->event));

        $position = $this->scenario->position->fresh();

        expect($this->event->fresh()->name)->toBe('Culto especial')
            ->and($position->quantity)->toBe(3)
            ->and($position->assignments()->count())->toBe(2)
            ->and($this->event->fresh()->requiredPeople())->toBe(4);
    });

    test('a role with people can not be removed or cut below them', function () {
        ($this->update)(['positions' => [['team_role_id' => $this->audio->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('positions');
        ($this->update)(['positions' => [['team_role_id' => $this->camera->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('positions.0.quantity');

        expect($this->event->fresh()->name)->toBe('Culto de Domingo')
            ->and($this->event->assignments()->count())->toBe(2);
    });

    test('a role left only with declined shifts can be removed', function () {
        $this->event->assignments()->update(['status' => AssignmentStatus::Declined]);

        ($this->update)(['positions' => [['team_role_id' => $this->audio->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();

        expect($this->event->positions()->pluck('team_role_id')->all())->toBe([$this->audio->id]);
    });

    test('a new time is announced to people in a published schedule, once', function () {
        $this->post(route('coord.schedules.publish', $this->scenario->schedule));
        Notification::fake();

        ($this->update)()->assertSessionHasNoErrors();
        Notification::assertSentTimes(ShiftRescheduled::class, 2);

        // Same times again: nothing new to tell.
        Notification::fake();
        ($this->update)(['location' => 'Outra sala'])->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    });

    test('a new time in a draft schedule is not announced', function () {
        Notification::fake();
        ($this->update)()->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    });

    test('reminders go out again for a new arrival time', function () {
        $this->event->assignments()->update(['check_sent_at' => now(), 'reminder_sent_at' => now()]);

        ($this->update)()->assertSessionHasNoErrors();

        expect($this->event->assignments()->whereNull('check_sent_at')->whereNull('reminder_sent_at')->count())->toBe(2);
    });

    test('people who can not come at the new time are flagged, and cleared if it moves back', function () {
        // The scenario's people only come on Sunday mornings.
        ($this->update)()->assertSessionHasNoErrors();
        expect($this->event->assignments()->whereNotNull('conflict_flagged_at')->count())->toBe(2);

        ($this->update)(['arrival_time' => '07:30', 'start_time' => '08:30', 'end_time' => '11:30'])->assertSessionHasNoErrors();
        expect($this->event->assignments()->whereNotNull('conflict_flagged_at')->count())->toBe(0);
    });

    test('a clash the coordinator already accepted is not flagged by a new time', function () {
        $this->event->assignments->each(fn ($assignment) => $assignment->volunteer->availabilities()->delete());

        ($this->update)(['arrival_time' => '07:45', 'start_time' => '08:30', 'end_time' => '11:30'])->assertSessionHasNoErrors();

        expect($this->event->assignments()->whereNotNull('conflict_flagged_at')->count())->toBe(0);
    });

    test('the form says how many people would be told', function () {
        $this->post(route('coord.schedules.publish', $this->scenario->schedule));

        $this->get(route('coord.events.edit', $this->event))
            ->assertInertia(fn ($page) => $page->where('peopleToNotify', 2));
    });

    test('it can not move outside the schedule its people are in', function () {
        ($this->update)(['date' => $this->scenario->schedule->ends_on->addDay()->toDateString()])
            ->assertSessionHasErrors('date');
    });
});

test('an occurrence can not move to a date its template already fills', function () {
    $template = ActivityTemplate::factory()->withPosition($this->camera, 1)->create();
    [$first, $second] = collect([7, 14])->map(fn (int $days) => Event::factory()->withPosition($this->camera, 1)->create([
        'activity_template_id' => $template->id,
        'type' => EventType::Recurring,
        'date' => LocalTime::today()->addDays($days)->toDateString(),
    ]))->all();

    $this->actingAs($this->coordinator)
        ->put(route('coord.events.update', $first), specialEventPayload(['date' => $second->date->toDateString()]))
        ->assertSessionHasErrors('date');
});

test('the event page lists the people once the schedule is published', function () {
    $scenario = ScheduleScenario::create();
    $volunteer = $scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $scenario->schedule));

    $this->get(route('coord.events.show', $scenario->event))
        ->assertInertia(fn ($page) => $page->where('schedule.published', false)->where('team', []));

    $this->post(route('coord.schedules.publish', $scenario->schedule))->assertSessionHasNoErrors();

    $this->get(route('coord.events.show', $scenario->event))
        ->assertInertia(fn ($page) => $page
            ->where('schedule.published', true)
            ->where('team.0.people.0.name', $volunteer->full_name)
            ->where('team.0.people.0.status', 'confirmed'));
});
