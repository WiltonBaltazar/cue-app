<?php

use App\Enums\EventType;
use App\Models\ActivityTemplate;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\EventCancelled;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    $this->template = ActivityTemplate::factory()->withPosition($this->camera, 1)->create(['name' => 'Culto de Domingo']);

    [$this->past, $this->next, $this->later] = collect([-7, 7, 14])->map(fn (int $days) => Event::factory()
        ->withPosition($this->camera)
        ->create([
            'activity_template_id' => $this->template->id,
            'type' => EventType::Recurring,
            'date' => LocalTime::today()->addDays($days)->toDateString(),
            'starts_at' => now()->addDays($days),
        ]))->all();

    $this->schedule = Schedule::factory()->create();
    $this->volunteer = Volunteer::factory()->active()->for(User::factory()->volunteer())->create();
    foreach ([$this->past, $this->next, $this->later] as $event) {
        Assignment::factory()->create([
            'schedule_id' => $this->schedule->id,
            'position_id' => $event->positions()->value('id'),
            'volunteer_id' => $this->volunteer->id,
        ]);
    }
});

test('an activity goes with its dates to come; past ones stay as history', function () {
    $this->actingAs($this->coordinator)
        ->delete(route('coord.templates.destroy', $this->template))
        ->assertRedirect(route('coord.templates.index'));

    expect(ActivityTemplate::query()->count())->toBe(0)
        ->and(Event::query()->pluck('id')->all())->toBe([$this->past->id])
        ->and($this->past->fresh()->activity_template_id)->toBeNull()
        ->and(Assignment::query()->pluck('event_id')->all())->toBe([$this->past->id]);
    Notification::assertNothingSent();
});

test('people in a published schedule are told once, with every date', function () {
    $this->schedule->forceFill(['status' => 'published'])->save();

    $this->actingAs($this->coordinator)->delete(route('coord.templates.destroy', $this->template));

    $dates = "{$this->next->date->format('j/n')} e {$this->later->date->format('j/n')}";
    Notification::assertSentTimes(EventCancelled::class, 1);
    Notification::assertSentTo($this->volunteer->user, EventCancelled::class, fn (EventCancelled $n) => $n->event === "Culto de Domingo ({$dates})");
});

test('the form says what deleting affects', function () {
    $this->actingAs($this->coordinator)
        ->get(route('coord.templates.edit', $this->template))
        ->assertInertia(fn ($page) => $page->where('deletion', ['dates' => 2, 'volunteers' => 1]));
});

test('volunteers can not delete activities', function () {
    $this->actingAs($this->volunteer->user)
        ->delete(route('coord.templates.destroy', $this->template))
        ->assertForbidden();

    expect(ActivityTemplate::query()->count())->toBe(1);
});
