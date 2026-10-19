<?php

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\ActivityTemplate;
use App\Models\Event;
use App\Models\TeamRole;
use App\Models\User;
use App\Support\LocalTime;
use Spatie\Activitylog\Models\Activity;

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
