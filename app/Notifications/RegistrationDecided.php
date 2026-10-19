<?php

namespace App\Notifications;

use App\Enums\VolunteerStatus;

/**
 * To the volunteer: their registration was approved, rejected or needs a correction.
 * Coordinator notes are not included; they are shown on the status page.
 */
class RegistrationDecided extends AppNotification
{
    public function __construct(public VolunteerStatus $decision) {}

    public static function type(): string
    {
        return 'registration_decided';
    }

    public function title(): string
    {
        return match ($this->decision) {
            VolunteerStatus::Active => 'Cadastro aprovado',
            VolunteerStatus::NeedsCorrection => 'Cadastro precisa de correcção',
            default => 'Cadastro não aprovado',
        };
    }

    public function body(): string
    {
        return match ($this->decision) {
            VolunteerStatus::Active => 'Bem-vindo à equipa! Já pode ser escalado.',
            VolunteerStatus::NeedsCorrection => 'Veja o que falta corrigir e reenvie o cadastro.',
            default => 'Veja os detalhes na sua área pessoal.',
        };
    }

    public function url(): string
    {
        return $this->decision === VolunteerStatus::Active
            ? route('home', absolute: false)
            : route('volunteer.status', absolute: false);
    }
}
