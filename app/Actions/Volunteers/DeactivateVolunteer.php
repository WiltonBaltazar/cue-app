<?php

namespace App\Actions\Volunteers;

use App\Actions\Scheduling\FreeDraftPlaces;
use App\Actions\Swaps\ReplacementFlagger;
use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A coordinator takes someone out of the team without losing their data (FR-005b). Unlike
 * a pause there is no return date, the volunteer can not undo it, and they can not be put
 * on a schedule even by hand.
 */
class DeactivateVolunteer
{
    public function __construct(private ReplacementFlagger $flagger, private FreeDraftPlaces $freeDraftPlaces) {}

    /**
     * @return int published shifts flagged for replacement
     */
    public function handle(Volunteer $volunteer, User $coordinator): int
    {
        if (! in_array($volunteer->status, [VolunteerStatus::Active, VolunteerStatus::Inactive], true)) {
            throw ValidationException::withMessages(['volunteer' => 'Só é possível desactivar um voluntário activo ou em pausa.']);
        }

        return DB::transaction(function () use ($volunteer, $coordinator): int {
            $today = LocalTime::today();

            $volunteer->status = VolunteerStatus::Deactivated;
            $volunteer->inactive_since = $today;
            $volunteer->inactive_until = null;
            $volunteer->paused_by = $coordinator->id;
            $volunteer->save();

            $this->freeDraftPlaces->handle($volunteer);

            return $this->flagger->flag($volunteer, $today);
        });
    }
}
