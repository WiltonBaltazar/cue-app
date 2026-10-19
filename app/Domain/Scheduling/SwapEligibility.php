<?php

namespace App\Domain\Scheduling;

use App\Models\Assignment;
use App\Models\Volunteer;
use Illuminate\Support\Collection;

/**
 * Who may take over a shift (FR-017): the same hard rules as scheduling, checked for the
 * colleague at that position. The requester's own booking is ignored.
 */
class SwapEligibility
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
    ) {}

    /**
     * @return list<Violation>
     */
    public function violations(Assignment $assignment, Volunteer $candidate): array
    {
        $assignment->loadMissing(['event', 'position']);
        $event = $assignment->event;

        $context = $this->loader->forPeriod($event->date, $event->date, $candidate);
        $context->remove($assignment->volunteer_id, $event->id);

        return $this->checker->check($this->loader->profileOf($candidate), $this->loader->slotFor($assignment->position, $event), $context, automatic: false);
    }

    public function isEligible(Assignment $assignment, Volunteer $candidate): bool
    {
        return $candidate->id !== $assignment->volunteer_id && $this->violations($assignment, $candidate) === [];
    }

    /**
     * Active colleagues who have the role and break no rule for this shift.
     *
     * @return Collection<int, Volunteer>
     */
    public function eligibleColleagues(Assignment $assignment): Collection
    {
        $assignment->loadMissing(['event', 'position']);
        $event = $assignment->event;
        $context = $this->loader->forPeriod($event->date, $event->date);
        $context->remove($assignment->volunteer_id, $event->id);
        $slot = $this->loader->slotFor($assignment->position, $event);

        return Volunteer::query()
            ->schedulable()
            ->whereKeyNot($assignment->volunteer_id)
            ->whereHas('skills', fn ($query) => $query->where('team_role_id', $assignment->position->team_role_id))
            ->with(['skills', 'availabilities', 'user'])
            ->get()
            ->filter(fn (Volunteer $volunteer) => $this->checker->check($this->loader->profileOf($volunteer), $slot, $context, automatic: false) === [])
            ->values();
    }
}
