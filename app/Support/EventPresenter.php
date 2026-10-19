<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Position;

/**
 * Event data for coordinator pages, with times converted back to local "H:i".
 */
class EventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type->value,
            'date' => $event->date->toDateString(),
            'arrivalTime' => LocalTime::timeOf($event->arrival_at),
            'startTime' => LocalTime::timeOf($event->starts_at),
            'endTime' => LocalTime::timeOf($event->ends_at),
            'location' => $event->location,
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'isCustomized' => $event->is_customized,
            'requiredPeople' => $event->requiredPeople(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function full(Event $event): array
    {
        return [
            ...self::summary($event),
            'teardownMinutes' => (int) $event->ends_at->diffInMinutes($event->teardown_until),
            'periodLabel' => $event->period->label(),
            'typeLabel' => $event->type->label(),
            'templateId' => $event->activity_template_id,
            'cancelReason' => $event->cancel_reason,
            'positions' => $event->positions->map(fn (Position $position) => [
                'teamRoleId' => $position->team_role_id,
                'name' => $position->teamRole->name,
                'quantity' => $position->quantity,
                'minLevel' => $position->min_level?->value,
                'minLevelLabel' => $position->min_level?->label(),
            ])->values(),
        ];
    }
}
