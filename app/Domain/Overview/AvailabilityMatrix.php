<?php

namespace App\Domain\Overview;

use App\Enums\Level;
use App\Enums\Period;
use App\Enums\VolunteerStatus;
use App\Models\Availability;
use App\Models\Event;
use App\Models\Position;
use App\Models\TeamRole;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Support\Collection;

/**
 * How many active volunteers are usually available per weekday × period, optionally only
 * those with one role at a minimum level (US5-1), and which roles are short: an upcoming
 * event needs more people in a role than there are available volunteers who could take
 * it (US5-3).
 */
final class AvailabilityMatrix
{
    /** Upcoming events looked at to work out demand. */
    public const DEMAND_DAYS = 60;

    /**
     * @return array{
     *     weekdays: list<int>,
     *     periods: list<string>,
     *     counts: array<string, int>,
     *     demand: array<string, int>,
     *     shortages: list<string>,
     *     shortageCells: array<string, list<string>>,
     * }
     */
    public function build(?TeamRole $role = null, ?Level $minLevel = null): array
    {
        $volunteers = Volunteer::query()
            ->where('status', VolunteerStatus::Active)
            ->with(['availabilities', 'skills'])
            ->get();

        $counts = [];

        foreach ($volunteers as $volunteer) {
            if ($role !== null && ! $this->qualifies($volunteer, $role->id, $minLevel)) {
                continue;
            }

            foreach ($volunteer->availabilities as $availability) {
                $key = self::key($availability->weekday, $availability->period);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        ksort($counts);

        [$demand, $shortageCells] = $this->demand($volunteers, $role);

        $shortages = array_values(array_unique(array_merge([], ...array_values($shortageCells))));
        sort($shortages);

        return [
            'weekdays' => array_keys(Availability::WEEKDAYS),
            'periods' => array_map(fn (Period $period) => $period->value, Period::cases()),
            'counts' => $counts,
            'demand' => $demand,
            'shortages' => $shortages,
            'shortageCells' => $shortageCells,
        ];
    }

    public static function key(int $weekday, Period $period): string
    {
        return "{$weekday}.{$period->value}";
    }

    /**
     * Demand per cell (the most people one event in that weekday × period needs, in the
     * filtered role or overall) and the role slugs short in each cell. A position is short
     * when its places outnumber the volunteers available then with the role and level.
     *
     * @param  Collection<int, Volunteer>  $volunteers
     * @return array{0: array<string, int>, 1: array<string, list<string>>}
     */
    private function demand(Collection $volunteers, ?TeamRole $role): array
    {
        $today = LocalTime::today();

        $events = Event::query()
            ->scheduled()
            ->betweenDates($today, $today->addDays(self::DEMAND_DAYS))
            ->with('positions.teamRole')
            ->get();

        $demand = [];
        $shortageCells = [];

        foreach ($events as $event) {
            $key = self::key($event->date->dayOfWeekIso, $event->period);
            $positions = $role === null
                ? $event->positions
                : $event->positions->where('team_role_id', $role->id);

            $demand[$key] = max($demand[$key] ?? 0, (int) $positions->sum('quantity'));

            foreach ($positions as $position) {
                if ($position->quantity > $this->supply($volunteers, $position, $event)) {
                    $shortageCells[$key][] = $position->teamRole->slug;
                }
            }
        }

        $shortageCells = array_map(fn (array $slugs) => array_values(array_unique($slugs)), $shortageCells);
        ksort($demand);
        ksort($shortageCells);

        return [array_filter($demand), $shortageCells];
    }

    /**
     * @param  Collection<int, Volunteer>  $volunteers
     */
    private function supply(Collection $volunteers, Position $position, Event $event): int
    {
        return $volunteers
            ->filter(fn (Volunteer $volunteer) => $this->qualifies($volunteer, $position->team_role_id, $position->min_level)
                && $volunteer->availabilities->contains(fn (Availability $availability) => $availability->weekday === $event->date->dayOfWeekIso
                    && $availability->period === $event->period))
            ->count();
    }

    private function qualifies(Volunteer $volunteer, int $roleId, ?Level $minLevel): bool
    {
        $skill = $volunteer->skills->firstWhere('team_role_id', $roleId);

        return $skill !== null && $skill->level->meets($minLevel);
    }
}
