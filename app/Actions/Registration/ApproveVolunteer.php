<?php

namespace App\Actions\Registration;

use App\Enums\GuardianConsentMethod;
use App\Enums\Level;
use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approves a registration (US1-3). Minors need the guardian's consent, obtained in person
 * or by phone, recorded with the coordinator and date (FR-002a).
 */
class ApproveVolunteer extends ReviewTransition
{
    /**
     * @param  array<int, Level>  $levels  team_role_id => level adjustments (FR-004)
     */
    public function handle(Volunteer $volunteer, User $coordinator, array $levels = [], ?GuardianConsentMethod $guardianConsent = null): void
    {
        $this->ensureStatus($volunteer, [VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection], 'Este cadastro já não está à espera de revisão.');

        if ($volunteer->is_minor && ! $volunteer->hasGuardianConsent() && $guardianConsent === null) {
            throw ValidationException::withMessages([
                'guardian_consent_method' => 'Confirme o consentimento do encarregado antes de aprovar um menor.',
            ]);
        }

        DB::transaction(function () use ($volunteer, $coordinator, $levels, $guardianConsent): void {
            foreach ($volunteer->skills as $skill) {
                if (isset($levels[$skill->team_role_id])) {
                    $skill->update(['level' => $levels[$skill->team_role_id]]);
                }
            }

            if ($guardianConsent !== null) {
                $volunteer->guardian_consent_confirmed_by = $coordinator->id;
                $volunteer->guardian_consent_confirmed_at = now();
                $volunteer->guardian_consent_method = $guardianConsent;
            }

            $this->recordDecision($volunteer, $coordinator, VolunteerStatus::Active, null);
        });
    }
}
