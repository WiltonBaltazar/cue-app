<?php

namespace App\Actions\Registration;

use App\Actions\Scheduling\FreeDraftPlaces;
use App\Actions\Swaps\ReplacementFlagger;
use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;

/**
 * Carries out a data-removal request once its 7 days are over (FR-028), or a coordinator's
 * decision to delete someone (FR-005b): personal fields are
 * cleared, the login is deleted and the profile becomes "Voluntário #id". Past assignments
 * stay as anonymous participation records; the audit log is never touched (FR-029).
 */
class AnonymizeVolunteer
{
    public function __construct(
        private ReplacementFlagger $flagger,
        private FreeDraftPlaces $freeDraftPlaces,
        private DeleteUserAccount $deleteAccount,
    ) {}

    /**
     * @return int published shifts flagged for replacement
     */
    public function handle(Volunteer $volunteer): int
    {
        if ($volunteer->isAnonymized()) {
            return 0;
        }

        return DB::transaction(function () use ($volunteer): int {
            // Anything published since the request still needs someone else.
            $this->freeDraftPlaces->handle($volunteer);
            $flagged = $this->flagger->flag($volunteer, LocalTime::today());

            $volunteer->forceFill([
                'status' => VolunteerStatus::Anonymized,
                'full_name' => Volunteer::anonymousName($volunteer->id),
                'birth_date' => null,
                'guardian_name' => null,
                'guardian_phone' => null,
                'nickname' => null,
                'neighbourhood' => null,
                'whatsapp_phone' => null,
                'social_media' => null,
                'education_level' => null,
                'training' => null,
                'other_area' => null,
                'experience_role_ids' => null,
                'other_experience' => null,
                'experience_description' => null,
                'commitments' => null,
                'motivations' => null,
                'expectations' => null,
                'goals' => null,
                'comments' => null,
                'suggestions' => null,
                'suggestion_status' => null,
                'suggestion_note' => null,
                'frequency_note' => null,
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

            return $flagged;
        });
    }
}
