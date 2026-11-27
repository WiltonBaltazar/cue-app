<?php

use App\Enums\AssignmentStatus;
use App\Enums\EventType;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\ScheduleStatus;
use App\Enums\VolunteerStatus;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SwapRequest;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

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

test('the people who cannot serve on a date are named with the reason, never why they are absent', function () {
    $sunday = LocalTime::today()->next(CarbonImmutable::SUNDAY);
    $on = fn (string $name, array $slots = [[7, Period::Morning]], array $attributes = []) => Volunteer::factory()->active()
        ->availableOn($slots)->create(['full_name' => $name, 'variable_hours' => false, ...$attributes]);

    $on('Ana Livre');
    $on('Berta Variável', [], ['variable_hours' => true]);
    $on('Carla Terça', [[2, Period::Evening]]);
    $on('Dino Ausente')->absences()->create([
        'starts_on' => $sunday->subDay()->toDateString(), 'ends_on' => $sunday->toDateString(), 'reason' => 'Consulta médica',
    ]);
    $on('Elsa Pausa')->forceFill(['status' => VolunteerStatus::Inactive])->save();
    Volunteer::factory()->pending()->create(['full_name' => 'Fora']);

    $response = $this->actingAs($this->coordinator)
        ->getJson(route('app-api.availability-on-date', ['date' => $sunday->toDateString()]))
        ->assertOk();

    expect(array_column($response->json('unavailable'), 'reason', 'name'))->toBe([
        'Dino Ausente' => "Ausente de {$sunday->subDay()->format('d/m')} a {$sunday->format('d/m')}",
        'Elsa Pausa' => 'Em pausa',
        'Carla Terça' => 'Não costuma poder neste dia da semana',
    ])
        ->and(array_column($response->json('available'), 'when', 'name'))->toBe(['Ana Livre' => 'Manhã', 'Berta Variável' => 'Horários variáveis'])
        ->and($response->getContent())->not->toContain('Consulta');
});

test('the people without a shift in a month are named with why', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00', LocalTime::zone()));
    $sunday = CarbonImmutable::parse('2026-11-08');
    $event = Event::factory()->withPosition($this->audio, 2, Level::Intermediate)->create([
        'type' => EventType::Recurring, 'date' => $sunday->toDateString(), 'period' => Period::Morning,
        'arrival_at' => LocalTime::toUtc($sunday, '07:30'), 'starts_at' => LocalTime::toUtc($sunday, '08:30'),
        'ends_at' => LocalTime::toUtc($sunday, '11:30'), 'teardown_until' => LocalTime::toUtc($sunday, '12:00'),
    ]);
    $person = fn (string $name, TeamRole $role, array $slots = [[7, Period::Morning]], Level $level = Level::Advanced) => Volunteer::factory()->active()
        ->withSkill($role, $level)->availableOn($slots)->create(['full_name' => $name, 'variable_hours' => false]);

    $serving = $person('Ana Serve', $this->audio);
    $person('Beto Livre', $this->audio);
    $person('Carla Câmara', $this->camera);
    $person('Dino Terça', $this->audio, [[2, Period::Evening]]);
    $person('Elsa Ausente', $this->audio)->absences()->create(['starts_on' => '2026-11-07', 'ends_on' => '2026-11-09']);
    $person('Fito Iniciante', $this->audio, level: Level::Beginner);
    $declined = $person('Gina Recusou', $this->audio);

    $position = $event->positions()->firstOrFail();
    Assignment::factory()->create(['position_id' => $position->id, 'volunteer_id' => $serving->id]);
    Assignment::factory()->create(['position_id' => $position->id, 'volunteer_id' => $declined->id])
        ->forceFill(['status' => AssignmentStatus::Declined])->save();

    $reasons = fn () => array_column($this->actingAs($this->coordinator)
        ->getJson(route('app-api.volunteers-not-serving', ['month' => '2026-11']))
        ->assertOk()->assertJsonPath('events', 1)
        ->json('notServing'), 'reason', 'name');

    expect($reasons())->toBe([
        'Beto Livre' => 'Há lugar por preencher em 08/11',
        'Carla Câmara' => 'Nenhum evento do mês pede as funções que tem',
        'Dino Terça' => 'Não costuma poder nos dias e períodos em que há lugares na sua função',
        'Elsa Ausente' => 'Ausente nas datas em que podia servir',
        'Fito Iniciante' => 'Nível abaixo do mínimo pedido',
        'Gina Recusou' => 'Foi escalado, mas saiu (recusa, ausência ou troca)',
    ]);

    $position->update(['quantity' => 1]);

    expect($reasons()['Beto Livre'])->toBe('Podia servir, mas os lugares são de outras pessoas');
});

