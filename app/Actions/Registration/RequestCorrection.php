<?php

namespace App\Actions\Registration;

use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;

/**
 * Sends the registration back to the volunteer with notes on what to fix (FR-003).
 */
class RequestCorrection extends ReviewTransition
{
    public function handle(Volunteer $volunteer, User $coordinator, string $notes): void
    {
        $this->ensureStatus($volunteer, [VolunteerStatus::Pending], 'Só é possível pedir correcção a um cadastro pendente.');

        $this->recordDecision($volunteer, $coordinator, VolunteerStatus::NeedsCorrection, $notes);
    }
}
