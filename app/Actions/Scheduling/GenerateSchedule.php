<?php

namespace App\Actions\Scheduling;

use App\Actions\Events\GenerateOccurrences;
use App\Domain\Scheduling\CandidateScorer;
use App\Domain\Scheduling\ScheduleGenerator;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Models\Assignment;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proposes assignments for a draft schedule (US3). Regenerating replaces only automatic,
 * unlocked assignments; manual edits survive, and each slot steers away from the person it had
 * before. Recurring events the period still lacks (past
 * the horizon) are created first.
 */
class GenerateSchedule
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ScheduleGenerator $generator,
        private GenerateOccurrences $occurrences,
    ) {}

    /**
     * @return array{created: int, missing: int}
     */
    public function handle(Schedule $schedule): array
    {
        if ($schedule->isPublished()) {
            throw ValidationException::withMessages(['schedule' => 'Uma escala publicada não é gerada de novo. Edite as atribuições à mão.']);
        }

        return DB::transaction(function () use ($schedule): array {
            $previous = $schedule->assignments()
                ->where('source', Assignment::SOURCE_AUTO)
                ->where('is_locked', false);

            // "Gerar de novo" should give a different mix, not the same proposal again.
            $avoid = (clone $previous)->get(['event_id', 'position_id', 'volunteer_id'])
                ->mapWithKeys(fn (Assignment $assignment) => [CandidateScorer::avoidKey($assignment->event_id, $assignment->position_id, $assignment->volunteer_id) => true])
                ->all();

            $previous->delete();

            // After the automatic assignments are gone, so occurrences duplicating an event
            // added by hand are free to be removed.
            if (! $schedule->isForSingleEvent()) {
                $this->occurrences->fillBetween($schedule->starts_on, $schedule->ends_on);
            }

            $context = $this->loader->forPeriod($schedule->starts_on, $schedule->ends_on);
            $result = $this->generator->generate(
                $context,
                $this->loader->openSlots($schedule),
                $schedule->starts_on->toDateString(),
                $schedule->ends_on->toDateString(),
                $avoid,
            );

            foreach ($result['assignments'] as $assignment) {
                $schedule->assignments()->create([
                    'position_id' => $assignment['slot']->positionId,
                    'event_id' => $assignment['slot']->eventId,
                    'volunteer_id' => $assignment['volunteerId'],
                    'source' => Assignment::SOURCE_AUTO,
                    'is_locked' => false,
                ]);
            }

            $schedule->generation_report = $result['gaps']->toArray();
            $schedule->save();

            return ['created' => count($result['assignments']), 'missing' => $result['gaps']->missingCount()];
        });
    }
}
