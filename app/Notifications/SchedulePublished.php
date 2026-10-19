<?php

namespace App\Notifications;

/**
 * To each assigned volunteer when a schedule is published (US3-7, US6-1).
 */
class SchedulePublished extends AppNotification
{
    public function __construct(public int $shiftCount, public string $firstShift) {}

    public static function type(): string
    {
        return 'schedule_published';
    }

    public function title(): string
    {
        return 'Nova escala publicada';
    }

    public function body(): string
    {
        return $this->shiftCount === 1
            ? "Tem 1 turno: {$this->firstShift}"
            : "Tem {$this->shiftCount} turnos. O primeiro: {$this->firstShift}";
    }

    public function url(): string
    {
        return '/minha-escala';
    }
}
