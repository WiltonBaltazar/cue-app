<?php

namespace App\Notifications;

/**
 * To coordinators: a volunteer asked for their data to be removed; their upcoming shifts
 * are now "Por substituir" (FR-028).
 */
class DataRemovalRequested extends AppNotification
{
    public function __construct(public string $volunteerShortName, public int $flaggedShifts) {}

    public static function type(): string
    {
        return 'data_removal_requested';
    }

    public function title(): string
    {
        return 'Pedido de remoção de dados';
    }

    public function body(): string
    {
        return $this->flaggedShifts > 0
            ? "{$this->volunteerShortName} vai deixar a equipa. {$this->flaggedShifts} turnos ficaram por substituir."
            : "{$this->volunteerShortName} vai deixar a equipa.";
    }

    public function url(): string
    {
        return $this->flaggedShifts > 0 ? '/gestao/escalas' : '/gestao/voluntarios';
    }
}
