<?php

namespace App\Enums;

enum EventType: string
{
    case Recurring = 'recurring';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'Actividade recorrente',
            self::Special => 'Evento extraordinário',
        };
    }
}
