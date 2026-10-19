<?php

use App\Models\Assignment;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->me = $this->scenario->volunteer(attributes: ['full_name' => 'Ana Maria Machava']);
    $this->colleague = $this->scenario->volunteer(attributes: ['full_name' => 'João Pedro Sitoe']);

    foreach ([$this->me, $this->colleague] as $volunteer) {
        $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $volunteer->id,
        ]);
    }
    auth()->logout();
});

test('draft schedules are invisible to volunteers', function () {
    $this->actingAs($this->me->user)
        ->get(route('schedules.show', $this->scenario->schedule))
        ->assertNotFound();

    $this->actingAs($this->me->user)
        ->get(route('volunteer.shifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('volunteer/shifts/index')->has('shifts', 0));
});

test('after publishing, volunteers see the team schedule with short names and roles only', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $response = $this->actingAs($this->me->user)
        ->get(route('schedules.show', $this->scenario->schedule))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('schedules/show')
            ->where('events.0.positions.0.people', fn ($people) => collect($people)->pluck('name')->sort()->values()->all() === ['Ana M.', 'João S.'])
            ->where('events.0.positions.0.people', fn ($people) => collect($people)->firstWhere('isMe', true)['name'] === 'Ana M.'));

    $content = $response->getContent();

    expect($content)->not->toContain('Sitoe')
        ->and($content)->not->toContain((string) $this->colleague->user?->phone)
        ->and($content)->not->toContain('Pedro');
});

test('my shifts lists my published shifts with arrival time', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->me->user)
        ->get(route('volunteer.shifts.index'))
        ->assertInertia(fn ($page) => $page
            ->has('shifts', 1)
            ->where('shifts.0.eventName', 'Culto de Domingo')
            ->where('shifts.0.role', 'Operação de câmara')
            ->where('shifts.0.arrivalTime', '07:30'));
});

test('the volunteer home shows the next shifts', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->me->user)
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->component('volunteer/home')->has('nextShifts', 1));
});

test('the home shows who else serves at the next event, by short name only', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $response = $this->actingAs($this->me->user)->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->where('nextTeam.needsReplacement', 0)
        ->where('nextTeam.people', [
            ['name' => 'Ana M.', 'role' => $this->scenario->camera->name, 'isMe' => true],
            ['name' => 'João S.', 'role' => $this->scenario->camera->name, 'isMe' => false],
        ])
        ->where('openSwaps', 0));

    expect($response->getContent())->not->toContain('Sitoe')
        ->not->toContain($this->colleague->user->phone);
});

test('the home counts colleagues\' open requests the volunteer could take', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $other = $this->scenario->volunteer(attributes: ['full_name' => 'Rita Chongo']);
    $assignment = Assignment::query()->where('volunteer_id', $this->colleague->id)->sole();

    $this->actingAs($this->colleague->user)->post(route('swaps.store'), ['assignment_id' => $assignment->id]);

    $this->actingAs($other->user)
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('openSwaps', 1));
});

test('the PDF contains short names only', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $response = $this->actingAs($this->coordinator)
        ->get(route('coord.schedules.pdf', $this->scenario->schedule))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(strlen((string) $response->getContent()))->toBeGreaterThan(500);
});

test('opened from a shift, the team schedule shows only that event', function () {
    Event::factory()->create(['name' => 'Ensaio Geral', 'date' => $this->scenario->event->date->toDateString()]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->me->user)
        ->get(route('schedules.show', $this->scenario->schedule))
        ->assertInertia(fn ($page) => $page->where('focusedEventId', null)->has('events', 2));

    $this->actingAs($this->me->user)
        ->get(route('schedules.show', ['schedule' => $this->scenario->schedule, 'evento' => $this->scenario->event->id]))
        ->assertInertia(fn ($page) => $page
            ->where('focusedEventId', $this->scenario->event->id)
            ->has('events', 1)
            ->where('events.0.name', 'Culto de Domingo'));

    // An event from another schedule falls back to the full schedule.
    $this->actingAs($this->me->user)
        ->get(route('schedules.show', ['schedule' => $this->scenario->schedule, 'evento' => 999999]))
        ->assertInertia(fn ($page) => $page->where('focusedEventId', null)->has('events', 2));
});
