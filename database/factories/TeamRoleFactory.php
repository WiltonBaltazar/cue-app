<?php

namespace Database\Factories;

use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamRole>
 */
class TeamRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
