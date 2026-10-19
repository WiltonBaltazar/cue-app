<?php

namespace App\Notifications;

/**
 * To volunteers assigned to an event that was cancelled (US2-4).
 */
class EventCancelled extends AppNotification
{
    public function __construct(public string $event) {}

    public static function type(): string
    {
        return 'event_cancelled';
    }

    public function title(): string
    {
        return 'Evento cancelado';
    }

    public function body(): string
    {
        return "{$this->event} foi cancelado. Já não precisa de comparecer.";
    }

    public function url(): string
    {
        return '/minha-escala';
    }

    public function pushUrgency(): string
    {
        return 'high';
    }
}
