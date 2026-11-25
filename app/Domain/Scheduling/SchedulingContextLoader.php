<?php

namespace App\Domain\Scheduling;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Absence;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Volunteer;
use App\Models\VolunteerSkill;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * Builds the in-memory scheduling context from the database. Bookings cover the whole
 * calendar months of the period (monthly caps) and the 4 weeks before it (recent load).
 */
class SchedulingContextLoader
{
    private const RECENT_DAYS = 28;

    public function forPeriod(CarbonImmutable $from, CarbonImmutable $to, ?Volunteer $include = null): SchedulingContext
    {
        $windowFrom = $from->startOfMonth()->min($from->subDays(self::RECENT_DAYS));
        $windowTo = $to->endOfMonth();

        $volunteers = Volunteer::query()
            ->schedulable()
            ->with(['skills', 'availabilities'])
            ->get();

        if ($include !== null && ! $volunteers->contains($include)) {
            $volunteers->push($include->loadMissing(['skills', 'availabilities']));
        }

        $absences = Absence::query()
            ->whereIn('volunteer_id', $volunteers->modelKeys())
            ->where('ends_on', '>=', $windowFrom->toDateString())
            ->where('starts_on', '<=', $windowTo->toDateString())
            ->get()
            ->map(fn (Absence $absence) => [
                'volunteerId' => $absence->volunteer_id,
                'from' => $absence->starts_on->toDateString(),
                'to' => $absence->ends_on->toDateString(),
            ])
            ->all();

        $bookings = Assignment::query()
            ->active()
            ->whereHas('event', fn ($query) => $query
                ->where('status', EventStatus::Scheduled)
                ->betweenDates($windowFrom, $windowTo))
            ->with(['event', 'position'])
            ->get()
            ->map(fn (Assignment $assignment) => Booking::forSlot($assignment->volunteer_id, $this->slotFor($assignment->position, $assignment->event), $assignment->id))
            ->all();

        return new SchedulingContext(
            array_values($volunteers->map(fn (Volunteer $volunteer) => $this->profileOf($volunteer))->all()),
            array_values($absences),
            array_values($bookings),
            $this->lastServedBefore($windowFrom),
        );
    }

    /**
     * @return array<int, string> volunteer id => last local date served before the window
     */
    private function lastServedBefore(CarbonImmutable $windowFrom): array
    {
        return Assignment::query()
            ->active()
            ->join('events', 'events.id', '=', 'assignments.event_id')
            ->where('events.status', EventStatus::Scheduled)
            ->where('events.date', '<', $windowFrom->toDateString())
            ->groupBy('assignments.volunteer_id')
            ->selectRaw('assignments.volunteer_id, max(events.date) as last_date')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->volunteer_id => substr((string) $row->last_date, 0, 10)])
            ->all();
    }

    public function profileOf(Volunteer $volunteer): VolunteerProfile
    {
        $volunteer->loadMissing(['skills', 'availabilities']);

        return new VolunteerProfile(
            id: $volunteer->id,
            isActive: $volunteer->isSchedulable(),
            frequency: $volunteer->max_frequency,
            variableHours: $volunteer->variable_hours,
            canSupportOthers: $volunteer->can_support_others,
            availability: $volunteer->availabilities
                ->mapWithKeys(fn ($slot) => ["{$slot->weekday}|{$slot->period->value}" => true])
                ->all(),
            levels: $volunteer->skills
                ->mapWithKeys(fn (VolunteerSkill $skill) => [$skill->team_role_id => $skill->level])
                ->all(),
            preferredRoleIds: array_values($volunteer->skills
                ->where('is_preferred', true)
                ->pluck('team_role_id')
                ->map(fn ($id): int => (int) $id)
                ->all()),
        );
    }

    public function slotFor(Position $position, ?Event $event = null, ?int $quantity = null): Slot
    {
        $event ??= $position->event;
        $position->loadMissing('teamRole');

        return new Slot(
            positionId: $position->id,
            eventId: $event->id,
            roleId: $position->team_role_id,
            minLevel: $position->min_level,
            date: $event->date->toDateString(),
            weekday: $event->date->dayOfWeekIso,
            period: $event->period->value,
            busyFrom: $event->arrival_at->getTimestamp(),
            busyUntil: $event->teardown_until->getTimestamp(),
            quantity: $quantity ?? $position->quantity,
            label: $event->name.' · '.$event->date->format('d/m').' '.LocalTime::timeOf($event->starts_at).' · '.$position->teamRole->name,
            checksWeeklyAvailability: $event->type !== EventType::Special,
        );
    }

    /**
     * Positions of the schedule's scheduled events (or just one of them), with the people still needed.
     *
     * @return list<Slot>
     */
    public function openSlots(Schedule $schedule, ?Event $only = null): array
    {
        $events = $schedule->events()
            ->scheduled()
            ->when($only, fn ($query, Event $event) => $query->whereKey($event->id))
            ->with(['positions.teamRole', 'positions.assignments' => fn ($query) => $query->active()])
            ->get();

        $slots = [];

        foreach ($events as $event) {
            foreach ($event->positions as $position) {
                $missing = $position->quantity - $position->assignments->count();

                if ($missing > 0) {
                    $slots[] = $this->slotFor($position, $event, $missing);
                }
            }
        }

        return $slots;
    }
}
