<?php

namespace App\Notifications;

/**
 * To the assigned volunteer `check_reminder_hours_before` the event (72 h): still able to
 * come? "Não posso ir" opens the decline confirmation for that shift (US4-5).
 */
class ShiftCheck extends AppNotification
{
    public function __construct(public int $assignmentId, public string $shift, public int $secondsToStart) {}

    public static function type(): string
    {
        return 'shift_check';
    }

    public function title(): string
    {
        return 'Ainda pode ir?';
    }

    public function body(): string
    {
        return $this->shift;
    }

    public function url(): string
    {
        return '/minha-escala';
    }

    public function pushTag(): string
    {
        return "assignment-{$this->assignmentId}";
    }

    /**
     * Pointless once the event has started.
     */
    public function pushTtl(): int
    {
        return $this->secondsToStart;
    }

    public function pushActions(): array
    {
        return [['action' => 'decline', 'title' => 'Não posso ir', 'url' => "/minha-escala?recusar={$this->assignmentId}"]];
    }
}
