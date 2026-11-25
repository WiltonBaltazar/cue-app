<?php

namespace App\Actions\Events;

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Domain\Scheduling\Violation;
use App\Models\Assignment;
use App\Models\Event;
use App\Notifications\ShiftRescheduled;
use App\Support\ShiftDescription;
use Illuminate\Database\Eloquent\Collection;

/**
 * When an event with people changes date or times: rechecks whether they can come (a new
 * clash is flagged, never removed), lets reminders go out again for the new time, and tells
 * those in a published schedule.
 */
class RescheduleShifts
{
    private const RECHECKED = [Violation::Availability, Violation::Absence, Violation::TimeOverlap];

    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
    ) {}

    /**
     * Clashes of each shift as the event stands. Taken before the change, so a person placed
     * in spite of one (a coordinator's choice) is not flagged for it.
     *
     * @return array<int, list<string>> assignment id => violations
     */
    public function clashes(Event $event): array
    {
        $context = $this->loader->forPeriod($event->date, $event->date);
        $clashes = [];

        foreach ($this->shifts($event) as $assignment) {
            $violations = $this->checker->check(
                $this->loader->profileOf($assignment->volunteer),
                $this->loader->slotFor($assignment->position, $event),
                $context,
                automatic: false,
            );

            $clashes[$assignment->id] = array_values(array_intersect(Violation::values($violations), Violation::values(self::RECHECKED)));
        }

        return $clashes;
    }

    /**
     * Flags new clashes and clears the ones that are gone. Runs inside the edit's transaction.
     *
     * @param  array<int, list<string>>  $before
     */
    public function recheck(Event $event, array $before, bool $arrivalChanged): void
    {
        $after = $this->clashes($event);

        foreach ($this->shifts($event) as $assignment) {
            $clashes = $after[$assignment->id] ?? [];

            if ($clashes === []) {
                $assignment->conflict_flagged_at = null;
            } elseif (array_diff($clashes, $before[$assignment->id] ?? []) !== []) {
                $assignment->conflict_flagged_at ??= now()->toImmutable();
            }

            if ($arrivalChanged) {
                $assignment->check_sent_at = null;
                $assignment->reminder_sent_at = null;
            }

            $assignment->save();
        }
    }

    public function notify(Event $event): void
    {
        if ($event->starts_at->isPast()) {
            return;
        }

        $this->shifts($event)
            ->filter(fn (Assignment $assignment): bool => $assignment->schedule->isPublished())
            ->each(fn (Assignment $assignment) => $assignment->volunteer->user?->notify(
                new ShiftRescheduled($assignment->id, ShiftDescription::of($assignment)),
            ));
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function shifts(Event $event): Collection
    {
        return $event->assignments()->active()
            ->with(['schedule', 'position.teamRole', 'volunteer.user', 'volunteer.skills', 'volunteer.availabilities'])
            ->get();
    }
}
