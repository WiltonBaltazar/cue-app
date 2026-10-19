<?php

namespace App\Notifications;

/**
 * To the chosen colleague, or every eligible colleague for an open request (US4-2).
 */
class SwapRequested extends AppNotification
{
    public function __construct(public string $requesterShortName, public string $shift, public bool $urgent) {}

    public static function type(): string
    {
        return 'swap_requested';
    }

    public function title(): string
    {
        return $this->urgent ? 'Troca urgente pedida' : 'Pedido de troca';
    }

    public function body(): string
    {
        return "{$this->requesterShortName} procura quem o substitua: {$this->shift}";
    }

    public function url(): string
    {
        return '/trocas';
    }
}
