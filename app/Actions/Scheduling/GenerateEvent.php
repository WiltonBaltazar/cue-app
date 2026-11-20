<?php

namespace App\Actions\Scheduling;

use App\Domain\Scheduling\ScheduleGenerator;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Notifications\AssignmentChanged;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proposes people for one event of a schedule, without touching the rest of it. On a draft
 * the event's automatic, unlocked assignments are redone; on a published schedule only the
 * empty places are filled and the people placed are told.
 */
class GenerateEvent
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ScheduleGenerator $generator,
    ) {}

    /**
     * @return array{created: int, missing: int}
     */
    public function handle(Schedule $schedule, Event $event): array
    {
        if ($event->isCancelled() || $event->starts_at->isPast()) {
            throw ValidationException::withMessages(['event' => 'Só pode gerar um evento que ainda vai acontecer.']);
        }

        $published = $schedule->isPublished();

        $created = DB::transaction(function () use ($schedule, $event, $published): array {
            if (! $published) {
                $schedule->assignments()
                    ->where('event_id', $event->id)
                    ->where('source', Assignment::SOURCE_AUTO)
                    ->where('is_locked', false)
                    ->delete();
            }

            $context = $this->loader->forPeriod($event->date, $event->date);
            $date = $event->date->toDateString();
            $result = $this->generator->generate($context, $this->loader->openSlots($schedule, $event), $date, $date);

            $created = [];

            foreach ($result['assignments'] as $assignment) {
                $created[] = $schedule->assignments()->create([
                    'position_id' => $assignment['slot']->positionId,
                    'event_id' => $assignment['slot']->eventId,
                    'volunteer_id' => $assignment['volunteerId'],
                    'source' => Assignment::SOURCE_AUTO,
                    'is_locked' => false,
                ]);
            }

            // Keep the other events' gaps; replace this event's.
            $schedule->generation_report = [
                ...array_values(array_filter(
                    $schedule->generation_report ?? [],
                    fn (array $gap): bool => $gap['eventId'] !== $event->id,
                )),
                ...$result['gaps']->toArray(),
            ];
            $schedule->save();

            return ['assignments' => $created, 'missing' => $result['gaps']->missingCount()];
        });

        if ($published) {
            foreach ($created['assignments'] as $assignment) {
                $assignment->loadMissing('volunteer.user');
                $assignment->volunteer->user?->notify(new AssignmentChanged(true, ShiftDescription::of($assignment)));
            }
        }

        return ['created' => count($created['assignments']), 'missing' => $created['missing']];
    }
}
