<?php

namespace App\Enums;

enum VolunteerStatus: string
{
    case Pending = 'pending';
    case NeedsCorrection = 'needs_correction';
    case Active = 'active';
    case Inactive = 'inactive';
    case Deactivated = 'deactivated';
    case Rejected = 'rejected';
    case Anonymized = 'anonymized';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::NeedsCorrection => 'Em correcção',
            self::Active => 'Activo',
            self::Inactive => 'Em pausa',
            self::Deactivated => 'Desactivado',
            self::Rejected => 'Não aprovado',
            self::Anonymized => 'Removido',
        };
    }

    /**
     * Only active volunteers can be scheduled.
     */
    public function isSchedulable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Out of the team: not even a coordinator can place them by hand.
     */
    public function hasLeft(): bool
    {
        return in_array($this, [self::Deactivated, self::Anonymized], true);
    }
}
