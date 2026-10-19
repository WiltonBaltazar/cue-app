<?php

namespace App\Actions\Scheduling;

use App\Models\Assignment;
use App\Notifications\AssignmentChanged;
use App\Support\ShiftDescription;

/**
 * Takes a volunteer off a position. In a published schedule they are told.
 */
class RemoveAssignment
{
    public function __construct(private ConcurrencyGuard $guard) {}

    public function handle(Assignment $assignment, ?int $lockVersion): void
    {
        $this->guard->ensureCurrent($assignment, $lockVersion);

        $published = $assignment->schedule->isPublished();
        $shift = ShiftDescription::of($assignment);
        $user = $assignment->volunteer->user;

        $assignment->delete();

        if ($published) {
            $user?->notify(new AssignmentChanged(false, $shift));
        }
    }
}
