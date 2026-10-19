<?php

namespace App\Notifications;

/**
 * To the requester and the substitute once a swap is approved or rejected (US4-3).
 */
class SwapDecided extends AppNotification
{
    public function __construct(public bool $approved, public string $shift) {}

    public static function type(): string
    {
        return 'swap_decided';
    }

    public function title(): string
    {
        return $this->approved ? 'Troca aprovada' : 'Troca não aprovada';
    }

    public function body(): string
    {
        return $this->shift;
    }

    public function url(): string
    {
        return '/minha-escala';
    }
}
