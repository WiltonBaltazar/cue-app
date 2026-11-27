<?php

use App\Actions\Events\GenerateOccurrences;
use App\Enums\AssignmentStatus;
use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\ScheduleStatus;
use App\Models\ActivityTemplate;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\AssignmentChanged;
use App\Notifications\AvailabilityConflict;
use App\Notifications\EventCancelled;
use App\Notifications\SchedulePublished;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create(['name' => 'Coordenadora Ana']);
    $this->scenario = ScheduleScenario::create();
});

test('creating a schedule generates a proposal and reports gaps', function () {
    $this->scenario->schedule->delete();
    $this->scenario->volunteer();
    $month = LocalTime::today()->next('Sunday');

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.store'), [
            'starts_on' => $month->startOfMonth()->toDateString(),
            'ends_on' => $month->endOfMonth()->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $schedule = Schedule::query()->firstOrFail();

    expect($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->assignments()->count())->toBe(1)
        ->and($schedule->generation_report[0]['missing'] ?? null)->toBe(1);
});

test('a schedule can be created empty, to fill by hand', function () {
    $this->scenario->schedule->delete();
    $this->scenario->volunteer();
    $month = LocalTime::today()->next('Sunday');

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.store'), [
            'starts_on' => $month->startOfMonth()->toDateString(),
            'ends_on' => $month->endOfMonth()->toDateString(),
            'generate' => false,
        ])
        ->assertSessionHasNoErrors();

    $schedule = Schedule::query()->firstOrFail();

    expect($schedule->assignments()->count())->toBe(0)
        ->and($schedule->generation_report)->toBeNull();
});

test('schedules can not overlap', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.store'), [
            'starts_on' => $this->scenario->schedule->starts_on->addDays(3)->toDateString(),
            'ends_on' => $this->scenario->schedule->ends_on->addDays(10)->toDateString(),
        ])
        ->assertSessionHasErrors('starts_on');
});

test('regenerating keeps manual assignments and replaces automatic ones', function () {
    $manual = $this->scenario->volunteer();
    $this->scenario->volunteer();

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $manual->id,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();

    $assignments = $this->scenario->schedule->assignments()->get();

    expect($assignments)->toHaveCount(2)
        ->and($assignments->firstWhere('volunteer_id', $manual->id)?->is_locked)->toBeTrue();
});

test('regenerating swaps people when others are equally fair', function () {
    foreach (range(1, 3) as $i) {
        $this->scenario->volunteer();
    }

    $picked = fn () => $this->scenario->schedule->assignments()->pluck('volunteer_id')->sort()->values()->all();

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();
    $first = $picked();

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();

    expect($first)->toHaveCount(2)
        ->and($picked())->toHaveCount(2)
        ->and($picked())->not->toBe($first);
});

test('regenerating replaces a generated date with the event added by hand', function () {
    $this->scenario->volunteer();
    $this->scenario->volunteer();
    $template = ActivityTemplate::factory()->create(['name' => 'Culto de Domingo', 'starts_on' => LocalTime::today()->subWeek()->toDateString()]);
    $this->scenario->event->update(['activity_template_id' => $template->id]);

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();
    expect($this->scenario->event->assignments()->count())->toBe(2);

    $byHand = Event::factory()->create([
        'name' => 'CULTO DE DOMINGO',
        ...$this->scenario->event->only(['date', 'period', 'arrival_at', 'starts_at', 'ends_at', 'teardown_until']),
    ]);
    $byHand->positions()->create(['team_role_id' => $this->scenario->camera->id, 'quantity' => 2]);

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule))->assertSessionHasNoErrors();

    expect(Event::query()->whereKey($this->scenario->event->id)->exists())->toBeFalse()
        ->and($byHand->refresh()->activity_template_id)->toBe($template->id)
        ->and($byHand->assignments()->count())->toBe(2);
});

