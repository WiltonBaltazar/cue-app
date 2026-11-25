<?php

namespace App\Domain\Overview;

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContext;
use App\Domain\Scheduling\SchedulingContextLoader;
use App\Domain\Scheduling\Violation;
use App\Enums\AssignmentStatus;
use App\Enums\VolunteerStatus;
use App\Models\Absence;
use App\Models\Availability;
use App\Models\Event;
use App\Models\Position;
use App\Models\Volunteer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * By name, for coordinators (FR-031): who cannot serve on a date, and who has no shift in a
 * month and why. Reasons of absences are never included (FR-026).
 */
final class VolunteerAvailability
{
    /** Open dates listed for someone who could still be placed. */
    private const OPEN_DATES = 3;

    public function __construct(
        private SchedulingContextLoader $loader,
        private ConstraintChecker $checker,
    ) {}

    /**
     * Absent and paused people come before those who never serve on that weekday.
     *
     * @return array{
     *     date: string,
     *     unavailable: list<array{volunteerId: int, name: string, reason: string}>,
     *     available: list<array{volunteerId: int, name: string, when: string}>,
     * }
     */
    public function on(CarbonImmutable $date): array
    {
        $day = $date->toDateString();
        $volunteers = $this->volunteers();
        $absences = Absence::query()
            ->whereIn('volunteer_id', $volunteers->modelKeys())
            ->where('starts_on', '<=', $day)
            ->where('ends_on', '>=', $day)
            ->get()
            ->keyBy('volunteer_id');

        $unavailable = [];
        $otherDays = [];
        $available = [];

        foreach ($volunteers as $volunteer) {
            $person = ['volunteerId' => $volunteer->id, 'name' => $volunteer->full_name];
            $absence = $absences->get($volunteer->id);
            $periods = $volunteer->availabilities
                ->where('weekday', $date->dayOfWeekIso)
                ->map(fn (Availability $slot): string => $slot->period->label());

            if (! $volunteer->isSchedulable()) {
                $unavailable[] = [...$person, 'reason' => 'Em pausa'];
            } elseif ($absence !== null) {
                $unavailable[] = [...$person, 'reason' => $this->absenceReason($absence)];
            } elseif ($volunteer->variable_hours) {
                $available[] = [...$person, 'when' => 'Horários variáveis'];
            } elseif ($periods->isEmpty()) {
                $otherDays[] = [...$person, 'reason' => 'Não costuma poder neste dia da semana'];
            } else {
                $available[] = [...$person, 'when' => $periods->join(', ', ' e ')];
            }
        }

        return ['date' => $day, 'unavailable' => [...$unavailable, ...$otherDays], 'available' => $available];
    }

    /**
     * @return array{
     *     month: string,
     *     events: int,
     *     serving: int,
     *     notServing: list<array{volunteerId: int, name: string, reason: string}>,
     * }
     */
    public function notServing(CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $events = Event::query()
            ->scheduled()
            ->betweenDates($from, $to)
            ->with(['positions.teamRole', 'positions.assignments'])
            ->orderBy('starts_at')
            ->get();

        $assignments = $events->flatMap(fn (Event $event) => $event->positions->flatMap->assignments);
        $serving = $assignments->where('status', AssignmentStatus::Confirmed)->pluck('volunteer_id')->unique();
        $left = $assignments->pluck('volunteer_id')->diff($serving)->unique();

        // Only the people and their absences: whoever is listed has no shift in the month.
        $context = $this->loader->forPeriod($from, $to);
        $notServing = [];

        foreach ($this->volunteers() as $volunteer) {
            if (! $serving->contains($volunteer->id)) {
                $notServing[] = [
                    'volunteerId' => $volunteer->id,
                    'name' => $volunteer->full_name,
                    'reason' => match (true) {
                        ! $volunteer->isSchedulable() => 'Em pausa',
                        $left->contains($volunteer->id) => 'Foi escalado, mas saiu (recusa, ausência ou troca)',
                        default => $this->whyNot($volunteer, $events, $context),
                    },
                ];
            }
        }

        return [
            'month' => $month->format('Y-m'),
            'events' => $events->count(),
            'serving' => $serving->count(),
            'notServing' => $notServing,
        ];
    }

    /**
     * The first rule that keeps them out of every place of the month, or where they still fit.
     *
     * @param  Collection<int, Event>  $events
     */
    private function whyNot(Volunteer $volunteer, Collection $events, SchedulingContext $context): string
    {
        $profile = $this->loader->profileOf($volunteer);

        if ($profile->levels === []) {
            return 'Não tem nenhuma função';
        }

        $places = [];
        foreach ($events as $event) {
            foreach ($event->positions as $position) {
                $places[] = [
                    'event' => $event,
                    'open' => $this->isOpen($position, $event),
                    'violations' => $this->checker->check($profile, $this->loader->slotFor($position, $event), $context, automatic: false),
                ];
            }
        }

        $blocks = [
            [Violation::Role, 'Nenhum evento do mês pede as funções que tem'],
            [Violation::Availability, 'Não costuma poder nos dias e períodos em que há lugares na sua função'],
            [Violation::Absence, 'Ausente nas datas em que podia servir'],
            [Violation::MinLevel, 'Nível abaixo do mínimo pedido'],
        ];

        foreach ($blocks as [$violation, $reason]) {
            $places = array_filter($places, fn (array $place): bool => ! in_array($violation, $place['violations'], true));

            if ($places === []) {
                return $reason;
            }
        }

        $dates = array_unique(array_map(
            fn (array $place): string => $place['event']->date->format('d/m'),
            array_filter($places, fn (array $place): bool => $place['open']),
        ));

        return $dates === []
            ? 'Podia servir, mas os lugares são de outras pessoas'
            : 'Há lugar por preencher em '.implode(', ', array_slice($dates, 0, self::OPEN_DATES));
    }

    private function isOpen(Position $position, Event $event): bool
    {
        return $event->starts_at->isFuture()
            && $position->assignments->where('status', AssignmentStatus::Confirmed)->count() < $position->quantity;
    }

    private function absenceReason(Absence $absence): string
    {
        return $absence->starts_on->equalTo($absence->ends_on)
            ? 'Ausente neste dia'
            : "Ausente de {$absence->starts_on->format('d/m')} a {$absence->ends_on->format('d/m')}";
    }

    /**
     * Active and paused people, by name.
     *
     * @return Collection<int, Volunteer>
     */
    private function volunteers(): Collection
    {
        return Volunteer::query()
            ->whereIn('status', [VolunteerStatus::Active, VolunteerStatus::Inactive])
            ->whereNull('removal_requested_at')
            ->with(['availabilities', 'skills'])
            ->orderBy('full_name')
            ->get();
    }
}
