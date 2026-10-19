<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Volunteer;

/**
 * Who may see and change a volunteer profile (FR-026, FR-031).
 */
class VolunteerPolicy
{
    /**
     * Sensitive fields: coordinators and the volunteer themself only.
     */
    /**
     * An anonymised profile has nothing left to show or edit (FR-028).
     */
    public function viewSensitive(User $user, Volunteer $volunteer): bool
    {
        return ! $volunteer->isAnonymized() && ($user->isCoordinator() || $this->owns($user, $volunteer));
    }

    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function update(User $user, Volunteer $volunteer): bool
    {
        return ! $volunteer->isAnonymized() && ($user->isCoordinator() || $this->owns($user, $volunteer));
    }

    /**
     * Approve, reject, request correction, set levels.
     */
    public function review(User $user, Volunteer $volunteer): bool
    {
        return $user->isCoordinator();
    }

    public function pause(User $user, Volunteer $volunteer): bool
    {
        return $user->isCoordinator() || $this->owns($user, $volunteer);
    }

    public function issueResetLink(User $user, Volunteer $volunteer): bool
    {
        return $user->isCoordinator() && $volunteer->user_id !== null;
    }

    private function owns(User $user, Volunteer $volunteer): bool
    {
        return $volunteer->user_id === $user->id;
    }
}
