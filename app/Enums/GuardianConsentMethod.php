<?php

namespace App\Enums;

/**
 * How a coordinator obtained a minor's guardian consent outside the app (FR-002a).
 */
enum GuardianConsentMethod: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Presencialmente',
            self::Phone => 'Por telefone',
        };
    }
}
