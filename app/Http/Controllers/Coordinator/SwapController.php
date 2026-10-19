<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Swaps\DecideSwap;
use App\Enums\AssignmentStatus;
use App\Enums\SwapStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Support\ShiftDescription;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Swaps waiting for a decision and shifts that need a substitute (US4), urgent first.
 */
class SwapController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SwapRequest::class);

        $swaps = SwapRequest::query()
            ->active()
            ->whereHas('assignment.event', fn ($query) => $query->where('starts_at', '>', now()))
            ->with(['assignment.event', 'assignment.position.teamRole', 'requester', 'target', 'acceptedBy'])
            ->get()
            ->sortBy(fn (SwapRequest $swap) => [$swap->status === SwapStatus::Accepted ? 0 : 1, $swap->is_urgent ? 0 : 1, $swap->assignment->event->starts_at->getTimestamp()])
            ->values();

        $needsReplacement = Assignment::query()
            ->where('status', AssignmentStatus::NeedsReplacement)
            ->whereHas('event', fn ($query) => $query->scheduled()->where('starts_at', '>', now()))
            ->with(['event', 'position.teamRole', 'volunteer'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp())
            ->values();

        return Inertia::render('coord/swaps/index', [
            'swaps' => $swaps->map(fn (SwapRequest $swap) => [
                'id' => $swap->id,
                'status' => $swap->status->value,
                'statusLabel' => $swap->status->label(),
                'isUrgent' => $swap->is_urgent,
                'shift' => ShiftDescription::of($swap->assignment),
                'requester' => $swap->requester->full_name,
                'target' => $swap->target?->full_name,
                'acceptedBy' => $swap->acceptedBy?->full_name,
                'scheduleId' => $swap->assignment->schedule_id,
            ]),
            'needsReplacement' => $needsReplacement->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'shift' => ShiftDescription::of($assignment),
                'volunteer' => $assignment->volunteer->full_name,
                'scheduleId' => $assignment->schedule_id,
            ]),
        ]);
    }

    public function decide(Request $request, SwapRequest $swap, #[CurrentUser] User $coordinator, DecideSwap $decideSwap): RedirectResponse
    {
        Gate::authorize('decide', $swap);

        $validated = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $approve = $validated['decision'] === 'approve';

        $decideSwap->handle($swap, $approve, $coordinator);

        Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Troca aprovada.' : 'Troca rejeitada.']);

        return to_route('coord.swaps.index');
    }
}
