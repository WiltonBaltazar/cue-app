<?php

namespace App\Actions\Volunteers;

use App\Enums\Frequency;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;

/**
 * Saves a volunteer's weekly availability and frequency (FR-005). Conflict detection
 * against published shifts is added with schedules (T081a).
 */
class UpdateAvailability
{
    /**
     * @param  list<array{weekday: int, period: string}>  $slots
     */
    public function handle(Volunteer $volunteer, array $slots, Frequency $maxFrequency, bool $variableHours): void
    {
        DB::transaction(function () use ($volunteer, $slots, $maxFrequency, $variableHours): void {
            $volunteer->max_frequency = $maxFrequency;
            $volunteer->variable_hours = $variableHours;
            $volunteer->save();

            $volunteer->availabilities()->delete();

            foreach (collect($slots)->unique(fn (array $slot) => $slot['weekday'].'|'.$slot['period']) as $slot) {
                $volunteer->availabilities()->create($slot);
            }
        });
    }
}
