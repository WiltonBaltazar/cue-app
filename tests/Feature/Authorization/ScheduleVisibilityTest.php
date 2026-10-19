<?php

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

test('the PDF contains short names only', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $response = $this->actingAs($this->coordinator)
        ->get(route('coord.schedules.pdf', $this->scenario->schedule))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(strlen((string) $response->getContent()))->toBeGreaterThan(500);
});
