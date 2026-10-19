<?php

use App\Enums\AssignmentStatus;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\SwapStatus;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapAccepted;
use App\Notifications\SwapDecided;
use App\Notifications\SwapRequested;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->requester = $this->scenario->volunteer(Level::Intermediate);
    $this->colleague = $this->scenario->volunteer(Level::Advanced);
    $this->unavailable = $this->scenario->volunteer(slots: [[2, Period::Evening]]);

    $this->actingAs($this->coordinator)->post(route('coord.assignments.store'), [
        'position_id' => $this->scenario->position->id,
        'volunteer_id' => $this->requester->id,
    ]);
    $this->actingAs($this->coordinator)->post(route('coord.schedules.publish', $this->scenario->schedule));
    $this->assignment = Assignment::query()->firstOrFail();
    auth()->logout();
});

function openSwap(): SwapRequest
{
    test()->actingAs(test()->requester->user)
        ->post(route('swaps.store'), ['assignment_id' => test()->assignment->id])
        ->assertSessionHasNoErrors();

    return SwapRequest::query()->latest('id')->firstOrFail();
}

test('an open request is offered only to eligible colleagues', function () {
    openSwap();

    Notification::assertSentTo($this->colleague->user, SwapRequested::class);
    Notification::assertNotSentTo($this->unavailable->user, SwapRequested::class);

    $this->actingAs($this->colleague->user)
        ->get(route('swaps.index'))
        ->assertInertia(fn ($page) => $page->has('openToMe', 1));

    $this->actingAs($this->unavailable->user)
        ->get(route('swaps.index'))
        ->assertInertia(fn ($page) => $page->has('openToMe', 0));
});

test('an ineligible colleague can not accept', function () {
    $swap = openSwap();

    $this->actingAs($this->unavailable->user)
        ->post(route('swaps.accept', $swap))
        ->assertSessionHasErrors('swap');

    expect($swap->fresh()->status)->toBe(SwapStatus::Open);
});

test('accept then coordinator approval moves the shift to the colleague', function () {
    $swap = openSwap();

    $this->actingAs($this->colleague->user)->post(route('swaps.accept', $swap))->assertSessionHasNoErrors();

    expect($swap->fresh()->status)->toBe(SwapStatus::Accepted);
    Notification::assertSentTo($this->coordinator, SwapAccepted::class);

    $this->actingAs($this->coordinator)
        ->post(route('coord.swaps.decide', $swap), ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $swap->refresh();
    $newShift = Assignment::query()->where('volunteer_id', $this->colleague->id)->firstOrFail();

    expect($swap->status)->toBe(SwapStatus::Approved)
        ->and($swap->decided_by)->toBe($this->coordinator->id)
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::Declined)
        ->and($newShift->status)->toBe(AssignmentStatus::Confirmed)
        ->and($newShift->source)->toBe(Assignment::SOURCE_SWAP);

    Notification::assertSentTo($this->requester->user, SwapDecided::class);
    Notification::assertSentTo($this->colleague->user, SwapDecided::class);
});

test('a coordinator can reject an accepted swap', function () {
    $swap = openSwap();
    $this->actingAs($this->colleague->user)->post(route('swaps.accept', $swap));

    $this->actingAs($this->coordinator)
        ->post(route('coord.swaps.decide', $swap), ['decision' => 'reject'])
        ->assertSessionHasNoErrors();

    expect($swap->fresh()->status)->toBe(SwapStatus::Rejected)
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::Confirmed);
});

test('a directed request can only be accepted by the chosen colleague', function () {
    $third = $this->scenario->volunteer(Level::Advanced);

    $this->actingAs($this->requester->user)
        ->post(route('swaps.store'), ['assignment_id' => $this->assignment->id, 'target_volunteer_id' => $this->colleague->id])
        ->assertSessionHasNoErrors();
    $swap = SwapRequest::query()->firstOrFail();

    Notification::assertSentTo($this->colleague->user, SwapRequested::class);
    Notification::assertNotSentTo($third->user, SwapRequested::class);

    $this->actingAs($third->user)->post(route('swaps.accept', $swap))->assertSessionHasErrors('swap');
    $this->actingAs($this->colleague->user)->post(route('swaps.accept', $swap))->assertSessionHasNoErrors();
});

test('auto-approval applies when enabled and the substitute level is equal or higher', function () {
    app(Settings::class)->set('swap_auto_approve', true);
    $swap = openSwap();

    $this->actingAs($this->colleague->user)->post(route('swaps.accept', $swap));

    expect($swap->fresh()->status)->toBe(SwapStatus::Approved)
        ->and($swap->fresh()->decided_by)->toBeNull();
});

test('auto-approval does not apply when the substitute has a lower level', function () {
    app(Settings::class)->set('swap_auto_approve', true);
    $beginner = $this->scenario->volunteer(Level::Beginner);
    $swap = openSwap();

    $this->actingAs($beginner->user)->post(route('swaps.accept', $swap))->assertSessionHasNoErrors();

    expect($swap->fresh()->status)->toBe(SwapStatus::Accepted);
});

test('the requester can cancel an open request', function () {
    $swap = openSwap();

    $this->actingAs($this->colleague->user)->post(route('swaps.cancel', $swap))->assertForbidden();
    $this->actingAs($this->requester->user)->post(route('swaps.cancel', $swap))->assertSessionHasNoErrors();

    expect($swap->fresh()->status)->toBe(SwapStatus::Cancelled);
});

test('requests within the urgency threshold are marked urgent', function () {
    app(Settings::class)->set('urgent_threshold_hours', 24 * 30);

    expect(openSwap()->is_urgent)->toBeTrue();
});

test('requests expire when the event starts', function () {
    $swap = openSwap();

    $this->travelTo($this->scenario->event->starts_at->addMinute());
    $this->artisan('swaps:expire')->assertSuccessful();

    expect($swap->fresh()->status)->toBe(SwapStatus::Expired);
});

test('only one active request per shift', function () {
    openSwap();

    $this->actingAs($this->requester->user)
        ->post(route('swaps.store'), ['assignment_id' => $this->assignment->id])
        ->assertSessionHasErrors('assignment_id');
});
