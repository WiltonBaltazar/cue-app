<?php

namespace App\Actions\Scheduling;

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Domain\Scheduling\Violation;
use App\Enums\Level;
use App\Models\Assignment;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\AssignmentChanged;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual assignment by a coordinator (FR-013): to an open place or replacing someone.
 * Breaking a hard rule needs an explicit note and is audited; a stale version is refused
 * so two coordinators do not overwrite each other. Someone without the role can be given it
 * at the same time, as a beginner, so generation finds them from then on.
 */
class AssignVolunteer
{
    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
        private ConcurrencyGuard $guard,
    ) {}

    /**
     * @return list<Violation>
     */
    public function violations(Position $position, Volunteer $volunteer, ?Assignment $replacing = null): array
    {
        $event = $position->event;
        $context = $this->loader->forPeriod($event->date, $event->date, $volunteer);

        if ($replacing !== null) {
            $context->remove($replacing->volunteer_id, $replacing->event_id);
        }

        return $this->checker->check($this->loader->profileOf($volunteer), $this->loader->slotFor($position), $context, automatic: false);
    }

    public function handle(Position $position, Volunteer $volunteer, User $coordinator, ?Assignment $replacing = null, ?int $lockVersion = null, ?string $overrideNote = null, bool $addRole = false): Assignment
    {
        if ($replacing !== null) {
            $this->guard->ensureCurrent($replacing, $lockVersion);
        }

        $schedule = $this->scheduleFor($position);
        // The coordinator decides: broken rules are only warnings and a note is optional; the
        // volunteer says if they can not go. Only a second place at the same event is refused.
        $violations = $this->violations($position, $volunteer, $replacing);

        if (in_array(Violation::SameEvent, $violations, true)) {
            throw ValidationException::withMessages(['volunteer_id' => Violation::SameEvent->message().'.']);
        }

        if ($replacing === null && $position->assignments()->active()->count() >= $position->quantity) {
            throw ValidationException::withMessages(['position_id' => 'Esta posição já está completa.']);
        }

        $previousVolunteer = $replacing?->volunteer;

        $assignment = DB::transaction(function () use ($position, $volunteer, $coordinator, $replacing, $schedule, $violations, $overrideNote, $addRole): Assignment {
            $assignment = $replacing ?? new Assignment([
                'schedule_id' => $schedule->id,
                'position_id' => $position->id,
                'event_id' => $position->event_id,
            ]);

            $assignment->fill([
                'volunteer_id' => $volunteer->id,
                'source' => Assignment::SOURCE_MANUAL,
                'is_locked' => true,
                'override_note' => $violations === [] || blank($overrideNote) ? null : $overrideNote,
            ]);
            $assignment->conflict_flagged_at = null;
            $assignment->updated_by = $coordinator->id;
            $assignment->save();

            if ($addRole && in_array(Violation::Role, $violations, true)) {
                $volunteer->skills()->create([
                    'team_role_id' => $position->team_role_id,
                    'interested' => false,
                    'has_experience' => false,
                    'level' => Level::Beginner,
                ]);
            }

            return $assignment;
        });

        if ($schedule->isPublished()) {
            $shift = ShiftDescription::of($assignment);
            $volunteer->user?->notify(new AssignmentChanged(true, $shift));

            if ($previousVolunteer !== null && $previousVolunteer->id !== $volunteer->id) {
                $previousVolunteer->user?->notify(new AssignmentChanged(false, $shift));
            }
        }

        return $assignment;
    }

    private function scheduleFor(Position $position): Schedule
    {
        $schedule = Schedule::forEvent($position->event);

        if ($schedule === null) {
            throw ValidationException::withMessages(['schedule' => 'Crie primeiro a escala que cobre esta data.']);
        }

        return $schedule;
    }
}
