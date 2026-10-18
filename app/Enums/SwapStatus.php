<?php

namespace App\Enums;

enum SwapStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em aberto',
            self::Accepted => 'Aceite',
            self::Approved => 'Aprovada',
            self::Rejected => 'Rejeitada',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled, self::Expired], true);
    }
}
