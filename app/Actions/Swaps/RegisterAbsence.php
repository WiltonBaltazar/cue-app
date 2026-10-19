<?php

namespace App\Actions\Swaps;

use App\Models\Absence;
use App\Models\User;
use App\Models\Volunteer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registers a period a volunteer cannot serve (FR-016). Published shifts inside it become
 * "Por substituir" and coordinators are told; future generation avoids it automatically.
 */
class RegisterAbsence
{
    public function __construct(private ReplacementFlagger $flagger) {}

    public function handle(Volunteer $volunteer, CarbonImmutable $from, CarbonImmutable $to, ?string $reason, User $by): Absence
    {
        return DB::transaction(function () use ($volunteer, $from, $to, $reason, $by): Absence {
            $absence = $volunteer->absences()->create([
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
                'reason' => filled($reason) ? $reason : null,
                'created_by' => $by->id,
            ]);

            $this->flagger->flag($volunteer, $from, $to);

            return $absence;
        });
    }
}
