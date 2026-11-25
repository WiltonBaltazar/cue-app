<?php

namespace App\Actions\Events;

use App\Enums\EventType;
use App\Enums\Period;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\TeamRole;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a special event or edits any event. Editing a generated occurrence marks it
 * customised, so later template changes leave it alone (FR-009). People already assigned
 * keep their places; a new date or time is rechecked and announced (RescheduleShifts).
 */
class SaveEvent
{
    public function __construct(private RescheduleShifts $shifts) {}

    /**
     * @param  array{name: string, date: string, arrival_time: string, start_time: string, end_time: string, teardown_minutes: int, location: string, positions: list<array{team_role_id: int, quantity: int, min_level: string|null}>}  $data
     */
    public function handle(?Event $event, array $data): Event
    {
        if ($event?->isCancelled()) {
            throw ValidationException::withMessages(['event' => 'Um evento cancelado não pode ser alterado.']);
        }

        $timesBefore = $event === null ? null : $this->times($event);
        $clashesBefore = $event === null ? [] : $this->shifts->clashes($event);

        $event = DB::transaction(function () use ($event, $data, $timesBefore, $clashesBefore): Event {
            $event ??= new Event(['type' => EventType::Special]);

            if ($event->exists) {
                $this->ensureDateIsPossible($event, $data['date']);
            }

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

            // A schedule of its own follows the event's date.
            Schedule::query()->where('event_id', $event->id)->update([
                'starts_on' => $data['date'],
                'ends_on' => $data['date'],
            ]);

            $this->syncPositions($event, $data['positions']);

            if ($timesBefore !== null && $timesBefore !== $this->times($event)) {
                $this->shifts->recheck($event, $clashesBefore, $timesBefore['arrival'] !== $this->times($event)['arrival']);
            }

            return $event;
        });

        if ($timesBefore !== null && $timesBefore !== $this->times($event)) {
            $this->shifts->notify($event);
        }

        return $event;
    }

    /**
     * What people with a shift are told about.
     *
     * @return array{arrival: int, start: int, end: int}
     */
    private function times(Event $event): array
    {
        return [
            'arrival' => $event->arrival_at->getTimestamp(),
            'start' => $event->starts_at->getTimestamp(),
            'end' => $event->ends_at->getTimestamp(),
        ];
    }

    private function ensureDateIsPossible(Event $event, string $date): void
    {
        if ($event->date->toDateString() === $date) {
            return;
        }

        $taken = $event->activity_template_id !== null && Event::query()
            ->where('activity_template_id', $event->activity_template_id)
            ->where('date', $date)
            ->whereKeyNot($event->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['date' => 'Já existe uma ocorrência desta actividade nessa data.']);
        }

        // Its people belong to a schedule for a period: outside it they would be out of sight.
        $leavesSchedule = Schedule::query()
            ->forRange()
            ->whereHas('assignments', fn ($query) => $query->where('event_id', $event->id))
            ->where(fn ($query) => $query->where('starts_on', '>', $date)->orWhere('ends_on', '<', $date))
            ->exists();

        if ($leavesSchedule) {
            throw ValidationException::withMessages(['date' => 'Este evento tem pessoas numa escala que não cobre essa data. Retire-as da escala primeiro.']);
        }
    }

    /**
     * Updates positions in place, because assignments point to them. Places still taken are
     * never removed from under someone.
     *
     * @param  list<array{team_role_id: int, quantity: int, min_level: string|null}>  $positions
     */
    private function syncPositions(Event $event, array $positions): void
    {
        $existing = $event->positions()->with('assignments')->get()->keyBy('team_role_id');

        foreach ($positions as $index => $data) {
            $position = $existing->pull($data['team_role_id']) ?? $event->positions()->make(['team_role_id' => $data['team_role_id']]);
            $taken = $position->exists ? $this->peopleIn($position) : 0;

            if ($data['quantity'] < $taken) {
                throw ValidationException::withMessages(["positions.{$index}.quantity" => "Já há {$taken} escalados nesta função. Retire alguém da escala primeiro."]);
            }

            $position->fill(['quantity' => $data['quantity'], 'min_level' => $data['min_level']])->save();
        }

        foreach ($existing as $position) {
            if ($this->peopleIn($position) > 0) {
                $role = TeamRole::query()->whereKey($position->team_role_id)->value('name');

                throw ValidationException::withMessages(['positions' => "Há pessoas escaladas em {$role}. Retire-as da escala antes de remover a função."]);
            }

            // Only declined or replaced shifts are left here; the place they were for is gone.
            $position->assignments->each(fn (Assignment $assignment) => $assignment->delete());
            $position->delete();
        }

        $event->unsetRelation('positions');
    }

    private function peopleIn(Position $position): int
    {
        return $position->assignments()->active()->count();
    }
}
