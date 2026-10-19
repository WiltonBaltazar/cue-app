<?php

namespace Database\Factories;

use App\Enums\Level;
use App\Enums\Period;
use App\Models\ActivityTemplate;
use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTemplate>
 */
class ActivityTemplateFactory extends Factory
{
    /**
     * Define the model's default state: a Sunday-morning service.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Culto de Domingo',
            'weekday' => 7,
            'interval_weeks' => 1,
            'starts_on' => now()->toDateString(),
            'period' => Period::Morning,
            'arrival_time' => '07:30',
            'start_time' => '08:30',
            'end_time' => '11:30',
            'teardown_minutes' => 30,
            'location' => 'Templo sede',
            'is_active' => true,
        ];
    }

    public function withPosition(TeamRole $role, int $quantity = 1, ?Level $minLevel = null): static
    {
        return $this->afterCreating(function (ActivityTemplate $template) use ($role, $quantity, $minLevel): void {
            $template->positions()->create([
                'team_role_id' => $role->id,
                'quantity' => $quantity,
                'min_level' => $minLevel,
            ]);
        });
    }
}
