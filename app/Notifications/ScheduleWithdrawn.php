<?php

namespace App\Notifications;

/**
 * To a volunteer when a published schedule with upcoming shifts of theirs is deleted.
 * One notification per person, not one per shift; follows the "Alterações aos turnos"
 * preference.
 */
class ScheduleWithdrawn extends AppNotification
{
    public function __construct(public string $period, public int $shifts) {}

    public static function type(): string
    {
        return 'assignment_changed';
    }

    public function title(): string
    {
        return 'Escala retirada';
    }

    public function body(): string
    {
        return $this->shifts === 1
            ? "A escala de {$this->period} foi retirada. Já não tem o turno que lhe estava atribuído."
            : "A escala de {$this->period} foi retirada. Já não tem os {$this->shifts} turnos que lhe estavam atribuídos.";
    }

    public function url(): string
    {
        return '/minha-escala';
    }
}
