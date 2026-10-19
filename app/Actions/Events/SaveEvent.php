<?php

namespace App\Actions\Events;

use App\Enums\EventType;
use App\Enums\Period;
use App\Models\Event;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a special event or edits any event. Editing a generated occurrence marks it
 * customised, so later template changes leave it alone (FR-009).
 */
class SaveEvent
{
    /**
     * @param  array{name: string, date: string, arrival_time: string, start_time: string, end_time: string, teardown_minutes: int, location: string, positions: list<array{team_role_id: int, quantity: int, min_level: string|null}>}  $data
     */
    public function handle(?Event $event, array $data): Event
    {
        if ($event?->isCancelled()) {
            throw ValidationException::withMessages(['event' => 'Um evento cancelado não pode ser alterado.']);
        }

        return DB::transaction(function () use ($event, $data): Event {
            $event ??= new Event(['type' => EventType::Special]);

            $endsAt = LocalTime::toUtc($data['date'], $data['end_time']);

            $event->fill([
                'name' => $data['name'],
                'date' => $data['date'],
                'period' => Period::fromTime(CarbonImmutable::parse("{$data['date']} {$data['start_time']}")),
                'arrival_at' => LocalTime::toUtc($data['date'], $data['arrival_time']),
                'starts_at' => LocalTime::toUtc($data['date'], $data['start_time']),
                'ends_at' => $endsAt,
                'teardown_until' => $endsAt->addMinutes($data['teardown_minutes']),
                'location' => $data['location'],
            ]);

            if ($event->exists && $event->type === EventType::Recurring) {
                $event->is_customized = true;
            }

            $event->save();

            $event->positions()->delete();
            foreach ($data['positions'] as $position) {
                $event->positions()->create([
                    'team_role_id' => $position['team_role_id'],
                    'quantity' => $position['quantity'],
                    'min_level' => $position['min_level'],
                ]);
            }

            return $event;
        });
    }
}
