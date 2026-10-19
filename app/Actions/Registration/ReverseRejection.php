<?php

namespace App\Actions\Registration;

use App\Enums\VolunteerStatus;
use App\Models\RejectedRegistration;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puts a rejected registration back under review, within 30 days of the rejection (FR-003a).
 */
class ReverseRejection extends ReviewTransition
{
    public function handle(Volunteer $volunteer, User $coordinator): void
    {
        $this->ensureStatus($volunteer, [VolunteerStatus::Rejected], 'Este cadastro não está rejeitado.');

        if ($volunteer->reviewed_at === null || $volunteer->reviewed_at->addDays(RejectVolunteer::REVERSIBLE_DAYS)->isPast()) {
            throw ValidationException::withMessages([
                'volunteer' => 'Já passaram mais de 30 dias desde a rejeição. A pessoa pode fazer um novo cadastro.',
            ]);
        }

        DB::transaction(function () use ($volunteer, $coordinator): void {
            if ($volunteer->user !== null) {
                RejectedRegistration::query()
                    ->where('phone_hash', RejectedRegistration::hashPhone($volunteer->user->phone))
                    ->delete();
            }

            $volunteer->status = VolunteerStatus::Pending;
            $volunteer->reviewed_by = $coordinator->id;
            $volunteer->reviewed_at = now();
            $volunteer->review_notes = null;
            $volunteer->save();
        });
    }
}
