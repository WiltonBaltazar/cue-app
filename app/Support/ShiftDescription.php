<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Event;

/**
 * Short shift text for notifications and lists, e.g.
 * "Dom 2/11 · Culto de Domingo · Áudio · chegada 07:30". No personal data (Principle V).
 */
class ShiftDescription
{
    private const WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    public static function of(Assignment $assignment): string
    {
        $assignment->loadMissing(['event', 'position.teamRole']);

        return self::event($assignment->event).' · '.$assignment->position->teamRole->name
            .' · chegada '.LocalTime::timeOf($assignment->event->arrival_at);
    }

    public static function event(Event $event): string
    {
        return self::WEEKDAYS[$event->date->dayOfWeekIso].' '.$event->date->format('j/n').' · '.$event->name;
    }
}
