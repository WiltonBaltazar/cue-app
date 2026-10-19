<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Assignment;
use App\Models\Event;
use App\Notifications\EventCancelled;
use App\Support\ShiftDescription;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an event and tells everyone assigned to it (US2-4). Their assignments stay as
 * history; a cancelled event no longer counts for any rule.
 */
class CancelEvent
{
    public function handle(Event $event, ?string $reason): void
    {
        if ($event->isCancelled()) {
            throw ValidationException::withMessages(['event' => 'Este evento já está cancelado.']);
        }

        $event->status = EventStatus::Cancelled;
        $event->cancelled_at = now();
        $event->cancel_reason = filled($reason) ? $reason : null;
        $event->save();

        $description = ShiftDescription::event($event);

        // Only people who were told about the shift (published schedules) hear about it.
        $event->assignments()->active()
            ->whereHas('schedule', fn ($query) => $query->published())
            ->with('volunteer.user')->get()
            ->each(fn (Assignment $assignment) => $assignment->volunteer->user?->notify(new EventCancelled($description)));
    }

    public function affectedCount(Event $event): int
    {
        return $event->assignments()->active()->count();
    }
}
