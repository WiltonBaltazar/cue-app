<?php

namespace App\Actions\Registration;

use App\Enums\Level;
use App\Enums\TrainingAvailability;
use App\Enums\UserRole;
use App\Models\RejectedRegistration;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\RegistrationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates the account and the pending volunteer profile from a validated registration
 * (US1, FR-001–FR-004). The person can log in, but only sees their status until approved.
 */
class SubmitRegistration
{
    /**
     * @param  array<string, mixed>  $data  validated StoreRegistrationRequest data
     */
    public function handle(array $data): Volunteer
    {
        $volunteer = DB::transaction(function () use ($data): Volunteer {
            $phone = (string) $data['phone_e164'];
            $level = Level::from((string) $data['level']);
            $noExperience = (bool) ($data['no_experience'] ?? false);
            $experienceRoleIds = $noExperience ? [] : array_values(array_map('intval', $data['experience_role_ids'] ?? []));

            $user = User::query()->create([
                'name' => $data['full_name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);

            $volunteer = new Volunteer([
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'nickname' => $data['nickname'] ?? null,
                'birth_date' => $data['birth_date'],
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone_e164'] ?? null,
                'neighbourhood' => $data['neighbourhood'] ?? null,
                'phone_has_whatsapp' => (bool) ($data['phone_has_whatsapp'] ?? false),
                'whatsapp_phone' => ($data['phone_has_whatsapp'] ?? false) ? null : ($data['whatsapp_phone_e164'] ?? null),
                'social_media' => array_values($data['social_media'] ?? []),
                'education_level' => $data['education_level'] ?? null,
                'training' => $data['training'] ?? null,
                'other_area' => $data['other_area'] ?? null,
                'experience_role_ids' => $experienceRoleIds,
                'other_experience' => $noExperience ? null : ($data['other_experience'] ?? null),
                'experience_description' => $data['experience_description'] ?? null,
                'commitments' => $data['commitments'] ?? null,
                'motivations' => $data['motivations'] ?? null,
                'expectations' => $data['expectations'] ?? null,
                'goals' => $data['goals'] ?? null,
                'comments' => $data['comments'] ?? null,
                'suggestions' => $data['suggestions'] ?? null,
                'max_frequency' => $data['max_frequency'],
                'frequency_note' => $data['frequency_note'] ?? null,
                'variable_hours' => (bool) ($data['variable_hours'] ?? false),
                'other_days_by_arrangement' => (bool) ($data['other_days_by_arrangement'] ?? false),
                'training_availability' => TrainingAvailability::from((string) $data['training_availability']),
                // "Posso apoiar outros membros" is the top answer of the level question.
                'can_support_others' => $level === Level::Mentor,
            ]);
            $volunteer->consent_accepted_at = now();
            $volunteer->consent_version = (int) $data['consent_version'];
            $volunteer->previously_rejected_at = RejectedRegistration::lastRejectionFor($phone);
            $volunteer->save();

            // The form asks one overall level; it is copied to every chosen role and the
            // coordinator adjusts it per role (FR-004).
            $preferred = $data['preferred_role_id'] ?? null;

            /** @var list<array{team_role_id: int}> $skills */
            $skills = $data['skills'] ?? [];
            foreach ($skills as $skill) {
                $volunteer->skills()->create([
                    'team_role_id' => $skill['team_role_id'],
                    'interested' => true,
                    'has_experience' => in_array((int) $skill['team_role_id'], $experienceRoleIds, true),
                    'level' => $level,
                    'is_preferred' => (int) $skill['team_role_id'] === (int) $preferred,
                ]);
            }

            /** @var list<array{weekday: int, period: string}> $availability */
            $availability = $data['availability'] ?? [];
            foreach (collect($availability)->unique(fn (array $slot) => $slot['weekday'].'|'.$slot['period']) as $slot) {
                $volunteer->availabilities()->create($slot);
            }

            return $volunteer;
        });

        Notification::send(
            User::query()->where('role', UserRole::Coordinator)->get(),
            new RegistrationSubmitted($volunteer),
        );

        return $volunteer;
    }
}
