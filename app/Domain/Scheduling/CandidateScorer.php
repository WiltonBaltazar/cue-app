<?php

namespace App\Domain\Scheduling;

use App\Enums\Frequency;
use App\Enums\Level;

/**
 * Orders eligible candidates for a slot (FR-012), lowest key first:
 * 1. no shift yet this calendar month, so everyone serves at least once when there are
 *    places ("Ocasionalmente" after the rest, then people who already have one);
 * 2. fewest recent assignments (fair rotation, SC-005);
 * 2b. on "Gerar de novo", not the person this slot had in the previous proposal, so
 *    regenerating swaps people wherever someone equally fair is available;
 * 3. whoever has gone longest without serving, so that nobody who served before the recent
 *    window is picked again ahead of someone who never did;
 * 4. regular availability before "horários variáveis" (spec assumption);
 * 5. the role is their area of greatest interest;
 * 6. when a beginner is already in this role at this event, someone who can support others;
 * 7. volunteer id, so the same input always gives the same schedule.
 */
final class CandidateScorer
{
    /**
     * @param  array<string, true>  $avoid  keys from self::avoidKey() of the previous proposal
     * @return array{int, int, int, string, int, int, int, int}
     */
    public function key(VolunteerProfile $volunteer, Slot $slot, SchedulingContext $context, string $windowFrom, string $windowTo, array $avoid = []): array
    {
        return [
            match (true) {
                $context->servesInMonth($volunteer->id, $slot->month(), $slot->eventId) => 2,
                $volunteer->frequency === Frequency::Occasional => 1,
                default => 0,
            },
            $context->loadOf($volunteer->id, $windowFrom, $windowTo),
            isset($avoid[self::avoidKey($slot->eventId, $slot->positionId, $volunteer->id)]) ? 1 : 0,
            $context->lastServedBefore($volunteer->id, $slot->date),
            $volunteer->variableHours ? 1 : 0,
            $volunteer->prefers($slot->roleId) ? 0 : 1,
            $this->needsSupporter($slot, $context) && ! $volunteer->canSupportOthers ? 1 : 0,
            $volunteer->id,
        ];
    }

    /**
     * @param  list<VolunteerProfile>  $candidates
     * @param  array<string, true>  $avoid
     * @return list<VolunteerProfile>
     */
    public function rank(array $candidates, Slot $slot, SchedulingContext $context, string $windowFrom, string $windowTo, array $avoid = []): array
    {
        usort($candidates, fn (VolunteerProfile $a, VolunteerProfile $b): int => $this->key($a, $slot, $context, $windowFrom, $windowTo, $avoid) <=> $this->key($b, $slot, $context, $windowFrom, $windowTo, $avoid));

        return $candidates;
    }

    public static function avoidKey(int $eventId, int $positionId, int $volunteerId): string
    {
        return "{$eventId}:{$positionId}:{$volunteerId}";
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
