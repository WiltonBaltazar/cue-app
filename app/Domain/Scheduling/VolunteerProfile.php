<?php

namespace App\Domain\Scheduling;

use App\Enums\Frequency;
use App\Enums\Level;

/**
 * What scheduling needs to know about one volunteer. Plain data: no database access.
 */
final readonly class VolunteerProfile
{
    /**
     * @param  array<string, true>  $availability  keys "weekday|period", e.g. "7|morning"
     * @param  array<int, Level>  $levels  team_role_id => level
     * @param  list<int>  $preferredRoleIds
     */
    public function __construct(
        public int $id,
        public bool $isActive,
        public Frequency $frequency,
        public bool $variableHours,
        public bool $canSupportOthers,
        public array $availability,
        public array $levels,
        public array $preferredRoleIds = [],
        public bool $otherDaysByArrangement = false,
    ) {}

    public function isAvailable(int $weekday, string $period): bool
    {
        return $this->variableHours || isset($this->availability["{$weekday}|{$period}"]);
    }

    /**
     * The days and periods the volunteer declared count for every event. An extraordinary
     * event on another day only takes those who said they can serve other days by arrangement.
     */
    public function isAvailableFor(Slot $slot): bool
    {
        return $this->isAvailable($slot->weekday, $slot->period)
            || ($slot->isExtraordinary && $this->otherDaysByArrangement);
    }

    public function levelIn(int $roleId): ?Level
    {
        return $this->levels[$roleId] ?? null;
    }

    public function prefers(int $roleId): bool
    {
        return in_array($roleId, $this->preferredRoleIds, true);
    }
}
