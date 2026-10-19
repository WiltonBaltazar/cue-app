<?php

namespace App\Domain\Scheduling;

/**
 * A volunteer already placed on a slot (existing assignment or one just generated).
 */
final readonly class Booking
{
    public function __construct(
        public int $volunteerId,
        public int $eventId,
        public int $positionId,
        public int $roleId,
        public string $date,
        public int $busyFrom,
        public int $busyUntil,
        public ?int $assignmentId = null,
    ) {}

    public static function forSlot(int $volunteerId, Slot $slot, ?int $assignmentId = null): self
    {
        return new self($volunteerId, $slot->eventId, $slot->positionId, $slot->roleId, $slot->date, $slot->busyFrom, $slot->busyUntil, $assignmentId);
    }

    public function isoWeek(): string
    {
        return date('o-W', (int) strtotime($this->date));
    }

    public function month(): string
    {
        return substr($this->date, 0, 7);
    }
}
