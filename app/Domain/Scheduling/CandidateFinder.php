<?php

namespace App\Domain\Scheduling;

use App\Models\Assignment;
use App\Models\Position;
use App\Models\Volunteer;
use Illuminate\Support\Str;

/**
 * Ranked candidates for a position, for the editor's picker and substitute suggestions
 * (FR-019): eligible people first (those within their frequency before those over it),
 * ordered like the generator, then the rest by how few rules they would break. Active
 * people without the role come apart, by name. People already on this event, and the
 * person being replaced, are left out.
 */
class CandidateFinder
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
        private CandidateScorer $scorer,
    ) {}

    /**
     * @return array{
     *     candidates: list<array{volunteerId: int, name: string, level: string|null, recentAssignments: int, eligible: bool, violations: list<string>}>,
     *     others: list<array{volunteerId: int, name: string, level: string|null, recentAssignments: int, eligible: bool, violations: list<string>}>,
     * }
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
        $others = [];

        foreach ($context->volunteers() as $volunteer) {
            if ($volunteer->id === $replacing?->volunteer_id) {
                continue;
            }

            $violations = $this->checker->check($volunteer, $slot, $context, automatic: false);

            if (in_array(Violation::SameEvent, $violations, true)) {
                continue;
            }

            $level = $volunteer->levelIn($slot->roleId);
            $eligible = Violation::hard($violations) === [];
            $row = [
                'volunteerId' => $volunteer->id,
                'name' => (string) ($names[$volunteer->id] ?? ''),
                'level' => $level?->label(),
                'recentAssignments' => $context->loadOf($volunteer->id, $windowFrom, $windowTo),
                'eligible' => $eligible,
                'violations' => Violation::values($violations),
            ];

            if ($level === null) {
                $others[] = $row;

                continue;
            }

            $rows[] = [
                'key' => [$eligible ? 0 : 1, count($violations), ...$this->scorer->key($volunteer, $slot, $context, $windowFrom, $windowTo)],
                'row' => $row,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        usort($others, fn (array $a, array $b): int => Str::ascii($a['name']) <=> Str::ascii($b['name']));

        return ['candidates' => array_column($rows, 'row'), 'others' => $others];
    }
}
