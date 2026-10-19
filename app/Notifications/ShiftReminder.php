<?php

namespace App\Notifications;

/**
 * To the assigned volunteer `reminder_hours_before` the event (24 h), with the arrival time (US6).
 */
class ShiftReminder extends AppNotification
{
    public function __construct(public int $assignmentId, public string $shift, public int $secondsToStart) {}

    public static function type(): string
    {
        return 'shift_reminder';
    }

    public function title(): string
    {
        return 'Lembrete de turno';
    }

    public function body(): string
    {
        return $this->shift;
    }

    public function url(): string
    {
        return '/minha-escala';
    }

    public function pushTag(): string
    {
        return "assignment-{$this->assignmentId}";
    }

    public function pushTtl(): int
    {
        return $this->secondsToStart;
    }
}
