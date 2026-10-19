<?php

namespace App\Actions\Volunteers;

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Domain\Scheduling\Violation;
use App\Enums\Frequency;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\AvailabilityConflict;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Saves a volunteer's weekly availability and frequency (FR-005). Applies to schedules
 * not yet published; future published shifts that now clash with their availability are
 * flagged, never removed (frequency is only a preference, so it never clashes),
 * and coordinators are told (spec edge case).
 */
class UpdateAvailability
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
    ) {}

    /**
     * @param  list<array{weekday: int, period: string}>  $slots
     * @return int published shifts now in conflict
     */
    public function handle(Volunteer $volunteer, array $slots, Frequency $maxFrequency, bool $variableHours): int
    {
        DB::transaction(function () use ($volunteer, $slots, $maxFrequency, $variableHours): void {
            $volunteer->max_frequency = $maxFrequency;
            $volunteer->variable_hours = $variableHours;
            $volunteer->save();

            $volunteer->availabilities()->delete();

            foreach (collect($slots)->unique(fn (array $slot) => $slot['weekday'].'|'.$slot['period']) as $slot) {
                $volunteer->availabilities()->create($slot);
            }
        });

        return $this->flagConflicts($volunteer->fresh(['skills', 'availabilities']) ?? $volunteer);
    }

    private function flagConflicts(Volunteer $volunteer): int
    {
        $shifts = $volunteer->assignments()
            ->active()
            ->whereNull('conflict_flagged_at')
            ->whereHas('schedule', fn ($query) => $query->published())
            ->whereHas('event', fn ($query) => $query->scheduled()->where('date', '>=', LocalTime::today()->toDateString()))
            ->with(['event', 'position', 'schedule'])
            ->get();

        $relevant = [Violation::Availability];
        $profile = $this->loader->profileOf($volunteer);
        $flagged = $shifts->filter(function (Assignment $assignment) use ($volunteer, $profile, $relevant): bool {
            $event = $assignment->event;
            $context = $this->loader->forPeriod($event->date, $event->date, $volunteer);
            $context->remove($volunteer->id, $event->id);
            $violations = $this->checker->check($profile, $this->loader->slotFor($assignment->position, $event), $context, automatic: false);

            return array_intersect(Violation::values($violations), Violation::values($relevant)) !== [];
        });

        if ($flagged->isEmpty()) {
            return 0;
        }

        $flagged->each(function (Assignment $assignment): void {
            $assignment->conflict_flagged_at = now();
            $assignment->save();
        });

        Notification::send(
            User::query()->where('role', UserRole::Coordinator)->get(),
            new AvailabilityConflict($volunteer->shortName(), $flagged->count(), $flagged->firstOrFail()->schedule_id),
        );

        return $flagged->count();
    }
}
