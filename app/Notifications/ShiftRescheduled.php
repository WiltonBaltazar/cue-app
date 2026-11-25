<?php

namespace App\Notifications;

/**
 * To volunteers in a published schedule when their event's date or times change (FR-023).
 */
class ShiftRescheduled extends AppNotification
{
    public function __construct(public int $assignmentId, public string $shift) {}

    public static function type(): string
    {
        return 'assignment_changed';
    }

    public function title(): string
    {
        return 'O seu turno mudou de data ou hora';
    }

    public function body(): string
    {
        return "Agora: {$this->shift}";
    }

    public function url(): string
    {
        return '/minha-escala';
    }

    public function pushTag(): string
    {
        return "assignment-{$this->assignmentId}";
    }
}