test('a manual edit that breaks a rule only warns: no note needed, and it is audited', function () {
    $unavailable = $this->scenario->volunteer(slots: [[2, Period::Evening]]);

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $unavailable->id,
        ])
        ->assertSessionHasNoErrors();

    $assignment = Assignment::query()->firstOrFail();

    expect($assignment->override_note)->toBeNull()
        ->and($assignment->source)->toBe(Assignment::SOURCE_MANUAL)
        ->and(Activity::query()->where('subject_type', Assignment::class)->where('subject_id', $assignment->id)->where('causer_id', $this->coordinator->id)->exists())->toBeTrue();
});

test('an optional note is kept when a rule is broken by hand', function () {
    $unavailable = $this->scenario->volunteer(slots: [[2, Period::Evening]]);

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $unavailable->id,
        'override_note' => 'Confirmou por telefone que pode vir.',
    ]);

    expect(Assignment::query()->firstOrFail()->override_note)->toBe('Confirmou por telefone que pode vir.');
});

test('the same person can not take two places at one event', function () {
    $volunteer = $this->scenario->volunteer();
    $data = ['position_id' => $this->scenario->position->id, 'volunteer_id' => $volunteer->id];

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), $data)->assertSessionHasNoErrors();
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), $data)->assertSessionHasErrors('volunteer_id');

    expect(Assignment::query()->count())->toBe(1);
});

test('going over a volunteer\'s frequency is only a warning: no note needed', function () {
    $monthly = $this->scenario->volunteer(slots: [[7, Period::Morning], [7, Period::Evening]], attributes: ['max_frequency' => Frequency::Monthly]);
    $sunday = $this->scenario->event->date;
    $evening = Event::factory()->create([
        'date' => $sunday->toDateString(),
        'period' => Period::Evening,
        'arrival_at' => LocalTime::toUtc($sunday, '16:30'),
        'starts_at' => LocalTime::toUtc($sunday, '17:00'),
        'ends_at' => LocalTime::toUtc($sunday, '19:00'),
        'teardown_until' => LocalTime::toUtc($sunday, '19:30'),
    ]);
    $eveningPosition = $evening->positions()->create(['team_role_id' => $this->scenario->camera->id, 'quantity' => 1]);

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), ['position_id' => $eveningPosition->id, 'volunteer_id' => $monthly->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), ['position_id' => $this->scenario->position->id, 'volunteer_id' => $monthly->id])
        ->assertSessionHasNoErrors();

    expect(Assignment::query()->where('volunteer_id', $monthly->id)->count())->toBe(2)
        ->and(Assignment::query()->whereNotNull('override_note')->exists())->toBeFalse();
});

test('placing someone below the minimum level is only a warning: no note needed', function () {
    $this->scenario->position->update(['min_level' => Level::Advanced]);
    $beginner = $this->scenario->volunteer(level: Level::Beginner);

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), ['position_id' => $this->scenario->position->id, 'volunteer_id' => $beginner->id])
        ->assertSessionHasNoErrors();

    expect(Assignment::query()->where('volunteer_id', $beginner->id)->whereNull('override_note')->exists())->toBeTrue();
});

test('a stale edit is refused with the name of who changed it', function () {
    [$first, $second, $third] = [$this->scenario->volunteer(), $this->scenario->volunteer(), $this->scenario->volunteer()];
    $other = User::factory()->coordinator()->create(['name' => 'Coordenador Bruno']);

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $first->id,
    ]);
    $assignment = Assignment::query()->firstOrFail();
    $loadedVersion = $assignment->lock_version;

    $this->actingAs($other)->put(route('coord.assignments.update', $assignment), [
        'volunteer_id' => $second->id,
        'lock_version' => $loadedVersion,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->coordinator)
        ->put(route('coord.assignments.update', $assignment), [
            'volunteer_id' => $third->id,
            'lock_version' => $loadedVersion,
        ])
        ->assertSessionHasErrors(['lock_version' => 'Alterado por Coordenador Bruno — recarregar']);

    expect($assignment->fresh()->volunteer_id)->toBe($second->id);
});

