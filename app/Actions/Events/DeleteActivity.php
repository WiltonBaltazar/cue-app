<?php

namespace App\Actions\Events;

use App\Models\ActivityTemplate;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventCancelled;
use App\Support\ShiftDescription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes a recurring activity and its dates still to come, with their positions and people.
 * Past dates stay as history. People told about a shift (published schedule) hear once that
 * it is off, whatever the number of dates.
 */
class DeleteActivity
{
    public function handle(ActivityTemplate $template): void
    {
        $toNotify = DB::transaction(function () use ($template): array {
            $events = $this->upcoming($template);
            $toNotify = $this->peopleToNotify($events);

            foreach ($events as $event) {
                // Swap requests go with their assignments; positions and an own schedule with the event.
                $event->assignments()->delete();
                $event->delete();
            }

            // Past events keep their own data; the database only clears their link to the template.
            $template->delete();

            return $toNotify;
        });

        foreach ($toNotify as ['user' => $user, 'events' => $events]) {
            $user->notify(new EventCancelled($this->describe($template, $events)));
        }
    }

    /**
     * @return array{dates: int, volunteers: int}
     */
    public function affected(ActivityTemplate $template): array
    {
        $events = $this->upcoming($template);

        return [
            'dates' => $events->count(),
            'volunteers' => Assignment::query()->active()->whereIn('event_id', $events->modelKeys())->distinct()->count('volunteer_id'),
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    private function upcoming(ActivityTemplate $template): Collection
    {
        return $template->events()->where('starts_at', '>', now())->orderBy('starts_at')->get();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{user: User, events: list<Event>}>
     */
    private function peopleToNotify(Collection $events): array
    {
        $toNotify = [];

        $assignments = Assignment::query()->active()
            ->whereIn('event_id', $events->reject(fn (Event $event): bool => $event->isCancelled())->modelKeys())
            ->whereHas('schedule', fn ($query) => $query->published())
            ->with(['event', 'volunteer.user'])
            ->get()
            ->sortBy(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp());

        foreach ($assignments as $assignment) {
            $user = $assignment->volunteer->user;

            if ($user !== null) {
                $toNotify[$user->id] ??= ['user' => $user, 'events' => []];
                $toNotify[$user->id]['events'][] = $assignment->event;
            }
        }

        return $toNotify;
    }

    /**
     * @param  list<Event>  $events
     */
    private function describe(ActivityTemplate $template, array $events): string
    {
        if (count($events) === 1) {
            return ShiftDescription::event($events[0]);
        }

        $dates = collect($events)->map(fn (Event $event): string => $event->date->format('j/n'))->join(', ', ' e ');

        return "{$template->name} ({$dates})";
    }
}
