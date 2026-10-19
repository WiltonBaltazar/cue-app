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
    ) {}

    public function isAvailable(int $weekday, string $period): bool
    {
        return $this->variableHours || isset($this->availability["{$weekday}|{$period}"]);
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