test('publishing confirms shifts and notifies each assigned volunteer once', function () {
    $volunteers = [$this->scenario->volunteer(), $this->scenario->volunteer()];
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.publish', $this->scenario->schedule))
        ->assertSessionHasNoErrors();

    $schedule = $this->scenario->schedule->fresh();

    expect($schedule->status)->toBe(ScheduleStatus::Published)
        ->and($schedule->published_by)->toBe($this->coordinator->id)
        ->and($schedule->assignments->every(fn (Assignment $assignment) => $assignment->status === AssignmentStatus::Confirmed))->toBeTrue();

    foreach ($volunteers as $volunteer) {
        Notification::assertSentToTimes($volunteer->user, SchedulePublished::class, 1);
    }
});

test('changing a published schedule tells the people affected', function () {
    [$first, $second] = [$this->scenario->volunteer(), $this->scenario->volunteer()];
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $first->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $assignment = Assignment::query()->firstOrFail();

    $this->actingAs($this->coordinator)->put(route('coord.assignments.update', $assignment), [
        'volunteer_id' => $second->id,
        'lock_version' => $assignment->lock_version,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($second->user, AssignmentChanged::class, fn ($notification) => $notification->added);
    Notification::assertSentTo($first->user, AssignmentChanged::class, fn ($notification) => ! $notification->added);
});

test('cancelling an event tells the people on its published shifts', function () {
    $volunteer = $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $volunteer->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->coordinator)->post(route('coord.events.cancel', $this->scenario->event));

    Notification::assertSentTo($volunteer->user, EventCancelled::class);
});

test('an availability change hitting a published shift flags it without removing it', function () {
    $volunteer = $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $volunteer->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($volunteer->user)->put(route('volunteer.profile.update'), [
        'skills' => [['team_role_id' => $this->scenario->camera->id]],
        'experience_role_ids' => [$this->scenario->camera->id],
        'availability' => [['weekday' => 2, 'period' => 'evening']],
        'max_frequency' => 'weekly',
        'training_availability' => 'regular',
    ])->assertSessionHasNoErrors();

    $assignment = Assignment::query()->firstOrFail();

    expect($assignment->status)->toBe(AssignmentStatus::Confirmed)
        ->and($assignment->conflict_flagged_at)->not->toBeNull();

    Notification::assertSentTo($this->coordinator, AvailabilityConflict::class);
});

test('regenerating events from a template never touches dates with shifts', function () {
    $template = ActivityTemplate::factory()->withPosition($this->scenario->camera, 2)->create([
        'starts_on' => LocalTime::today()->toDateString(),
    ]);
    $this->scenario->event->forceFill(['activity_template_id' => $template->id, 'type' => 'recurring'])->save();
    $volunteer = $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $template->update(['weekday' => 3, 'location' => 'Pavilhão']);
    app(GenerateOccurrences::class)->handle($template);

    expect($this->scenario->event->fresh())->not->toBeNull()
        ->and($this->scenario->event->fresh()->location)->toBe('Templo sede')
        ->and(Assignment::query()->count())->toBe(1);
});

test('coordinators get ranked candidates with the rules each would break', function () {
    $fits = $this->scenario->volunteer();
    $unavailable = $this->scenario->volunteer(slots: [[2, Period::Evening]]);

    $response = $this->actingAs($this->coordinator)
        ->getJson(route('app-api.positions.candidates', $this->scenario->position))
        ->assertOk();

    expect($response->json('data.0.volunteerId'))->toBe($fits->id)
        ->and($response->json('data.0.eligible'))->toBeTrue()
        ->and(collect($response->json('data'))->firstWhere('volunteerId', $unavailable->id)['violations'])->toBe(['availability']);
});

test('people already on the event, and the one being replaced, are not offered', function () {
    $placed = $this->scenario->volunteer();
    $free = $this->scenario->volunteer();

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $placed->id,
    ]);
    $assignment = Assignment::query()->firstOrFail();

    $filling = collect($this->actingAs($this->coordinator)
        ->getJson(route('app-api.positions.candidates', $this->scenario->position))
        ->json('data'))->pluck('volunteerId');

    $replacing = collect($this->actingAs($this->coordinator)
        ->getJson(route('app-api.positions.candidates', ['position' => $this->scenario->position, 'substituir' => $assignment->id]))
        ->json('data'))->pluck('volunteerId');

    expect($filling)->toContain($free->id)->not->toContain($placed->id)
        ->and($replacing)->toContain($free->id)->not->toContain($placed->id);
});

