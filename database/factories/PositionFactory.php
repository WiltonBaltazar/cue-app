<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Position;
use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'team_role_id' => fn () => TeamRole::query()->value('id') ?? TeamRole::factory(),
            'quantity' => 1,
            'min_level' => null,
        ];
    }
}
