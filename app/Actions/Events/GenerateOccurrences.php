<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\ActivityTemplate;
use App\Models\Event;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Keeps recurring activities' occurrences in step with their templates for the configured
 * horizon (US2, FR-008, FR-009):
 * - creates missing occurrences of active templates; where the same activity was already
 *   added by hand as an extraordinary event, that event becomes the occurrence instead;
 * - re-syncs future, non-customised, scheduled occurrences with the template;
 * - removes future, non-customised, scheduled occurrences that no longer match
 *   (weekday changed, template deactivated, or a duplicate of an event added by hand).
 * Past, customised and cancelled occurrences, and any occurrence that already has
 * assignments, are never touched.
 */
class GenerateOccurrences
{
    public function __construct(private Settings $settings) {}

    /**
     * @return int occurrences created
     */
    public function handle(?ActivityTemplate $only = null): int
    {
        $today = LocalTime::today();
        $horizon = $today->addMonths($this->settings->eventHorizonMonths());

        $templates = $only !== null
            ? collect([$only->fresh(['positions'])])->filter()
            : ActivityTemplate::query()->with('positions')->get();

        $created = 0;

        foreach ($templates as $template) {
            $created += DB::transaction(fn (): int => $this->syncTemplate($template, $today, $horizon));
        }

        return $created;
    }

    /**
     * Creates the occurrences still missing between two dates, however far past the horizon,
     * so a schedule never covers an empty calendar. Existing events are left as they are.
     *
     * @return int occurrences created
     */
    public function fillBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        // Calendar dates only: schedule dates are UTC midnights, today is a Maputo midnight.
        $firstDay = max($from->toDateString(), LocalTime::today()->toDateString());
        $lastDay = $to->toDateString();

        return DB::transaction(function () use ($firstDay, $lastDay): int {
            $created = 0;
            $templates = ActivityTemplate::query()->where('is_active', true)->with('positions')->get();

            foreach ($templates as $template) {
                $byHand = $this->addedByHand($template, $firstDay, $lastDay);
                $this->removeStale($template, CarbonImmutable::parse($firstDay), $byHand);
                $existing = $template->events()
                    ->betweenDates(CarbonImmutable::parse($firstDay), CarbonImmutable::parse($lastDay))
                    ->get()
                    ->keyBy(fn (Event $event) => $event->date->toDateString());

                for ($date = CarbonImmutable::parse($firstDay); $date->toDateString() <= $lastDay; $date = $date->addDay()) {
                    if (! $template->occursOn($date) || $existing->has($date->toDateString())) {
                        continue;
                    }

                    if (isset($byHand[$date->toDateString()])) {
                        $this->adopt($byHand[$date->toDateString()], $template);
                    } else {
                        $this->createOccurrence($template, $date);
                        $created++;
                    }
                }
            }

            return $created;
        });
    }

    private function syncTemplate(ActivityTemplate $template, CarbonImmutable $today, CarbonImmutable $horizon): int
    {
        $this->removeStale($template, $today, $this->addedByHand($template, $today->toDateString(), '9999-12-31'));

        if (! $template->is_active) {
            return 0;
        }

        $existing = $template->events()
            ->where('date', '>=', $today->toDateString())
            ->withCount('assignments')
            ->get()
            ->keyBy(fn (Event $event) => $event->date->toDateString());

        $created = 0;
        $date = $template->starts_on->greaterThan($today) ? $template->starts_on : $today;
        $byHand = $this->addedByHand($template, $date->toDateString(), $horizon->toDateString());

        for (; $date->lte($horizon); $date = $date->addDay()) {
            if (! $template->occursOn($date)) {
                continue;
            }

            $event = $existing->get($date->toDateString());

            if ($event !== null) {
                continue;
            }

            if (isset($byHand[$date->toDateString()])) {
                $this->adopt($byHand[$date->toDateString()], $template);
            } else {
                $this->createOccurrence($template, $date);
                $created++;
            }
        }

        // Including those past the horizon, which a schedule may have created.
        foreach ($existing as $event) {
            if (! $event->is_customized && ! $event->isCancelled() && $event->assignments_count === 0) {
                $this->applyTemplate($event, $template, $event->date);
            }
        }

        return $created;
    }

    /**
     * Extraordinary events of the same name by date, e.g. services set up by hand before their
     * recurring activity existed. Generating there would put the service twice in a schedule.
     *
     * @return array<string, Event>
     */
    private function addedByHand(ActivityTemplate $template, string $from, string $to): array
    {
        return Event::query()
            ->where('type', EventType::Special)
            ->where('status', '!=', EventStatus::Cancelled)
            ->whereBetween('date', [$from, $to])
            ->get()
            // Compared here: SQLite's lower() leaves accented capitals ("PÚBLICO") as they are.
            ->filter(fn (Event $event): bool => mb_strtolower(trim($event->name)) === mb_strtolower(trim($template->name)))
            ->keyBy(fn (Event $event) => $event->date->toDateString())
            ->all();
    }

    /**
     * An event added by hand on one of the activity's dates becomes its occurrence. It keeps its
     * own times, positions and people (customised), so the template never overwrites them.
     */
    private function adopt(Event $event, ActivityTemplate $template): void
    {
        $event->forceFill([
            'activity_template_id' => $template->id,
            'type' => EventType::Recurring,
            'is_customized' => true,
        ])->save();
    }

    private function createOccurrence(ActivityTemplate $template, CarbonImmutable $date): void
    {
        $event = new Event(['activity_template_id' => $template->id, 'type' => EventType::Recurring]);
        $this->applyTemplate($event, $template, $date);
    }

    private function applyTemplate(Event $event, ActivityTemplate $template, CarbonImmutable $date): void
    {
        $endsAt = LocalTime::toUtc($date, $template->end_time);

        $event->fill([
            'name' => $template->name,
            'date' => $date->toDateString(),
            'period' => $template->period,
            'arrival_at' => LocalTime::toUtc($date, $template->arrival_time),
            'starts_at' => LocalTime::toUtc($date, $template->start_time),
            'ends_at' => $endsAt,
            'teardown_until' => $endsAt->addMinutes($template->teardown_minutes),
            'location' => $template->location,
        ]);
        $event->save();

        $event->positions()->delete();
        foreach ($template->positions as $position) {
            $event->positions()->create([
                'team_role_id' => $position->team_role_id,
                'quantity' => $position->quantity,
                'min_level' => $position->min_level,
            ]);
        }
    }

    /**
     * Also removes occurrences that duplicate an extraordinary event added by hand.
     *
     * @param  array<string, Event>  $byHand  see addedByHand()
     */
    private function removeStale(ActivityTemplate $template, CarbonImmutable $from, array $byHand = []): void
    {
        $template->events()
            ->where('date', '>=', $from->toDateString())
            ->where('is_customized', false)
            ->where('status', EventStatus::Scheduled)
            ->whereDoesntHave('assignments')
            ->get()
            ->reject(fn (Event $event) => $template->is_active && $template->occursOn($event->date) && ! isset($byHand[$event->date->toDateString()]))
            ->each(fn (Event $event) => $event->delete());
    }
}
