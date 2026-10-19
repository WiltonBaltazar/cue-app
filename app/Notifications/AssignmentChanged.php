<?php

namespace App\Notifications;

/**
 * To a volunteer whose shift in a published schedule was added or removed (FR-023).
 */
class AssignmentChanged extends AppNotification
{
    public function __construct(public bool $added, public string $shift) {}

    public static function type(): string
    {
        return 'assignment_changed';
    }

    public function title(): string
    {
        return $this->added ? 'Novo turno' : 'Turno removido';
    }

    public function body(): string
    {
        return $this->shift;
    }

    public function url(): string
    {
        return '/minha-escala';
    }
}
