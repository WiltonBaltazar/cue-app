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
 * - creates missing occurrences of active templates;
 * - re-syncs future, non-customised, scheduled occurrences with the template;
 * - removes future, non-customised, scheduled occurrences that no longer match
 *   (weekday changed or template deactivated).
 * Past, customised and cancelled occurrences are never touched.
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

    private function syncTemplate(ActivityTemplate $template, CarbonImmutable $today, CarbonImmutable $horizon): int
    {
        $this->removeStale($template, $today);

        if (! $template->is_active) {
            return 0;
        }

        $existing = $template->events()
            ->where('date', '>=', $today->toDateString())
            ->get()
            ->keyBy(fn (Event $event) => $event->date->toDateString());

        $created = 0;
        $date = $template->starts_on->greaterThan($today) ? $template->starts_on : $today;

        for (; $date->lte($horizon); $date = $date->addDay()) {
            if (! $template->occursOn($date)) {
                continue;
            }

            $event = $existing->get($date->toDateString());

            if ($event === null) {
                $event = new Event(['activity_template_id' => $template->id, 'type' => EventType::Recurring]);
                $this->applyTemplate($event, $template, $date);
                $created++;
            } elseif (! $event->is_customized && ! $event->isCancelled()) {
                $this->applyTemplate($event, $template, $date);
            }
        }

        return $created;
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

    private function removeStale(ActivityTemplate $template, CarbonImmutable $today): void
    {
        $template->events()
            ->where('date', '>=', $today->toDateString())
            ->where('is_customized', false)
            ->where('status', EventStatus::Scheduled)
            ->get()
            ->reject(fn (Event $event) => $template->is_active && $template->occursOn($event->date))
            ->each(fn (Event $event) => $event->delete());
    }
}
