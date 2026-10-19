<?php

namespace App\Enums;

/**
 * Availability for meetings, training, technical rehearsals and other extra activities
 * (registration form, question 14).
 */
enum TrainingAvailability: string
{
    case Regular = 'regular';
    case WithNotice = 'with_notice';
    case Occasional = 'occasional';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Sim, regularmente',
            self::WithNotice => 'Sim, mediante aviso prévio',
            self::Occasional => 'Ocasionalmente',
            self::None => 'Não tenho disponibilidade regular',
        };
    }
}
