<?php

use App\Models\Assignment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleWithdrawn;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->scenario->volunteer();
    $this->scenario->volunteer();
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
});

test('a draft schedule is deleted with its assignments and nobody is told', function () {
    expect(Assignment::query()->count())->toBe(2);

    $this->actingAs($this->coordinator)
        ->delete(route('coord.schedules.destroy', $this->scenario->schedule))
        ->assertRedirect(route('coord.schedules.index'));

    expect(Schedule::query()->count())->toBe(0)
        ->and(Assignment::query()->count())->toBe(0)
        ->and(Activity::query()->where('subject_type', Schedule::class)->where('event', 'deleted')->exists())->toBeTrue();
    Notification::assertNothingSent();
});

test('deleting a published schedule tells each person with upcoming shifts once', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    Notification::fake();

    $this->actingAs($this->coordinator)->delete(route('coord.schedules.destroy', $this->scenario->schedule));

    Notification::assertSentTimes(ScheduleWithdrawn::class, 2);
});

test('several schedules can be deleted at once', function () {
    $other = Schedule::factory()->create(['starts_on' => '2030-01-01', 'ends_on' => '2030-01-31']);

    $this->actingAs($this->coordinator)
        ->delete(route('coord.schedules.destroy-many'), ['ids' => [$this->scenario->schedule->id, $other->id]])
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->count())->toBe(0);
});

test('the list says what deleting a published schedule affects', function () {
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));

    $row = $this->actingAs($this->coordinator)->get(route('coord.schedules.index'))
        ->viewData('page')['props']['schedules'][0];

    expect($row['deleteImpact'])->toBe(['shifts' => 2, 'people' => 2, 'past' => 0]);
});

test('volunteers can not delete schedules', function () {
    $volunteer = $this->scenario->volunteer();

    $this->actingAs($volunteer->user)
        ->delete(route('coord.schedules.destroy', $this->scenario->schedule))
        ->assertForbidden();

    expect(Schedule::query()->count())->toBe(1);
});
