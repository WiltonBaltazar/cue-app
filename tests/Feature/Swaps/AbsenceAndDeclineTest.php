<?php

use App\Enums\AssignmentStatus;
use App\Enums\SwapStatus;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\ReplacementNeeded;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->volunteer = $this->scenario->volunteer(attributes: ['full_name' => 'Ana Machava']);
    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $this->volunteer->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $this->assignment = Assignment::query()->firstOrFail();
    auth()->logout();
});

test('an absence over a published shift marks it "Por substituir" and alerts coordinators', function () {
    $date = $this->scenario->event->date;

    $this->actingAs($this->volunteer->user)
        ->post(route('absences.store'), [
            'starts_on' => $date->subDay()->toDateString(),
            'ends_on' => $date->addDays(5)->toDateString(),
            'reason' => 'Viagem de trabalho',
        ])
        ->assertSessionHasNoErrors();

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::NeedsReplacement);

    Notification::assertSentTo($this->coordinator, ReplacementNeeded::class);
});

test('absences can not start in the past or end before they start', function () {
    $this->actingAs($this->volunteer->user)
        ->post(route('absences.store'), [
            'starts_on' => LocalTime::today()->subDay()->toDateString(),
            'ends_on' => LocalTime::today()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('starts_on');

    $this->actingAs($this->volunteer->user)
        ->post(route('absences.store'), [
            'starts_on' => LocalTime::today()->addDays(5)->toDateString(),
            'ends_on' => LocalTime::today()->addDays(2)->toDateString(),
        ])
        ->assertSessionHasErrors('ends_on');
});

test('volunteers see and delete only their own absences', function () {
    $this->actingAs($this->volunteer->user)->post(route('absences.store'), [
        'starts_on' => LocalTime::today()->addDays(20)->toDateString(),
        'ends_on' => LocalTime::today()->addDays(22)->toDateString(),
    ]);
    $absence = $this->volunteer->absences()->firstOrFail();
    $other = $this->scenario->volunteer();

    $this->actingAs($other->user)->delete(route('absences.destroy', $absence))->assertForbidden();

    $this->actingAs($this->volunteer->user)->delete(route('absences.destroy', $absence))->assertRedirect();

    expect($this->volunteer->absences()->count())->toBe(0);
});

test('declining a shift opens a swap request and alerts coordinators', function () {
    $this->actingAs($this->volunteer->user)
        ->post(route('volunteer.shifts.decline', $this->assignment))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $swap = SwapRequest::query()->firstOrFail();

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::Declined)
        ->and($swap->status)->toBe(SwapStatus::Open)
        ->and($swap->assignment_id)->toBe($this->assignment->id);

    Notification::assertSentTo($this->coordinator, ReplacementNeeded::class);
});

test('volunteers can only decline their own upcoming confirmed shifts', function () {
    $other = $this->scenario->volunteer();

    $this->actingAs($other->user)
        ->post(route('volunteer.shifts.decline', $this->assignment))
        ->assertForbidden();

    $this->actingAs($this->volunteer->user)->post(route('volunteer.shifts.decline', $this->assignment));
    $this->actingAs($this->volunteer->user)
        ->post(route('volunteer.shifts.decline', $this->assignment))
        ->assertSessionHasErrors('assignment');
});

test('the absence reason is never shown to colleagues', function () {
    $date = $this->scenario->event->date;
    $this->actingAs($this->volunteer->user)->post(route('absences.store'), [
        'starts_on' => $date->toDateString(),
        'ends_on' => $date->toDateString(),
        'reason' => 'Consulta médica privada',
    ]);
    $colleague = $this->scenario->volunteer();

    foreach ([route('home'), route('swaps.index'), route('schedules.show', $this->scenario->schedule)] as $url) {
        expect($this->actingAs($colleague->user)->get($url)->getContent())->not->toContain('Consulta médica');
    }
});

test('coordinators see declined shifts and open requests on the swaps page', function () {
    $this->actingAs($this->volunteer->user)->post(route('volunteer.shifts.decline', $this->assignment));

    $this->actingAs($this->coordinator)
        ->get(route('coord.swaps.index'))
        ->assertInertia(fn ($page) => $page
            ->component('coord/swaps/index')
            ->has('swaps', 1)
            ->where('swaps.0.requester', 'Ana Machava'));

    $this->actingAs($this->volunteer->user)->get(route('coord.swaps.index'))->assertForbidden();
});

function registerAbsenceOnEventDay(): void
{
    $date = test()->scenario->event->date->toDateString();
    test()->actingAs(test()->volunteer->user)->post(route('absences.store'), ['starts_on' => $date, 'ends_on' => $date]);
}

test('removing an absence gives back shifts nobody has taken yet', function () {
    registerAbsenceOnEventDay();
    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::NeedsReplacement);

    $absence = $this->volunteer->absences()->firstOrFail();
    $this->actingAs($this->volunteer->user)->delete(route('absences.destroy', $absence))->assertRedirect();

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::Confirmed);
});

test('shifts stay up for replacement when the place was filled or another absence covers the day', function () {
    registerAbsenceOnEventDay();
    registerAbsenceOnEventDay();
    [$first, $second] = $this->volunteer->absences()->orderBy('id')->get()->all();

    // Another absence still covers the day.
    $this->actingAs($this->volunteer->user)->delete(route('absences.destroy', $first));
    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::NeedsReplacement);

    // The coordinator has filled both places in the meantime.
    foreach ([$this->scenario->volunteer(), $this->scenario->volunteer()] as $substitute) {
        $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
            'position_id' => $this->scenario->position->id,
            'volunteer_id' => $substitute->id,
        ])->assertSessionHasNoErrors();
    }

    $this->actingAs($this->volunteer->user)->delete(route('absences.destroy', $second));
    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::NeedsReplacement);
});

test('a shift with a swap in progress is not given back', function () {
    $this->actingAs($this->volunteer->user)->post(route('swaps.store'), ['assignment_id' => $this->assignment->id]);
    registerAbsenceOnEventDay();

    $this->actingAs($this->volunteer->user)->delete(route('absences.destroy', $this->volunteer->absences()->firstOrFail()));

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::NeedsReplacement);
});

test('the team schedule shows a place as "Por substituir" without naming who stepped out', function () {
    registerAbsenceOnEventDay();
    $colleague = $this->scenario->volunteer();

    $response = $this->actingAs($colleague->user)
        ->get(route('schedules.show', $this->scenario->schedule))
        ->assertInertia(fn ($page) => $page
            ->where('events.0.positions.0.needsReplacement', 1)
            ->has('events.0.positions.0.people', 0));

    expect($response->getContent())->not->toContain('Ana M.');
});
