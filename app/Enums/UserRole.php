<?php

namespace App\Enums;

/**
 * Access role of an account. Not to be confused with TeamRole ("Função").
 */
enum UserRole: string
{
    case Coordinator = 'coordinator';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::Coordinator => 'Coordenador',
            self::Volunteer => 'Voluntário',
        };
    }
}
