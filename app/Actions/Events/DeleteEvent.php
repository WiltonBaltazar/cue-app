<?php

namespace App\Actions\Events;

use App\Enums\EventType;
use App\Models\Assignment;
use App\Models\Event;
use App\Notifications\EventCancelled;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes an extraordinary event for good: its positions, assignments, swap requests and
 * its own schedule go with it. People told about a shift (published schedule) hear that it
 * is off. Recurring events come from a template and are only cancelled; past events with
 * people keep their history.
 */
class DeleteEvent
{
    public function handle(Event $event): void
    {
        if ($event->type !== EventType::Special) {
            throw ValidationException::withMessages(['event' => 'Só os eventos extraordinários podem ser apagados. Este pode ser cancelado.']);
        }

        if ($event->starts_at->isPast() && $event->assignments()->exists()) {
            throw ValidationException::withMessages(['event' => 'Este evento já aconteceu e tem histórico de voluntários. Não pode ser apagado.']);
        }

        $description = ShiftDescription::event($event);

        $toNotify = $event->starts_at->isPast() || $event->isCancelled()
            ? collect()
            : $event->assignments()->active()
                ->whereHas('schedule', fn ($query) => $query->published())
                ->with('volunteer.user')->get()
                ->map(fn (Assignment $assignment) => $assignment->volunteer->user)
                ->filter();

        DB::transaction(function () use ($event): void {
            // Swap requests go with their assignments; positions and the own schedule with the event.
            $event->assignments()->delete();
            $event->delete();
        });

        $toNotify->each(fn ($user) => $user->notify(new EventCancelled($description)));
    }
}
