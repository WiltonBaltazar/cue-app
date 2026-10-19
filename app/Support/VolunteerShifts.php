<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Volunteer;

/**
 * A volunteer's own upcoming shifts in published schedules.
 */
class VolunteerShifts
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function upcoming(Volunteer $volunteer, ?int $limit = null): array
    {
        $shifts = $volunteer->assignments()
            ->whereHas('schedule', fn ($query) => $query->published())
            ->whereHas('event', fn ($query) => $query->where('date', '>=', LocalTime::today()->toDateString()))
            ->with(['event', 'position.teamRole'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp());

        if ($limit !== null) {
            $shifts = $shifts->take($limit);
        }

        return array_values($shifts->map(fn (Assignment $assignment) => [
            'id' => $assignment->id,
            'scheduleId' => $assignment->schedule_id,
            'date' => $assignment->event->date->toDateString(),
            'eventName' => $assignment->event->name,
            'role' => $assignment->position->teamRole->name,
            'arrivalTime' => LocalTime::timeOf($assignment->event->arrival_at),
            'startTime' => LocalTime::timeOf($assignment->event->starts_at),
            'endTime' => LocalTime::timeOf($assignment->event->ends_at),
            'location' => $assignment->event->location,
            'status' => $assignment->status->value,
            'statusLabel' => $assignment->status->label(),
            'eventCancelled' => $assignment->event->isCancelled(),
        ])
            ->all());
    }
}
