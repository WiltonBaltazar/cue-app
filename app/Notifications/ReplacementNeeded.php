<?php

namespace App\Notifications;

/**
 * To coordinators when a published shift needs someone else: absence, decline or pause
 * (US4-1). Never includes the reason (FR-016).
 */
class ReplacementNeeded extends AppNotification
{
    public function __construct(public string $volunteerShortName, public string $shift, public bool $urgent, public int $scheduleId) {}

    public static function type(): string
    {
        return 'replacement_needed';
    }

    public function title(): string
    {
        return $this->urgent ? 'Substituto urgente' : 'Turno por substituir';
    }

    public function body(): string
    {
        return "{$this->volunteerShortName} não pode ir: {$this->shift}";
    }

    public function url(): string
    {
        return "/gestao/escalas/{$this->scheduleId}";
    }
}
