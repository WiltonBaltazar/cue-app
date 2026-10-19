<?php

namespace App\Actions\Registration;

use App\Enums\VolunteerStatus;
use App\Models\RejectedRegistration;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;

/**
 * Rejects a registration. It stays reversible for 30 days; afterwards the personal data is
 * purged (Phase 9) and only the phone hash remains (FR-003a).
 */
class RejectVolunteer extends ReviewTransition
{
    public const REVERSIBLE_DAYS = 30;

    public function handle(Volunteer $volunteer, User $coordinator, ?string $notes = null): void
    {
        $this->ensureStatus($volunteer, [VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection], 'Este cadastro já não está à espera de revisão.');

        DB::transaction(function () use ($volunteer, $coordinator, $notes): void {
            if ($volunteer->user !== null) {
                RejectedRegistration::query()->create([
                    'phone_hash' => RejectedRegistration::hashPhone($volunteer->user->phone),
                    'rejected_at' => now(),
                ]);
            }

            $this->recordDecision($volunteer, $coordinator, VolunteerStatus::Rejected, $notes);
        });
    }
}
