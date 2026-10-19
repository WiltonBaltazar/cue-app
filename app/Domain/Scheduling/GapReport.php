<?php

namespace App\Domain\Scheduling;

/**
 * Positions automatic generation could not fill, with the closest candidates and the
 * rules each would break (US3-6). Never filled by breaking a rule (SC-003).
 */
final class GapReport
{
    /** @var array<int, array{slot: Slot, missing: int, suggestions: list<array{volunteerId: int, violations: list<Violation>}>}> */
    private array $gaps = [];

    /**
     * @param  list<array{volunteerId: int, violations: list<Violation>}>  $suggestions
     */
    public function add(Slot $slot, array $suggestions): void
    {
        if (isset($this->gaps[$slot->positionId])) {
            $this->gaps[$slot->positionId]['missing']++;

            return;
        }

        $this->gaps[$slot->positionId] = ['slot' => $slot, 'missing' => 1, 'suggestions' => $suggestions];
    }

    public function isEmpty(): bool
    {
        return $this->gaps === [];
    }

    public function missingCount(): int
    {
        return array_sum(array_column($this->gaps, 'missing'));
    }

    /**
     * @return list<array{slot: Slot, missing: int, suggestions: list<array{volunteerId: int, violations: list<Violation>}>}>
     */
    public function all(): array
    {
        return array_values($this->gaps);
    }

    /**
     * JSON-safe shape stored on the schedule.
     *
     * @return list<array{positionId: int, eventId: int, label: string, missing: int, suggestions: list<array{volunteerId: int, violations: list<string>}>}>
     */
    public function toArray(): array
    {
        return array_map(fn (array $gap): array => [
            'positionId' => $gap['slot']->positionId,
            'eventId' => $gap['slot']->eventId,
            'label' => $gap['slot']->label,
            'missing' => $gap['missing'],
            'suggestions' => array_map(fn (array $suggestion): array => [
                'volunteerId' => $suggestion['volunteerId'],
                'violations' => Violation::values($suggestion['violations']),
            ], $gap['suggestions']),
        ], $this->all());
    }
}
