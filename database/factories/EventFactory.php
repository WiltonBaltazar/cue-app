<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Enums\Level;
use App\Enums\Period;
use App\Models\Event;
use App\Models\TeamRole;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state: a special event next week, 18:00–20:00 local.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = LocalTime::today()->addWeek();

        return [
            'activity_template_id' => null,
            'name' => 'Conferência de jovens',
            'type' => EventType::Special,
            'date' => $date->toDateString(),
            'period' => Period::Evening,
            'arrival_at' => LocalTime::toUtc($date, '17:00'),
            'starts_at' => LocalTime::toUtc($date, '18:00'),
            'ends_at' => LocalTime::toUtc($date, '20:00'),
            'teardown_until' => LocalTime::toUtc($date, '20:30'),
            'location' => 'Templo sede',
        ];
    }

    public function withPosition(TeamRole $role, int $quantity = 1, ?Level $minLevel = null): static
    {
        return $this->afterCreating(function (Event $event) use ($role, $quantity, $minLevel): void {
            $event->positions()->create([
                'team_role_id' => $role->id,
                'quantity' => $quantity,
                'min_level' => $minLevel,
            ]);
        });
    }
}