test('a schedule past the horizon gets its recurring events', function (bool $generate) {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Maputo'));
    app(Settings::class)->set('event_horizon_months', 1);
    $template = ActivityTemplate::factory()->withPosition($this->scenario->camera, 1)->create(['starts_on' => '2026-11-01']);
    $this->scenario->volunteer();

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.store'), [
            'starts_on' => '2027-01-20',
            'ends_on' => '2027-02-13',
            'generate' => $generate,
        ])
        ->assertSessionHasNoErrors();

    $schedule = Schedule::query()->where('starts_on', '2027-01-20')->firstOrFail();

    expect($template->events()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2027-01-24', '2027-01-31', '2027-02-07'])
        ->and($schedule->assignments()->count())->toBe($generate ? 3 : 0);

    // The existing empty schedule recovers with "Gerar de novo", without duplicating events.
    $this->post(route('coord.schedules.generate', $schedule))->assertSessionHasNoErrors();

    expect($template->events()->count())->toBe(3)
        ->and($schedule->assignments()->count())->toBe(3);
})->with(['generated' => true, 'empty' => false]);

test('generating leaves cancelled events and inactive activities alone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Maputo'));
    $active = ActivityTemplate::factory()->withPosition($this->scenario->camera, 1)->create(['starts_on' => '2026-11-01']);
    $inactive = ActivityTemplate::factory()->create(['starts_on' => '2026-11-01', 'is_active' => false]);
    app(GenerateOccurrences::class)->handle();
    $cancelled = $active->events()->where('date', '2027-01-24')->firstOrFail();
    $cancelled->forceFill(['status' => 'cancelled'])->save();

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.store'), ['starts_on' => '2027-01-20', 'ends_on' => '2027-02-13'])
        ->assertSessionHasNoErrors();

    expect($active->events()->where('date', '2027-01-24')->count())->toBe(1)
        ->and($cancelled->fresh()->isCancelled())->toBeTrue()
        ->and($inactive->events()->count())->toBe(0);
});

test('people without the role are offered apart, with what else would be broken', function () {
    $fits = $this->scenario->volunteer();
    $other = Volunteer::factory()->active()->availableOn([[2, Period::Evening]])->create(['full_name' => 'Ana Sem Função', 'variable_hours' => false]);
    Volunteer::factory()->pending()->create();

    $response = $this->actingAs($this->coordinator)
        ->getJson(route('app-api.positions.candidates', $this->scenario->position))
        ->assertOk()
        ->assertJsonPath('role', 'Operação de câmara');

    expect(array_column($response->json('data'), 'volunteerId'))->toBe([$fits->id])
        ->and(array_column($response->json('others'), 'volunteerId'))->toBe([$other->id])
        ->and($response->json('others.0.violations'))->toBe(['role', 'availability'])
        ->and($response->json('others.0.level'))->toBeNull();
});

test('someone without the role can be assigned just once or given the role', function (bool $addRole) {
    $other = Volunteer::factory()->active()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $other->id,
            'add_role' => $addRole,
        ])
        ->assertSessionHasNoErrors();

    $skill = $other->skills()->first();

    expect(Assignment::query()->where('volunteer_id', $other->id)->exists())->toBeTrue()
        ->and($skill?->team_role_id)->toBe($addRole ? $this->scenario->camera->id : null)
        ->and($skill?->level)->toBe($addRole ? Level::Beginner : null);
})->with([true, false]);

test('the role is never added twice, nor when the person could not be assigned', function () {
    $skilled = $this->scenario->volunteer(Level::Advanced);
    $payload = fn (Volunteer $volunteer) => ['position_id' => $this->scenario->position->id, 'volunteer_id' => $volunteer->id, 'add_role' => true];
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), $payload($skilled))->assertSessionHasNoErrors();

    expect($skilled->skills()->pluck('level')->all())->toBe([Level::Advanced]);

    $this->scenario->position->update(['quantity' => 1]);
    $other = Volunteer::factory()->active()->create();
    $this->post(route('coord.assignments.store'), $payload($other))->assertSessionHasErrors('position_id');

    expect($other->skills()->count())->toBe(0);
});
