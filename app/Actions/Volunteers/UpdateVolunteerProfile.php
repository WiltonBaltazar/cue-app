<?php

namespace App\Actions\Volunteers;

use App\Enums\Frequency;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;

/**
 * Saves a volunteer's profile data, whether the volunteer or a coordinator edits it (FR-005).
 * The name is kept in step with the login account, so it reads the same everywhere.
 */
class UpdateVolunteerProfile
{
    public function __construct(private UpdateAvailability $updateAvailability) {}

    /**
     * @param  array<string, mixed>  $data  validated UpdateVolunteerProfileRequest data
     */
    public function handle(Volunteer $volunteer, array $data, ?string $guardianPhone, ?string $whatsappPhone): void
    {
        $noExperience = (bool) ($data['no_experience'] ?? false);
        $experienceRoleIds = $noExperience ? [] : array_values(array_map('intval', $data['experience_role_ids'] ?? []));

        DB::transaction(function () use ($volunteer, $data, $guardianPhone, $whatsappPhone, $noExperience, $experienceRoleIds): void {
            $volunteer->fill([
                ...array_intersect_key($data, array_flip([
                    'full_name', 'nickname', 'birth_date', 'neighbourhood', 'phone_has_whatsapp',
                    'guardian_name', 'education_level', 'training', 'other_area', 'experience_description',
                    'commitments', 'motivations', 'expectations', 'goals', 'comments', 'suggestions',
                    'can_support_others', 'frequency_note', 'other_days_by_arrangement', 'training_availability',
                ])),
                'experience_role_ids' => $experienceRoleIds,
                'other_experience' => $noExperience ? null : ($data['other_experience'] ?? null),
                'guardian_phone' => $guardianPhone,
                'whatsapp_phone' => ($data['phone_has_whatsapp'] ?? false) ? null : $whatsappPhone,
                'social_media' => array_values($data['social_media'] ?? []),
            ]);
            $volunteer->save();

            if (isset($data['full_name'])) {
                $volunteer->user?->update(['name' => $data['full_name']]);
            }

            $this->syncSkills($volunteer, $data['skills'] ?? [], $experienceRoleIds, $data['preferred_role_id'] ?? null);

            $this->updateAvailability->handle(
                $volunteer,
                $data['availability'] ?? [],
                Frequency::from($data['max_frequency']),
                (bool) ($data['variable_hours'] ?? false),
            );
        });
    }

    /**
     * Keeps coordinator-set levels for roles that stay; new roles start as beginner
     * (column default).
     *
     * @param  list<array{team_role_id: int}>  $skills
     * @param  list<int>  $experienceRoleIds
     */
    private function syncSkills(Volunteer $volunteer, array $skills, array $experienceRoleIds, ?int $preferredRoleId): void
    {
        $chosen = collect($skills)->keyBy(fn (array $skill) => (int) $skill['team_role_id']);

        $volunteer->skills()->whereNotIn('team_role_id', $chosen->keys())->delete();

        foreach ($chosen as $roleId => $skill) {
            $volunteer->skills()->updateOrCreate(
                ['team_role_id' => $roleId],
                [
                    'interested' => true,
                    'has_experience' => in_array($roleId, $experienceRoleIds, true),
                    'is_preferred' => $roleId === $preferredRoleId,
                ],
            );
        }
    }
}
