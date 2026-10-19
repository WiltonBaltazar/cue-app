<?php

namespace App\Policies;

use App\Models\SwapRequest;
use App\Models\User;

/**
 * Requesters cancel their own requests; coordinators decide. Who may accept depends on
 * eligibility, which AcceptSwap checks with the scheduling rules.
 */
class SwapRequestPolicy
{
    public function cancel(User $user, SwapRequest $swap): bool
    {
        return $user->volunteer?->id === $swap->requester_id;
    }

    public function decide(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }
}
