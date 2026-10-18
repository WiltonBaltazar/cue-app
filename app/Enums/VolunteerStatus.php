<?php

namespace App\Enums;

enum VolunteerStatus: string
{
    case Pending = 'pending';
    case NeedsCorrection = 'needs_correction';
    case Active = 'active';
    case Inactive = 'inactive';
    case Rejected = 'rejected';
    case Anonymized = 'anonymized';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::NeedsCorrection => 'Em correcção',
            self::Active => 'Activo',
            self::Inactive => 'Em pausa',
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
}
