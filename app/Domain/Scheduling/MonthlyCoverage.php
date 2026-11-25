<?php

namespace App\Domain\Scheduling;

/**
 * Who can still serve at least once in one calendar month (FR-012). Tracks the people without
 * a shift that month and the places left, and tells how many of them can all get one
 * (maximum bipartite matching), so the generator does not use up the only place someone
 * could take. Counts only places they fit without breaking any rule.
 */
final class MonthlyCoverage
{
    /** @var array<int, list<int>> volunteer id => units they can take */
    private array $options = [];

    private int $coverable;

    /**
     * @param  array<int, Slot>  $units  one per person needed, all in the same month
     */
    public function __construct(private array $units, SchedulingContext $context, ConstraintChecker $checker)
    {
        foreach ($context->volunteers() as $volunteer) {
            if ($units === [] || $context->servesInMonth($volunteer->id, reset($units)->month())) {
                continue;
            }

            $byPosition = [];
            foreach ($units as $unit => $slot) {
                if ($byPosition[$slot->positionId] ??= $checker->check($volunteer, $slot, $context) === []) {
                    $this->options[$volunteer->id][] = $unit;
                }
            }
        }

        $this->coverable = $this->count($this->options, $units);
    }

    /**
     * Whether everyone who could still get a place this month still can after this choice.
     */
    public function allows(int $volunteerId, int $unit): bool
    {
        $gained = isset($this->options[$volunteerId]) ? 1 : 0;

        return $this->countAfter($volunteerId, $unit) + $gained >= $this->coverable;
    }

    public function take(int $volunteerId, int $unit): void
    {
        $this->coverable = $this->countAfter($volunteerId, $unit);
        unset($this->options[$volunteerId], $this->units[$unit]);
    }

    /**
     * A place left empty.
     */
    public function skip(int $unit): void
    {
        unset($this->units[$unit]);
        $this->coverable = $this->count($this->options, $this->units);
    }

    private function countAfter(int $volunteerId, int $unit): int
    {
        $options = $this->options;
        $units = $this->units;
        unset($options[$volunteerId], $units[$unit]);

        return $this->count($options, $units);
    }

    /**
     * @param  array<int, list<int>>  $options
     * @param  array<int, Slot>  $units
     */
    private function count(array $options, array $units): int
    {
        $holders = [];
        $count = 0;

        foreach (array_keys($options) as $volunteerId) {
            $seen = [];
            $count += $this->place($volunteerId, $options, $units, $holders, $seen) ? 1 : 0;
        }

        return $count;
    }

    /**
     * @param  array<int, list<int>>  $options
     * @param  array<int, Slot>  $units
     * @param  array<int, int>  $holders  unit => volunteer id
     * @param  array<int, true>  $seen
     */
    private function place(int $volunteerId, array $options, array $units, array &$holders, array &$seen): bool
    {
        foreach ($options[$volunteerId] as $unit) {
            if (! isset($units[$unit]) || isset($seen[$unit])) {
                continue;
            }

            $seen[$unit] = true;

            if (! isset($holders[$unit]) || $this->place($holders[$unit], $options, $units, $holders, $seen)) {
                $holders[$unit] = $volunteerId;

                return true;
            }
        }

        return false;
    }
}
