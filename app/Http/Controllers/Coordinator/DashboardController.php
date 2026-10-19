<?php

namespace App\Http\Controllers\Coordinator;

use App\Domain\Overview\CoverageReport;
use App\Enums\AssignmentStatus;
use App\Enums\SwapStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\Volunteer;
use App\Support\LocalTime;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What needs a coordinator now: registrations to review, swaps to decide or that are
 * urgent, and empty places in the next 30 days (US5).
 */
class DashboardController extends Controller
{
    public const GAP_DAYS = 30;

    public function __invoke(CoverageReport $coverage): Response
    {
        Gate::authorize('viewAny', Volunteer::class);

        $pending = Volunteer::query()->awaitingReview()->oldest()->get();

        $swaps = SwapRequest::query()
            ->active()
            ->where(fn ($query) => $query->where('is_urgent', true)->orWhere('status', SwapStatus::Accepted))
            ->whereHas('assignment.event', fn ($query) => $query->where('starts_at', '>', now()))
            ->with(['assignment.event', 'assignment.position.teamRole'])
            ->get()
            ->sortBy(fn (SwapRequest $swap) => [$swap->status === SwapStatus::Accepted ? 0 : 1, $swap->assignment->event->starts_at->getTimestamp()])
            ->values();

        $needsReplacement = Assignment::query()
            ->where('status', AssignmentStatus::NeedsReplacement)
            ->whereHas('event', fn ($query) => $query->scheduled()->where('starts_at', '>', now()))
            ->count();

        $today = LocalTime::today();

        return Inertia::render('coord/dashboard', [
            'pending' => [
                'count' => $pending->count(),
                'items' => $pending->take(5)->map(fn (Volunteer $volunteer) => [
                    'id' => $volunteer->id,
                    'name' => $volunteer->full_name,
                    'status' => $volunteer->status->label(),
                    'submittedAt' => $volunteer->created_at?->toIso8601ZuluString(),
                ])->values(),
            ],
            'swaps' => $swaps->take(5)->map(fn (SwapRequest $swap) => [
                'id' => $swap->id,
                'shift' => ShiftDescription::of($swap->assignment),
                'isUrgent' => $swap->is_urgent,
                'awaitingDecision' => $swap->status === SwapStatus::Accepted,
            ]),
            'swapCount' => $swaps->count(),
            'needsReplacement' => $needsReplacement,
            'gaps' => $coverage->gapsBetween($today, $today->addDays(self::GAP_DAYS)),
            'gapDays' => self::GAP_DAYS,
        ]);
    }
}
