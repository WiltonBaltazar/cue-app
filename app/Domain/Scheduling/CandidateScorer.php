<?php

namespace App\Domain\Scheduling;

use App\Enums\Level;

/**
 * Orders eligible candidates for a slot (FR-012), lowest key first:
 * 1. fewest recent assignments (fair rotation, SC-005);
 * 2. regular availability before "horários variáveis" (spec assumption);
 * 3. the role is their area of greatest interest;
 * 4. when a beginner is already in this role at this event, someone who can support others;
 * 5. volunteer id, so the same input always gives the same schedule.
 */
final class CandidateScorer
{
    /**
     * @return array{int, int, int, int, int}
     */
    public function key(VolunteerProfile $volunteer, Slot $slot, SchedulingContext $context, string $windowFrom, string $windowTo): array
    {
        return [
            $context->loadOf($volunteer->id, $windowFrom, $windowTo),
            $volunteer->variableHours ? 1 : 0,
            $volunteer->prefers($slot->roleId) ? 0 : 1,
            $this->needsSupporter($slot, $context) && ! $volunteer->canSupportOthers ? 1 : 0,
            $volunteer->id,
        ];
    }

    /**
     * @param  list<VolunteerProfile>  $candidates
     * @return list<VolunteerProfile>
     */
    public function rank(array $candidates, Slot $slot, SchedulingContext $context, string $windowFrom, string $windowTo): array
    {
        usort($candidates, fn (VolunteerProfile $a, VolunteerProfile $b): int => $this->key($a, $slot, $context, $windowFrom, $windowTo) <=> $this->key($b, $slot, $context, $windowFrom, $windowTo));

        return $candidates;
    }

    private function needsSupporter(Slot $slot, SchedulingContext $context): bool
    {
        $hasBeginner = false;

        foreach ($context->bookingsAt($slot->eventId, $slot->roleId) as $booking) {
            $colleague = $context->volunteer($booking->volunteerId);

            if ($colleague?->canSupportOthers) {
                return false;
            }

            $hasBeginner = $hasBeginner || $colleague?->levelIn($slot->roleId) === Level::Beginner;
        }

        return $hasBeginner;
    }
}
