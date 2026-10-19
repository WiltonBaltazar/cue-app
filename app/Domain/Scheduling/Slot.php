<?php

namespace App\Domain\Scheduling;

use App\Enums\Level;

/**
 * One position at one event that needs people. Times are UTC unix timestamps covering
 * team arrival to the end of teardown, which is what overlap checks use.
 */
final readonly class Slot
{
    public function __construct(
        public int $positionId,
        public int $eventId,
        public int $roleId,
        public ?Level $minLevel,
        public string $date,
        public int $weekday,
        public string $period,
        public int $busyFrom,
        public int $busyUntil,
        public int $quantity = 1,
        public string $label = '',
    ) {}

    public function isoWeek(): string
    {
        return date('o-W', (int) strtotime($this->date));
    }

    public function month(): string
    {
        return substr($this->date, 0, 7);
    }
}
