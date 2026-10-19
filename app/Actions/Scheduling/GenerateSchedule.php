<?php

namespace App\Actions\Scheduling;

use App\Domain\Scheduling\ScheduleGenerator;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Models\Assignment;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proposes assignments for a draft schedule (US3). Regenerating replaces only automatic,
 * unlocked assignments; manual edits survive.
 */
class GenerateSchedule
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ScheduleGenerator $generator,
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
            $schedule->assignments()
                ->where('source', Assignment::SOURCE_AUTO)
                ->where('is_locked', false)
                ->delete();

            $context = $this->loader->forPeriod($schedule->starts_on, $schedule->ends_on);
            $result = $this->generator->generate(
                $context,
                $this->loader->openSlots($schedule),
                $schedule->starts_on->toDateString(),
                $schedule->ends_on->toDateString(),
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
