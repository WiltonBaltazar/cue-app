<?php

namespace App\Domain\Overview;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Position;
use Carbon\CarbonImmutable;

/**
 * Filled vs required places for each scheduled event in a date range, with the roles still
 * missing people (US5-2). A shift "por substituir" counts as a gap until someone takes it.
 */
final class CoverageReport
{
    /**
     * @return list<array{
     *     eventId: int,
     *     name: string,
     *     date: string,
     *     startsAt: string,
     *     scheduleId: int|null,
     *     published: bool,
     *     filled: int,
     *     required: int,
     *     needsReplacement: int,
     *     gaps: list<array{role: string, missing: int}>,
     * }>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(Event::query()
            ->scheduled()
            ->betweenDates($from, $to)
            ->with(['positions.teamRole', 'assignments.schedule'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event) => $this->forEvent($event))
            ->all());
    }

    /**
     * Only the events with at least one empty place.
     *
     * @return list<array{
     *     eventId: int,
     *     name: string,
     *     date: string,
     *     startsAt: string,
     *     scheduleId: int|null,
     *     published: bool,
     *     filled: int,
     *     required: int,
     *     needsReplacement: int,
     *     gaps: list<array{role: string, missing: int}>,
     * }>
     */
    public function gapsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter(
            $this->between($from, $to),
            fn (array $row) => $row['filled'] < $row['required'],
        ));
    }

    /**
     * @return array{
     *     eventId: int,
     *     name: string,
     *     date: string,
     *     startsAt: string,
     *     scheduleId: int|null,
     *     published: bool,
     *     filled: int,
     *     required: int,
     *     needsReplacement: int,
     *     gaps: list<array{role: string, missing: int}>,
     * }
     */
    private function forEvent(Event $event): array
    {
        $filledByPosition = $event->assignments
            ->where('status', AssignmentStatus::Confirmed)
            ->countBy('position_id');

        $gaps = array_values($event->positions
            ->sortBy(fn (Position $position) => [$position->teamRole->sort_order, $position->teamRole->name])
            ->map(fn (Position $position) => [
                'role' => $position->teamRole->name,
                'missing' => max(0, $position->quantity - ($filledByPosition[$position->id] ?? 0)),
            ])
            ->filter(fn (array $gap) => $gap['missing'] > 0)
            ->groupBy('role')
            ->map(fn ($rows, string $role) => ['role' => $role, 'missing' => (int) $rows->sum('missing')])
            ->all());

        $required = $event->requiredPeople();
        /** @var Assignment|null $any */
        $any = $event->assignments->first();

        return [
            'eventId' => $event->id,
            'name' => $event->name,
            'date' => $event->date->toDateString(),
            'startsAt' => $event->starts_at->toIso8601ZuluString(),
            'scheduleId' => $any?->schedule_id,
            'published' => $any?->schedule->isPublished() ?? false,
            'filled' => $required - (int) array_sum(array_column($gaps, 'missing')),
            'required' => $required,
            'needsReplacement' => $event->assignments->where('status', AssignmentStatus::NeedsReplacement)->count(),
            'gaps' => $gaps,
        ];
    }
}
