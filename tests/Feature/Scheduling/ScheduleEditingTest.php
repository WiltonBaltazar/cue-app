<?php

use App\Actions\Events\GenerateOccurrences;
use App\Enums\AssignmentStatus;
use App\Enums\Period;
use App\Enums\ScheduleStatus;
use App\Models\ActivityTemplate;
use App\Models\Assignment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AssignmentChanged;
use App\Notifications\AvailabilityConflict;
use App\Notifications\EventCancelled;
use App\Notifications\SchedulePublished;
use App\Support\LocalTime;
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

test('a manual edit that breaks a rule needs a note and is audited', function () {
    $unavailable = $this->scenario->volunteer(slots: [[2, Period::Evening]]);

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $unavailable->id,
        ])
        ->assertSessionHasErrors(['override_note', 'violations']);

    expect(Assignment::query()->count())->toBe(0);

    $this->actingAs($this->coordinator)
        ->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $unavailable->id,
            'override_note' => 'Confirmou por telefone que pode vir.',
        ])
        ->assertSessionHasNoErrors();

    $assignment = Assignment::query()->firstOrFail();

    expect($assignment->override_note)->toBe('Confirmou por telefone que pode vir.')
        ->and($assignment->source)->toBe(Assignment::SOURCE_MANUAL)
        ->and(Activity::query()->where('subject_type', Assignment::class)->where('subject_id', $assignment->id)->where('causer_id', $this->coordinator->id)->exists())->toBeTrue();
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
        'skills' => [['team_role_id' => $this->scenario->camera->id, 'has_experience' => true]],
        'availability' => [['weekday' => 2, 'period' => 'evening']],
        'max_frequency' => 'weekly',
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
