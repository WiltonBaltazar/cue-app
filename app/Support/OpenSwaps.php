<?php

namespace App\Support;

use App\Domain\Scheduling\SwapEligibility;
use App\Enums\SwapStatus;
use App\Models\SwapRequest;
use App\Models\Volunteer;
use Illuminate\Support\Collection;

/**
 * Open swap requests a volunteer can take: to everyone or to them, not started, and
 * only for shifts they are eligible for (US4-2).
 */
class OpenSwaps
{
    public function __construct(private SwapEligibility $eligibility) {}

    /**
     * @return Collection<int, SwapRequest>
     */
    public function for(Volunteer $volunteer): Collection
    {
        return SwapRequest::query()
            ->where('status', SwapStatus::Open)
            ->where('requester_id', '!=', $volunteer->id)
            ->where(fn ($query) => $query->whereNull('target_volunteer_id')->orWhere('target_volunteer_id', $volunteer->id))
            ->whereHas('assignment.event', fn ($query) => $query->where('starts_at', '>', now()))
            ->with(['assignment.event', 'assignment.position.teamRole', 'requester'])
            ->get()
            ->filter(fn (SwapRequest $swap) => $this->eligibility->isEligible($swap->assignment, $volunteer))
            ->sortBy(fn (SwapRequest $swap) => $swap->assignment->event->starts_at->getTimestamp())
            ->values();
    }
}
