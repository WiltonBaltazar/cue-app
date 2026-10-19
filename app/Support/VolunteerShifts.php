<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\Volunteer;

/**
 * A volunteer's own upcoming shifts in published schedules: confirmed ones and those
 * waiting for a substitute. Declined/handed-over shifts are not listed.
 */
class VolunteerShifts
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function upcoming(Volunteer $volunteer, ?int $limit = null): array
    {
        $shifts = $volunteer->assignments()
            ->whereIn('status', [AssignmentStatus::Confirmed, AssignmentStatus::NeedsReplacement])
            ->whereHas('schedule', fn ($query) => $query->published())
            ->whereHas('event', fn ($query) => $query->where('date', '>=', LocalTime::today()->toDateString()))
            ->with(['event', 'position.teamRole'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp());

        if ($limit !== null) {
            $shifts = $shifts->take($limit);
        }

        $withSwap = SwapRequest::query()
            ->active()
            ->whereIn('assignment_id', $shifts->modelKeys())
            ->pluck('assignment_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values($shifts->map(fn (Assignment $assignment) => [
            'id' => $assignment->id,
            'scheduleId' => $assignment->schedule_id,
            'eventId' => $assignment->event_id,
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
            // Decline or ask for a swap (US4): own, confirmed, upcoming, no request yet.
            'canAct' => $assignment->status === AssignmentStatus::Confirmed
                && ! $assignment->event->isCancelled()
                && $assignment->event->starts_at->isFuture()
                && ! in_array($assignment->id, $withSwap, true),
            'hasActiveSwap' => in_array($assignment->id, $withSwap, true),
        ])->all());
    }

    /**
     * Who serves with the volunteer at that event, in short names only (FR-027). Places
     * waiting for a substitute are counted without saying who stepped out.
     *
     * @return array{people: list<array{name: string, role: string, isMe: bool}>, needsReplacement: int}
     */
    public static function team(int $scheduleId, int $eventId, int $volunteerId): array
    {
        $assignments = Assignment::query()
            ->where('schedule_id', $scheduleId)
            ->where('event_id', $eventId)
            ->whereIn('status', [AssignmentStatus::Confirmed, AssignmentStatus::NeedsReplacement])
            ->with(['volunteer', 'position.teamRole'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => [$assignment->volunteer_id !== $volunteerId, $assignment->position->teamRole->name]);

        return [
            'people' => array_values($assignments
                ->where('status', AssignmentStatus::Confirmed)
                ->map(fn (Assignment $assignment) => [
                    'name' => $assignment->volunteer->shortName(),
                    'role' => $assignment->position->teamRole->name,
                    'isMe' => $assignment->volunteer_id === $volunteerId,
                ])->all()),
            'needsReplacement' => $assignments->where('status', AssignmentStatus::NeedsReplacement)->count(),
        ];
    }
}
