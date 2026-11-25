<?php

namespace App\Policies;

use App\Models\User;

/**
 * Coordinators manage ActivityTemplate records (US2).
 */
class ActivityTemplatePolicy extends CoordinatorOnlyPolicy
{
    public function delete(User $user): bool
    {
        return $user->isCoordinator();
    }
}
