<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'position_id' => Position::factory(),
            'event_id' => fn (array $attributes) => Position::query()->whereKey($attributes['position_id'])->value('event_id'),
            'volunteer_id' => Volunteer::factory()->active(),
            'source' => Assignment::SOURCE_AUTO,
            'is_locked' => false,
        ];
    }
}
