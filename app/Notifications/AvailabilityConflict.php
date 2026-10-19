<?php

namespace App\Notifications;

/**
 * To coordinators: a volunteer's new availability clashes with published shifts, which
 * are flagged, not removed (spec edge case, T081a).
 */
class AvailabilityConflict extends AppNotification
{
    public function __construct(public string $volunteerShortName, public int $shiftCount, public int $scheduleId) {}

    public static function type(): string
    {
        return 'availability_conflict';
    }

    public function title(): string
    {
        return 'Conflito de disponibilidade';
    }

    public function body(): string
    {
        return $this->shiftCount === 1
            ? "{$this->volunteerShortName} mudou a disponibilidade e tem 1 turno em conflito."
            : "{$this->volunteerShortName} mudou a disponibilidade e tem {$this->shiftCount} turnos em conflito.";
    }

    public function url(): string
    {
        return "/gestao/escalas/{$this->scheduleId}";
    }

    public function pushTag(): string
    {
        return "availability-conflict-{$this->scheduleId}";
    }
}
