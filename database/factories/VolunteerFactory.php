<?php

namespace Database\Factories;

use App\Enums\Frequency;
use App\Enums\GuardianConsentMethod;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
use App\Enums\VolunteerStatus;
use App\Models\ConsentVersion;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Volunteer>
 */
class VolunteerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fn (array $attributes) => User::query()->whereKey($attributes['user_id'])->value('name') ?? fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'neighbourhood' => fake()->city(),
            'education_level' => 'Ensino secundário',
            'training' => null,
            'commitments' => null,
            'motivations' => 'Quero servir a igreja com os meus talentos.',
            'comments' => null,
            'max_frequency' => Frequency::TwoThreeMonthly,
            'variable_hours' => false,
            'training_availability' => TrainingAvailability::Regular,
            'can_support_others' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Volunteer $volunteer): void {
            $volunteer->status ??= VolunteerStatus::Pending;
            $volunteer->consent_accepted_at ??= now();
            $volunteer->consent_version ??= max(1, ConsentVersion::currentNumber());
        });
    }

    public function status(VolunteerStatus $status): static
    {
        return $this->afterMaking(fn (Volunteer $volunteer) => $volunteer->status = $status);
    }

    public function pending(): static
    {
        return $this->status(VolunteerStatus::Pending);
    }

    public function active(): static
    {
        return $this->status(VolunteerStatus::Active);
    }

    /**
     * Aged 15, with guardian contact but no recorded guardian consent yet.
     */
    public function minor(): static
    {
        return $this->state(fn (array $attributes) => [
            'birth_date' => now()->subYears(15)->subMonth()->format('Y-m-d'),
            'guardian_name' => fake()->name(),
            'guardian_phone' => '+25882'.fake()->numerify('#######'),
        ]);
    }

    public function withGuardianConsent(?User $coordinator = null): static
    {
        return $this->afterMaking(function (Volunteer $volunteer) use ($coordinator): void {
            $volunteer->guardian_consent_confirmed_by = $coordinator?->id;
            $volunteer->guardian_consent_confirmed_at = now();
            $volunteer->guardian_consent_method = GuardianConsentMethod::InPerson;
        });
    }

    /**
     * @param  list<array{0: int, 1: Period}>  $slots  weekday × period
     */
    public function availableOn(array $slots): static
    {
        return $this->afterCreating(function (Volunteer $volunteer) use ($slots): void {
            foreach ($slots as [$weekday, $period]) {
                $volunteer->availabilities()->create(['weekday' => $weekday, 'period' => $period]);
            }
        });
    }

    public function withSkill(TeamRole $role, Level $level = Level::Beginner, bool $preferred = false): static
    {
        return $this->afterCreating(function (Volunteer $volunteer) use ($role, $level, $preferred): void {
            $volunteer->skills()->create([
                'team_role_id' => $role->id,
                'interested' => true,
                'has_experience' => $level !== Level::Beginner,
                'level' => $level,
                'is_preferred' => $preferred,
            ]);
        });
    }
}
