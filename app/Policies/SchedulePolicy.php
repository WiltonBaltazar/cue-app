<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Coordinators manage schedules; anyone signed in may view a published one (FR-027).
 */
class SchedulePolicy extends CoordinatorOnlyPolicy
{
    public function view(User $user, ?Schedule $schedule = null): bool
    {
        return $user->isCoordinator() || ($schedule?->isPublished() ?? false);
    }
}