test('the named availability endpoints are coordinator only and validate their input', function () {
    $urls = [
        route('app-api.availability-on-date', ['date' => '2026-11-08']),
        route('app-api.volunteers-not-serving', ['month' => '2026-11']),
    ];

    foreach ($urls as $url) {
        $this->actingAs(User::factory()->volunteer()->create())->getJson($url)->assertForbidden();
        $this->actingAs($this->coordinator)->getJson(strtok($url, '?').'?date=x&month=x')->assertStatus(422);
    }
});

test('each count of the matrix opens the names behind it, with the same filters', function () {
    $person = fn (string $name, TeamRole $role, Level $level = Level::Beginner, array $slots = [[7, Period::Morning]]) => Volunteer::factory()->active()
        ->withSkill($role, $level)->availableOn($slots)->create(['full_name' => $name]);
    $person('Berta Áudio', $this->audio, Level::Advanced);
    $person('Ana Câmara', $this->camera);
    $person('Carlos Áudio', $this->audio);
    $person('Dino Terça', $this->audio, slots: [[2, Period::Evening]]);
    Volunteer::factory()->pending()->availableOn([[7, Period::Morning]])->create();

    $this->actingAs($this->coordinator);
    $cell = ['weekday' => 7, 'period' => 'morning'];

    foreach ([
        [[], ['Ana Câmara', 'Berta Áudio', 'Carlos Áudio']],
        [['role' => 'audio'], ['Berta Áudio', 'Carlos Áudio']],
        [['role' => 'audio', 'minLevel' => 'intermediate'], ['Berta Áudio']],
    ] as [$filters, $names]) {
        $people = $this->getJson(route('app-api.availability-people', [...$cell, ...$filters]))->assertOk()->json('data');

        expect(array_column($people, 'name'))->toBe($names)
            ->and($this->getJson(route('app-api.availability-matrix', $filters))->json('counts')['7.morning'])->toBe(count($names));
    }

    expect($people[0]['roles'])->toBe(['Áudio (Avançado)']);

    $this->getJson(route('app-api.availability-people', ['weekday' => 8, 'period' => 'x']))->assertJsonValidationErrors(['weekday', 'period']);
    $this->actingAs(User::factory()->volunteer()->create())->getJson(route('app-api.availability-people', $cell))->assertForbidden();
});

test('people with variable hours count in every cell and are marked in the names, as the scheduler sees them', function () {
    Volunteer::factory()->active()->withSkill($this->audio)->availableOn([[7, Period::Morning]])->create(['full_name' => 'Ana Domingo']);
    Volunteer::factory()->active()->withSkill($this->audio)->availableOn([])->create(['full_name' => 'Berta Variável', 'variable_hours' => true]);

    $this->actingAs($this->coordinator);

    $counts = $this->getJson(route('app-api.availability-matrix'))->json('counts');
    expect($counts['7.morning'])->toBe(2)
        ->and($counts['2.evening'])->toBe(1)
        ->and($counts)->toHaveCount(21);

    $people = $this->getJson(route('app-api.availability-people', ['weekday' => 2, 'period' => 'evening']))->json('data');
    expect(array_column($people, 'name'))->toBe(['Berta Variável'])
        ->and($people[0]['variableHours'])->toBeTrue();
});
