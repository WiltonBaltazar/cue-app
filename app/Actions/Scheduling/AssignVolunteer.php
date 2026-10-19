<?php

namespace App\Actions\Scheduling;

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Domain\Scheduling\Violation;
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
 * so two coordinators do not overwrite each other.
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

    public function handle(Position $position, Volunteer $volunteer, User $coordinator, ?Assignment $replacing = null, ?int $lockVersion = null, ?string $overrideNote = null): Assignment
    {
        if ($replacing !== null) {
            $this->guard->ensureCurrent($replacing, $lockVersion);
        }

        $schedule = $this->scheduleFor($position);
        // Going over someone's declared frequency is only a warning; hard rules need a reason.
        $violations = Violation::hard($this->violations($position, $volunteer, $replacing));

        if ($violations !== [] && blank($overrideNote)) {
            throw ValidationException::withMessages([
                'override_note' => 'Esta escolha quebra regras da escala. Indique o motivo para avançar.',
                'violations' => array_map(fn (Violation $violation): string => $violation->message(), $violations),
            ]);
        }

        if ($replacing === null && $position->assignments()->active()->count() >= $position->quantity) {
            throw ValidationException::withMessages(['position_id' => 'Esta posição já está completa.']);
        }

        $previousVolunteer = $replacing?->volunteer;

        $assignment = DB::transaction(function () use ($position, $volunteer, $coordinator, $replacing, $schedule, $violations, $overrideNote): Assignment {
            $assignment = $replacing ?? new Assignment([
                'schedule_id' => $schedule->id,
                'position_id' => $position->id,
                'event_id' => $position->event_id,
            ]);

            $assignment->fill([
                'volunteer_id' => $volunteer->id,
                'source' => Assignment::SOURCE_MANUAL,
                'is_locked' => true,
                'override_note' => $violations === [] ? null : $overrideNote,
            ]);
            $assignment->conflict_flagged_at = null;
            $assignment->updated_by = $coordinator->id;
            $assignment->save();

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
        $date = $position->event->date->toDateString();

        $schedule = Schedule::query()
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->first();

        if ($schedule === null) {
            throw ValidationException::withMessages(['schedule' => 'Crie primeiro a escala que cobre esta data.']);
        }

        return $schedule;
    }
}
