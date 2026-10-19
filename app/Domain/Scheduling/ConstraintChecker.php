<?php

namespace App\Domain\Scheduling;

/**
 * The hard rules of FR-011, defined once and used by automatic generation, manual edits
 * and swaps. Pure: reads only the given context.
 */
final class ConstraintChecker
{
    /**
     * @param  bool  $automatic  "Occasional" volunteers may only be placed by hand.
     * @return list<Violation>
     */
    public function check(VolunteerProfile $volunteer, Slot $slot, SchedulingContext $context, bool $automatic = true): array
    {
        $violations = [];

        if (! $volunteer->isActive) {
            $violations[] = Violation::Inactive;
        }

        $level = $volunteer->levelIn($slot->roleId);

        if ($level === null) {
            $violations[] = Violation::Role;
        } elseif (! $level->meets($slot->minLevel)) {
            $violations[] = Violation::MinLevel;
        }

        if (! $volunteer->isAvailable($slot->weekday, $slot->period)) {
            $violations[] = Violation::Availability;
        }

        if ($context->isAbsentOn($volunteer->id, $slot->date)) {
            $violations[] = Violation::Absence;
        }

        if ($this->exceedsFrequency($volunteer, $slot, $context, $automatic)) {
            $violations[] = Violation::FrequencyCap;
        }

        foreach ($context->bookingsOf($volunteer->id) as $booking) {
            if ($booking->eventId === $slot->eventId) {
                $violations[] = Violation::SameEvent;
            } elseif ($booking->busyFrom < $slot->busyUntil && $slot->busyFrom < $booking->busyUntil) {
                $violations[] = Violation::TimeOverlap;
            }
        }

        return array_values(array_unique($violations, SORT_REGULAR));
    }

    private function exceedsFrequency(VolunteerProfile $volunteer, Slot $slot, SchedulingContext $context, bool $automatic): bool
    {
        if (! $volunteer->frequency->allowsAutomaticAssignment()) {
            return $automatic;
        }

        $bookings = array_filter(
            $context->bookingsOf($volunteer->id),
            fn (Booking $booking): bool => $booking->eventId !== $slot->eventId,
        );

        $weeklyCap = $volunteer->frequency->weeklyCap();
        if ($weeklyCap !== null && count(array_filter($bookings, fn (Booking $booking) => $booking->isoWeek() === $slot->isoWeek())) >= $weeklyCap) {
            return true;
        }

        $monthlyCap = $volunteer->frequency->monthlyCap();

        return $monthlyCap !== null
            && count(array_filter($bookings, fn (Booking $booking) => $booking->month() === $slot->month())) >= $monthlyCap;
    }
}
