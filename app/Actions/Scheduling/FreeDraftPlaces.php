<?php

namespace App\Actions\Scheduling;

use App\Enums\ScheduleStatus;
use App\Models\Volunteer;
use App\Support\LocalTime;

/**
 * Draft schedules are only plans: takes someone who is leaving out of the upcoming ones,
 * so the places are open again. Published shifts are the ReplacementFlagger's job.
 */
class FreeDraftPlaces
{
    public function handle(Volunteer $volunteer): void
    {
        $volunteer->assignments()
            ->whereHas('schedule', fn ($query) => $query->where('status', ScheduleStatus::Draft))
            ->whereHas('event', fn ($query) => $query->where('date', '>=', LocalTime::today()->toDateString()))
            ->delete();
    }
}
