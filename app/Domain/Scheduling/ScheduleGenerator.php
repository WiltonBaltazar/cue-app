<?php

namespace App\Domain\Scheduling;

/**
 * Proposes assignments for a period (US3, FR-010–FR-012).
 *
 * Slots are filled scarcest-first (fewest eligible people), each by the best-ranked
 * candidate that breaks no hard rule. People within their declared frequency come first;
 * only when none is left does it go over someone's frequency, "Ocasionalmente" last, so
 * positions are filled whenever anyone can come. Bookings already in the context (locked
 * or manual assignments, other schedules) are respected. Same input → same output.
 */
final class ScheduleGenerator
{
    private const SUGGESTIONS = 3;

    private const RECENT_DAYS = 28;

    public function __construct(
        private ConstraintChecker $checker = new ConstraintChecker,
        private CandidateScorer $scorer = new CandidateScorer,
    ) {}

    /**
     * @param  list<Slot>  $slots  quantity = people still needed in each
     * @return array{assignments: list<array{slot: Slot, volunteerId: int}>, gaps: GapReport}
     */
    public function generate(SchedulingContext $context, array $slots, string $periodStart, string $periodEnd): array
    {
        $windowFrom = date('Y-m-d', (int) strtotime("{$periodStart} -".self::RECENT_DAYS.' days'));
        $units = $this->orderByScarcity($this->expand($slots), $context);

        $assignments = [];
        $gaps = new GapReport;

        foreach ($units as $slot) {
            $eligible = $this->eligibleFor($slot, $context);

            if ($eligible === []) {
                $gaps->add($slot, $this->suggestionsFor($slot, $context));

                continue;
            }

            $chosen = $this->scorer->rank($eligible, $slot, $context, $windowFrom, $periodEnd)[0];
            $context->add(Booking::forSlot($chosen->id, $slot));
            $assignments[] = ['slot' => $slot, 'volunteerId' => $chosen->id];
        }

        return ['assignments' => $assignments, 'gaps' => $gaps];
    }

    /**
     * Candidates breaking no hard rule, from the most preferred group that has anyone:
     * within their frequency, then over it, then "Ocasionalmente".
     *
     * @return list<VolunteerProfile>
     */
    private function eligibleFor(Slot $slot, SchedulingContext $context): array
    {
        $tiers = [[], [], []];

        foreach ($context->volunteers() as $volunteer) {
            $violations = $this->checker->check($volunteer, $slot, $context);

            if (Violation::hard($violations) !== []) {
                continue;
            }

            $tier = match (true) {
                $violations === [] => 0,
                $volunteer->frequency->allowsAutomaticAssignment() => 1,
                default => 2,
            };
            $tiers[$tier][] = $volunteer;
        }

        foreach ($tiers as $tier) {
            if ($tier !== []) {
                return $tier;
            }
        }

        return [];
    }

    /**
     * One unit per person needed.
     *
     * @param  list<Slot>  $slots
     * @return list<Slot>
     */
    private function expand(array $slots): array
    {
        $units = [];

        foreach ($slots as $slot) {
            for ($i = 0; $i < $slot->quantity; $i++) {
                $units[] = $slot;
            }
        }

        return $units;
    }

    /**
     * @param  list<Slot>  $units
     * @return list<Slot>
     */
    private function orderByScarcity(array $units, SchedulingContext $context): array
    {
        $supply = [];

        foreach ($units as $slot) {
            $supply[$slot->positionId] ??= count(array_filter(
                $context->volunteers(),
                fn (VolunteerProfile $volunteer): bool => Violation::hard($this->checker->check($volunteer, $slot, $context)) === [],
            ));
        }

        usort($units, fn (Slot $a, Slot $b): int => [$supply[$a->positionId], $a->date, $a->busyFrom, $a->roleId, $a->positionId]
            <=> [$supply[$b->positionId], $b->date, $b->busyFrom, $b->roleId, $b->positionId]);

        return $units;
    }

    /**
     * Closest candidates: fewest broken rules, people who at least have the role first.
     *
     * @return list<array{volunteerId: int, violations: list<Violation>}>
     */
    private function suggestionsFor(Slot $slot, SchedulingContext $context): array
    {
        $candidates = [];

        foreach ($context->volunteers() as $volunteer) {
            $violations = $this->checker->check($volunteer, $slot, $context);

            if ($violations !== [] && ! in_array(Violation::SameEvent, $violations, true)) {
                $candidates[] = ['volunteerId' => $volunteer->id, 'violations' => $violations];
            }
        }

        usort($candidates, fn (array $a, array $b): int => [in_array(Violation::Role, $a['violations'], true), count($a['violations']), $a['volunteerId']]
            <=> [in_array(Violation::Role, $b['violations'], true), count($b['violations']), $b['volunteerId']]);

        return array_slice($candidates, 0, self::SUGGESTIONS);
    }
}
