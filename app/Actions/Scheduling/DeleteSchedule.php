<?php

namespace App\Actions\Scheduling;

use App\Models\Schedule;
use App\Notifications\ScheduleWithdrawn;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a whole schedule with its assignments (and their swap requests). If it was
 * published, everyone with an upcoming shift in it is told once; nothing changes silently.
 * The deletion itself is audited through the Schedule activity log.
 */
class DeleteSchedule
{
    /**
     * @return int volunteers notified
     */
    public function handle(Schedule $schedule): int
    {
        $upcoming = $schedule->isPublished()
            ? $schedule->assignments()
                ->active()
                ->whereHas('event', fn ($query) => $query->scheduled()->where('date', '>=', LocalTime::today()->toDateString()))
                ->with('volunteer.user')
                ->get()
                ->groupBy('volunteer_id')
            : collect();

        $period = $schedule->starts_on->format('d/m').'–'.$schedule->ends_on->format('d/m');

        DB::transaction(fn () => $schedule->delete());

        foreach ($upcoming as $shifts) {
            $shifts->first()?->volunteer->user?->notify(new ScheduleWithdrawn($period, $shifts->count()));
        }

        return $upcoming->count();
    }

    /**
     * Upcoming shifts and distinct people that deleting would affect, for the confirmation.
     *
     * @return array{shifts: int, people: int, past: int}
     */
    public function impact(Schedule $schedule): array
    {
        $today = LocalTime::today()->toDateString();
        $active = $schedule->assignments()->active();

        $upcoming = (clone $active)->whereHas('event', fn ($query) => $query->where('date', '>=', $today));

        return [
            'shifts' => $schedule->isPublished() ? (clone $upcoming)->count() : 0,
            'people' => $schedule->isPublished() ? (clone $upcoming)->distinct()->count('volunteer_id') : 0,
            'past' => $schedule->isPublished() ? (clone $active)->whereHas('event', fn ($query) => $query->where('date', '<', $today))->count() : 0,
        ];
    }
}
