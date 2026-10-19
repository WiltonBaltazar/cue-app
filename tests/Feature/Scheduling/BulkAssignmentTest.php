<?php

use App\Models\Assignment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AssignmentChanged;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
});

/**
 * Generates the scenario's schedule with the given volunteers and returns its assignments.
 *
 * @return list<array{id: int, lock_version: int}>
 */
function generatedSelection(object $test, int $volunteers = 2): array
{
    foreach (range(1, $volunteers) as $ignored) {
        $test->scenario->volunteer();
    }

    $test->actingAs($test->coordinator)->post(route('coord.schedules.generate', $test->scenario->schedule));

    return Assignment::query()->get()
        ->map(fn (Assignment $assignment) => ['id' => $assignment->id, 'lock_version' => $assignment->lock_version])
        ->all();
}

function bulk(object $test, string $action, array $assignments)
{
    return $test->actingAs($test->coordinator)->post(route('coord.schedules.bulk-assignments', $test->scenario->schedule), [
        'action' => $action,
        'assignments' => $assignments,
    ]);
}

test('several assignments can be removed at once without notifying anyone in a draft', function () {
    $selection = generatedSelection($this);
    expect($selection)->toHaveCount(2);

    bulk($this, 'remove', $selection)->assertSessionHasNoErrors();

    expect(Assignment::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('removing from a published schedule tells each person', function () {
    $selection = generatedSelection($this);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    Notification::fake();

    $selection = Assignment::query()->get()->map(fn ($a) => ['id' => $a->id, 'lock_version' => $a->lock_version])->all();
    bulk($this, 'remove', $selection)->assertSessionHasNoErrors();

    Notification::assertSentTimes(AssignmentChanged::class, 2);
});

test('one outdated assignment refuses the whole change', function () {
    $selection = generatedSelection($this);
    $selection[1]['lock_version'] = 99;

    bulk($this, 'remove', $selection)->assertSessionHasErrors('lock_version');

    expect(Assignment::query()->count())->toBe(2);
});

test('assignments from another schedule are refused', function () {
    $selection = generatedSelection($this);
    $other = Schedule::factory()->create(['starts_on' => '2030-01-01', 'ends_on' => '2030-01-31']);

    $this->actingAs($this->coordinator)
        ->post(route('coord.schedules.bulk-assignments', $other), ['action' => 'remove', 'assignments' => $selection])
        ->assertSessionHasErrors('assignments');

    expect(Assignment::query()->count())->toBe(2);
});

test('automatic replacement puts someone else in each place', function () {
    $selection = generatedSelection($this, volunteers: 3);
    $before = Assignment::query()->pluck('volunteer_id')->all();
    $first = Assignment::query()->findOrFail($selection[0]['id']);

    bulk($this, 'replace', [$selection[0]])->assertSessionHasNoErrors();

    $after = Assignment::query()->pluck('volunteer_id')->all();

    expect($after)->toHaveCount(2)
        ->and($after)->not->toContain($first->volunteer_id)
        ->and(array_diff($after, $before))->toHaveCount(1);
});

test('replacement leaves the place empty when nobody else can come', function () {
    $selection = generatedSelection($this, volunteers: 2);

    bulk($this, 'replace', [$selection[0]])->assertSessionHasNoErrors();

    expect(Assignment::query()->count())->toBe(1);
});

test('pinned assignments survive regeneration and can be unpinned', function () {
    $selection = generatedSelection($this);

    bulk($this, 'lock', $selection)->assertSessionHasNoErrors();
    expect(Assignment::query()->where('is_locked', true)->count())->toBe(2);

    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
    expect(Assignment::query()->pluck('id')->sort()->values()->all())->toBe(collect($selection)->pluck('id')->sort()->values()->all());

    $selection = Assignment::query()->get()->map(fn ($a) => ['id' => $a->id, 'lock_version' => $a->lock_version])->all();
    bulk($this, 'unlock', $selection)->assertSessionHasNoErrors();
    expect(Assignment::query()->where('is_locked', true)->count())->toBe(0);
});

test('volunteers can not use bulk actions', function () {
    $selection = generatedSelection($this);
    $volunteer = $this->scenario->volunteer();

    $this->actingAs($volunteer->user)
        ->post(route('coord.schedules.bulk-assignments', $this->scenario->schedule), ['action' => 'remove', 'assignments' => $selection])
        ->assertForbidden();
});

test('replacing in a published schedule tells who leaves and who comes in', function () {
    generatedSelection($this, volunteers: 3);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    Notification::fake();

    $leaving = Assignment::query()->with('volunteer.user')->firstOrFail();
    bulk($this, 'replace', [['id' => $leaving->id, 'lock_version' => $leaving->lock_version]])->assertSessionHasNoErrors();

    $newcomer = Assignment::query()->with('volunteer.user')->latest('id')->firstOrFail();

    Notification::assertSentTo($leaving->volunteer->user, AssignmentChanged::class, fn (AssignmentChanged $n) => ! $n->added);
    Notification::assertSentTo($newcomer->volunteer->user, AssignmentChanged::class, fn (AssignmentChanged $n) => $n->added);
});
