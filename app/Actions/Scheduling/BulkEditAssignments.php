<?php

namespace App\Actions\Scheduling;

use App\Domain\Scheduling\Booking;
use App\Domain\Scheduling\ScheduleGenerator;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Models\Assignment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AssignmentChanged;
use App\Support\ShiftDescription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acts on several assignments of one schedule at once (FR-013): remove them, let the
 * generator put someone else in each place, or pin them so "Gerar de novo" keeps them.
 * All or nothing: one outdated assignment refuses the whole change. In a published
 * schedule everyone affected is told, as with single edits.
 */
class BulkEditAssignments
{
    public function __construct(
        private ConcurrencyGuard $guard,
        private RemoveAssignment $remove,
        private SchedulingContextLoader $loader,
        private ScheduleGenerator $generator,
    ) {}

    /**
     * @param  array<int, int>  $versions  assignment id => lock version seen by the editor
     */
    public function remove(Schedule $schedule, array $versions): int
    {
        $assignments = $this->load($schedule, $versions);

        DB::transaction(fn () => $assignments->each(fn (Assignment $assignment) => $this->remove->handle($assignment, $versions[$assignment->id])));

        return $assignments->count();
    }

    /**
     * @param  array<int, int>  $versions
     */
    public function setLocked(Schedule $schedule, array $versions, bool $locked, User $coordinator): int
    {
        $assignments = $this->load($schedule, $versions);

        DB::transaction(fn () => $assignments->each(function (Assignment $assignment) use ($locked, $coordinator): void {
            $assignment->is_locked = $locked;
            $assignment->updated_by = $coordinator->id;
            $assignment->save();
        }));

        return $assignments->count();
    }

    /**
     * Frees each place and lets the generator fill it with someone else; the person taken
     * off is never put back on the same event.
     *
     * @param  array<int, int>  $versions
     * @return array{replaced: int, missing: int}
     */
    public function autoReplace(Schedule $schedule, array $versions, User $coordinator): array
    {
        $assignments = $this->load($schedule, $versions)->load(['event', 'position.teamRole', 'volunteer.user']);

        if ($assignments->contains(fn (Assignment $assignment) => $assignment->event->isCancelled())) {
            throw ValidationException::withMessages(['assignments' => 'Não é possível substituir em eventos cancelados.']);
        }

        $published = $schedule->isPublished();
        $removed = [];

        $created = DB::transaction(function () use ($schedule, $assignments, $coordinator, &$removed): array {
            $slots = [];

            foreach ($assignments as $assignment) {
                $slot = $this->loader->slotFor($assignment->position, $assignment->event, 1);
                $slots[] = $slot;
                $removed[] = ['volunteer' => $assignment->volunteer, 'shift' => ShiftDescription::of($assignment), 'slot' => $slot];
                $assignment->delete();
            }

            $context = $this->loader->forPeriod($schedule->starts_on, $schedule->ends_on);

            foreach ($removed as $entry) {
                $context->add(Booking::forSlot($entry['volunteer']->id, $entry['slot']));
            }

            $result = $this->generator->generate($context, $slots, $schedule->starts_on->toDateString(), $schedule->ends_on->toDateString());

            return array_map(function (array $proposal) use ($schedule, $coordinator): Assignment {
                $assignment = new Assignment([
                    'schedule_id' => $schedule->id,
                    'position_id' => $proposal['slot']->positionId,
                    'event_id' => $proposal['slot']->eventId,
                    'volunteer_id' => $proposal['volunteerId'],
                    'source' => Assignment::SOURCE_AUTO,
                    'is_locked' => false,
                ]);
                $assignment->updated_by = $coordinator->id;
                $assignment->save();

                return $assignment;
            }, $result['assignments']);
        });

        if ($published) {
            foreach ($removed as $entry) {
                $entry['volunteer']->user?->notify(new AssignmentChanged(false, $entry['shift']));
            }

            foreach ($created as $assignment) {
                $assignment->load(['event', 'position.teamRole', 'volunteer.user']);
                $assignment->volunteer->user?->notify(new AssignmentChanged(true, ShiftDescription::of($assignment)));
            }
        }

        return ['replaced' => count($created), 'missing' => $assignments->count() - count($created)];
    }

    /**
     * @param  array<int, int>  $versions
     * @return Collection<int, Assignment>
     */
    private function load(Schedule $schedule, array $versions): Collection
    {
        $assignments = $schedule->assignments()->active()->whereKey(array_keys($versions))->get();

        if ($assignments->count() !== count($versions)) {
            throw ValidationException::withMessages(['assignments' => 'Algumas atribuições já não existem nesta escala — recarregar.']);
        }

        $assignments->each(fn (Assignment $assignment) => $this->guard->ensureCurrent($assignment, $versions[$assignment->id]));

        return $assignments;
    }
}
