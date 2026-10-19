<?php

namespace App\Actions\Swaps;

use App\Enums\AssignmentStatus;
use App\Enums\VolunteerStatus;
use App\Models\Absence;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an absence and gives back the shifts it put up for replacement, when nobody
 * has been found yet: still the volunteer's, place still free, no swap in progress, no
 * other absence or pause covering that day.
 */
class RemoveAbsence
{
    /**
     * @return int shifts given back
     */
    public function handle(Volunteer $volunteer, Absence $absence): int
    {
        return DB::transaction(function () use ($volunteer, $absence): int {
            $absence->delete();

            if ($volunteer->status !== VolunteerStatus::Active) {
                return 0;
            }

            $shifts = $volunteer->assignments()
                ->where('status', AssignmentStatus::NeedsReplacement)
                ->whereHas('schedule', fn ($query) => $query->published())
                ->whereHas('event', fn ($query) => $query
                    ->scheduled()
                    ->where('starts_at', '>', now())
                    ->whereBetween('date', [$absence->starts_on->toDateString(), $absence->ends_on->toDateString()]))
                ->with(['event', 'position'])
                ->get();

            $restored = 0;

            foreach ($shifts as $assignment) {
                if ($this->canRestore($assignment)) {
                    $assignment->status = AssignmentStatus::Confirmed;
                    $assignment->save();
                    $restored++;
                }
            }

            return $restored;
        });
    }

    private function canRestore(Assignment $assignment): bool
    {
        $date = $assignment->event->date->toDateString();

        $stillAbsent = Absence::query()
            ->where('volunteer_id', $assignment->volunteer_id)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->exists();

        $swapInProgress = SwapRequest::query()->active()->where('assignment_id', $assignment->id)->exists();

        $placeTaken = $assignment->position->assignments()->active()->count() >= $assignment->position->quantity;

        return ! $stillAbsent && ! $swapInProgress && ! $placeTaken;
    }
}
