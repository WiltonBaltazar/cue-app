<?php

namespace Tests\Support;

use App\Enums\EventType;
use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Models\Event;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\TeamRole;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * A Sunday-morning service with 2 camera places and volunteers who fit, for feature tests.
 */
final class ScheduleScenario
{
    public function __construct(
        public TeamRole $camera,
        public Event $event,
        public Position $position,
        public Schedule $schedule,
    ) {}

    public static function create(): self
    {
        $camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
        $sunday = LocalTime::today()->next(CarbonImmutable::SUNDAY);

        $event = Event::factory()->create([
            'name' => 'Culto de Domingo',
            'type' => EventType::Special,
            'date' => $sunday->toDateString(),
            'period' => Period::Morning,
            'arrival_at' => LocalTime::toUtc($sunday, '07:30'),
            'starts_at' => LocalTime::toUtc($sunday, '08:30'),
            'ends_at' => LocalTime::toUtc($sunday, '11:30'),
            'teardown_until' => LocalTime::toUtc($sunday, '12:00'),
        ]);

        $position = $event->positions()->create(['team_role_id' => $camera->id, 'quantity' => 2]);

        $schedule = Schedule::factory()->create([
            'starts_on' => $sunday->startOfMonth()->toDateString(),
            'ends_on' => $sunday->endOfMonth()->toDateString(),
        ]);

        return new self($camera, $event, $position, $schedule);
    }

    public function volunteer(Level $level = Level::Intermediate, array $slots = [[7, Period::Morning]], array $attributes = []): Volunteer
    {
        return Volunteer::factory()
            ->active()
            ->withSkill($this->camera, $level)
            ->availableOn($slots)
            ->create(['max_frequency' => Frequency::Weekly, ...$attributes]);
    }
}
