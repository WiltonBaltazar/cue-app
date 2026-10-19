<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared rule for catalogue and planning models only coordinators manage.
 */
abstract class CoordinatorOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function view(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function update(User $user): bool
    {
        return $user->isCoordinator();
    }
}
