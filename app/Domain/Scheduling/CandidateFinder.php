<?php

namespace App\Domain\Scheduling;

use App\Models\Assignment;
use App\Models\Position;
use App\Models\Volunteer;

/**
 * Ranked candidates for a position, for the editor's picker and substitute suggestions
 * (FR-019): eligible people first, ordered like the generator, then the rest by how few
 * rules they would break.
 */
class CandidateFinder
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
        private CandidateScorer $scorer,
    ) {}

    /**
     * @return list<array{volunteerId: int, name: string, level: string|null, recentAssignments: int, eligible: bool, violations: list<string>}>
     */
    public function for(Position $position, ?Assignment $replacing = null): array
    {
        $event = $position->event;
        $context = $this->loader->forPeriod($event->date, $event->date);

        if ($replacing !== null) {
            $context->remove($replacing->volunteer_id, $replacing->event_id);
        }

        $slot = $this->loader->slotFor($position, $event);
        $windowFrom = $event->date->subDays(28)->toDateString();
        $windowTo = $event->date->toDateString();
        $names = Volunteer::query()->whereIn('id', array_keys($context->volunteers()))->pluck('full_name', 'id');

        $rows = [];

        foreach ($context->volunteers() as $volunteer) {
            if ($volunteer->levelIn($slot->roleId) === null) {
                continue;
            }

            $violations = $this->checker->check($volunteer, $slot, $context, automatic: false);

            $rows[] = [
                'profile' => $volunteer,
                'key' => [$violations === [] ? 0 : 1, count($violations), ...$this->scorer->key($volunteer, $slot, $context, $windowFrom, $windowTo)],
                'row' => [
                    'volunteerId' => $volunteer->id,
                    'name' => (string) ($names[$volunteer->id] ?? ''),
                    'level' => $volunteer->levelIn($slot->roleId)->label(),
                    'recentAssignments' => $context->loadOf($volunteer->id, $windowFrom, $windowTo),
                    'eligible' => $violations === [],
                    'violations' => Violation::values($violations),
                ],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        return array_column($rows, 'row');
    }
}
