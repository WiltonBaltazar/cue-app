<?php

namespace App\Actions\Volunteers;

use App\Actions\Swaps\ReplacementFlagger;
use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pauses a volunteer, by themself or a coordinator (FR-005a): not scheduled while paused,
 * published shifts in the pause become "Por substituir", optional automatic return date.
 */
class PauseVolunteer
{
    public function __construct(private ReplacementFlagger $flagger) {}

    public function handle(Volunteer $volunteer, User $by, ?CarbonImmutable $until = null): int
    {
        if ($volunteer->status !== VolunteerStatus::Active) {
            throw ValidationException::withMessages(['volunteer' => 'Só é possível pausar um voluntário activo.']);
        }

        return DB::transaction(function () use ($volunteer, $by, $until): int {
            $today = LocalTime::today();

            $volunteer->status = VolunteerStatus::Inactive;
            $volunteer->inactive_since = $today;
            $volunteer->inactive_until = $until;
            $volunteer->paused_by = $by->id;
            $volunteer->save();

            return $this->flagger->flag($volunteer, $today, $until);
        });
    }
}
