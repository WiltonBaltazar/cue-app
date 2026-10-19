<?php

namespace App\Actions\Registration;

use App\Actions\Swaps\ReplacementFlagger;
use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;

/**
 * Carries out a data-removal request once its 7 days are over (FR-028): personal fields are
 * cleared, the login is deleted and the profile becomes "Voluntário #id". Past assignments
 * stay as anonymous participation records; the audit log is never touched (FR-029).
 */
class AnonymizeVolunteer
{
    public function __construct(private ReplacementFlagger $flagger, private DeleteUserAccount $deleteAccount) {}

    public function handle(Volunteer $volunteer): void
    {
        if ($volunteer->isAnonymized()) {
            return;
        }

        DB::transaction(function () use ($volunteer): void {
            // Anything published since the request still needs someone else.
            $this->flagger->flag($volunteer, LocalTime::today());

            $volunteer->forceFill([
                'status' => VolunteerStatus::Anonymized,
                'full_name' => Volunteer::anonymousName($volunteer->id),
                'birth_date' => null,
                'guardian_name' => null,
                'guardian_phone' => null,
                'neighbourhood' => null,
                'education_level' => null,
                'training' => null,
                'commitments' => null,
                'motivations' => null,
                'comments' => null,
                'review_notes' => null,
                'anonymized_at' => now(),
            ])->save();

            // Availability, skills and absence reasons describe the person, not past shifts.
            $volunteer->availabilities()->delete();
            $volunteer->skills()->delete();
            $volunteer->absences()->delete();

            $user = $volunteer->user;
            if ($user !== null) {
                $this->deleteAccount->handle($user);
            }
        });
    }
}
