<?php

namespace App\Notifications;

/**
 * To coordinators (approval needed) and to the requester (US4-3).
 */
class SwapAccepted extends AppNotification
{
    public function __construct(public string $acceptorShortName, public string $shift, public bool $forCoordinator) {}

    public static function type(): string
    {
        return 'swap_accepted';
    }

    public function title(): string
    {
        return $this->forCoordinator ? 'Troca à espera de aprovação' : 'Troca aceite';
    }

    public function body(): string
    {
        return "{$this->acceptorShortName} aceitou substituir: {$this->shift}";
    }

    public function url(): string
    {
        return $this->forCoordinator ? '/gestao/trocas' : '/trocas';
    }
}
