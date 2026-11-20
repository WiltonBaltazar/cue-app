<?php

namespace App\Policies;

use App\Enums\EventType;
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

    public function delete(User $user, Event $event): bool
    {
        return $user->isCoordinator() && $event->type === EventType::Special;
    }
}
