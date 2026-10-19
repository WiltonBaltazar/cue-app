<?php

namespace App\Actions\Scheduling;

use App\Enums\AssignmentStatus;
use App\Enums\ScheduleStatus;
use App\Models\Assignment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\SchedulePublished;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a schedule (US3-7): shifts become visible and confirmed, and each assigned
 * volunteer is notified once with their shift count and first shift.
 */
class PublishSchedule
{
    /**
     * @return int volunteers notified
     */
    public function handle(Schedule $schedule, User $coordinator): int
    {
        if ($schedule->isPublished()) {
            throw ValidationException::withMessages(['schedule' => 'Esta escala já foi publicada.']);
        }

        DB::transaction(function () use ($schedule, $coordinator): void {
            $schedule->assignments()->update(['status' => AssignmentStatus::Confirmed]);

            $schedule->status = ScheduleStatus::Published;
            $schedule->published_by = $coordinator->id;
            $schedule->published_at = now();
            $schedule->save();
        });

        $byVolunteer = $schedule->assignments()
            ->active()
            ->with(['event', 'position.teamRole', 'volunteer.user'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp())
            ->groupBy('volunteer_id');

        foreach ($byVolunteer as $assignments) {
            /** @var Assignment $first */
            $first = $assignments->first();
            $first->volunteer->user?->notify(new SchedulePublished($assignments->count(), ShiftDescription::of($first)));
        }

        return $byVolunteer->count();
    }
}
