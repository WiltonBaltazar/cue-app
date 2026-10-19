<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Coordinators plan events (US2); a cancelled event is final.
 */
class EventPolicy extends CoordinatorOnlyPolicy
{
    public function cancel(User $user, Event $event): bool
    {
        return $user->isCoordinator();
    }
}
