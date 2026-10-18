<?php

namespace App\Enums;

/**
 * Status of a shift ("Turno"). Assignments start confirmed when a schedule is published.
 */
enum AssignmentStatus: string
{
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case NeedsReplacement = 'needs_replacement';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Declined => 'Recusado',
            self::NeedsReplacement => 'Por substituir',
        };
    }
}
