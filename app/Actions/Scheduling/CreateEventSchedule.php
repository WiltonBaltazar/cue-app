<?php

namespace App\Actions\Scheduling;

use App\Models\Event;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Gives one event a schedule of its own (e.g. an extraordinary event), so it can be staffed
 * and published without a whole period. Returns the existing one when there already is one.
 */
class CreateEventSchedule
{
    public function handle(Event $event, User $coordinator): Schedule
    {
        $existing = Schedule::query()->where('event_id', $event->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($event->isCancelled() || $event->starts_at->isPast()) {
            throw ValidationException::withMessages(['event' => 'Só pode criar a escala de um evento que ainda vai acontecer.']);
        }

        $range = Schedule::forEvent($event);

        if ($range !== null && $range->assignments()->where('event_id', $event->id)->exists()) {
            throw ValidationException::withMessages(['event' => "Este evento já tem pessoas na escala de {$range->starts_on->format('d/m')} a {$range->ends_on->format('d/m')}. Escolha as pessoas lá."]);
        }

        $date = $event->date->toDateString();

        return Schedule::query()->create([
            'starts_on' => $date,
            'ends_on' => $date,
            'event_id' => $event->id,
            'created_by' => $coordinator->id,
        ]);
    }
}
