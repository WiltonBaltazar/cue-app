<?php

namespace App\Actions\Volunteers;

use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use Illuminate\Validation\ValidationException;

/**
 * Brings a deactivated volunteer back to the team (FR-005b); coordinators only.
 */
class ReactivateVolunteer
{
    public function handle(Volunteer $volunteer): void
    {
        if ($volunteer->status !== VolunteerStatus::Deactivated) {
            throw ValidationException::withMessages(['volunteer' => 'Este voluntário não está desactivado.']);
        }

        $volunteer->status = VolunteerStatus::Active;
        $volunteer->inactive_since = null;
        $volunteer->inactive_until = null;
        $volunteer->paused_by = null;
        $volunteer->save();
    }
}
