<?php

namespace App\Actions\Volunteers;

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\TrainingAvailability;
use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A coordinator who also serves gets a volunteer profile of their own, so schedules
 * can place them like anyone else. There is no review: they are approved by being a
 * coordinator, and they choose their own levels.
 */
class CreateServingProfile
{
    /**
     * @param  array<string, mixed>  $data  validated ServingProfileController input
     */
    public function handle(User $coordinator, array $data): Volunteer
    {
        if ($coordinator->volunteer()->exists()) {
            throw ValidationException::withMessages(['form' => 'Já tem um perfil de voluntário.']);
        }

        /** @var list<array{team_role_id: int, level: string}> $skills */
        $skills = $data['skills'];
        /** @var list<array{weekday: int, period: string}> $availability */
        $availability = $data['availability'] ?? [];
        $fullName = (string) $data['full_name'];

        return DB::transaction(function () use ($coordinator, $data, $skills, $availability, $fullName): Volunteer {
            $volunteer = new Volunteer([
                'user_id' => $coordinator->id,
                'full_name' => $fullName,
                'birth_date' => $data['birth_date'],
                'phone_has_whatsapp' => true,
                'max_frequency' => Frequency::from((string) $data['max_frequency']),
                'training_availability' => TrainingAvailability::Regular,
                'can_support_others' => collect($skills)->contains(fn (array $skill) => $skill['level'] === Level::Mentor->value),
            ]);
            $volunteer->status = VolunteerStatus::Active;
            $volunteer->reviewed_by = $coordinator->id;
            $volunteer->reviewed_at = now();
            $volunteer->consent_accepted_at = now();
            $volunteer->consent_version = (int) $data['consent_version'];
            $volunteer->save();

            $preferred = $data['preferred_role_id'] ?? null;

            foreach ($skills as $skill) {
                $volunteer->skills()->create([
                    'team_role_id' => $skill['team_role_id'],
                    'interested' => true,
                    'has_experience' => $skill['level'] !== Level::Beginner->value,
                    'level' => Level::from($skill['level']),
                    'is_preferred' => (int) $skill['team_role_id'] === (int) $preferred,
                ]);
            }

            foreach (collect($availability)->unique(fn (array $slot) => $slot['weekday'].'|'.$slot['period']) as $slot) {
                $volunteer->availabilities()->create($slot);
            }

            if ($coordinator->name !== $fullName) {
                $coordinator->forceFill(['name' => $fullName])->save();
            }

            $coordinator->setRelation('volunteer', $volunteer);

            return $volunteer;
        });
    }
}
