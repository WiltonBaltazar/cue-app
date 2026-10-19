<?php

namespace App\Notifications;

use App\Models\Volunteer;

/**
 * To coordinators: a new registration is waiting for review (US1, FR-023).
 */
class RegistrationSubmitted extends AppNotification
{
    public function __construct(public Volunteer $volunteer) {}

    public static function type(): string
    {
        return 'registration_submitted';
    }

    public function title(): string
    {
        return 'Novo cadastro';
    }

    public function body(): string
    {
        return "{$this->volunteer->shortName()} enviou o cadastro para revisão.";
    }

    public function url(): string
    {
        return route('coord.registrations.show', $this->volunteer, absolute: false);
    }
}
