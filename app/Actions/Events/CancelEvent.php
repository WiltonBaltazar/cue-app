<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an event (US2-4). Notifying the people assigned to it is added with
 * assignments in User Story 3.
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
    }
}
