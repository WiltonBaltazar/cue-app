<?php

namespace App\Actions\Registration;

use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\RegistrationDecided;
use Illuminate\Validation\ValidationException;

/**
 * Shared guard and bookkeeping for coordinator review decisions (data-model.md state machine).
 */
abstract class ReviewTransition
{
    /**
     * @param  list<VolunteerStatus>  $allowed
     */
    protected function ensureStatus(Volunteer $volunteer, array $allowed, string $message): void
    {
        if (! in_array($volunteer->status, $allowed, true)) {
            throw ValidationException::withMessages(['volunteer' => $message]);
        }
    }

    protected function recordDecision(Volunteer $volunteer, User $coordinator, VolunteerStatus $status, ?string $notes): void
    {
        $volunteer->status = $status;
        $volunteer->reviewed_by = $coordinator->id;
        $volunteer->reviewed_at = now();
        $volunteer->review_notes = filled($notes) ? $notes : null;
        $volunteer->save();

        $volunteer->user?->notify(new RegistrationDecided($status));
    }
}
