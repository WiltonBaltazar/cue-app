<?php

namespace App\Actions\Volunteers;

use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use Illuminate\Validation\ValidationException;

/**
 * Ends a pause (FR-005a), manually or on the return date.
 */
class ResumeVolunteer
{
    public function handle(Volunteer $volunteer): void
    {
        if ($volunteer->status !== VolunteerStatus::Inactive) {
            throw ValidationException::withMessages(['volunteer' => 'Este voluntário não está em pausa.']);
        }

        $volunteer->status = VolunteerStatus::Active;
        $volunteer->inactive_since = null;
        $volunteer->inactive_until = null;
        $volunteer->paused_by = null;
        $volunteer->save();
    }
}
