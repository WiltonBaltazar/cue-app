<?php

namespace App\Actions\Swaps;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Volunteer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Não posso ir" (US4-5): the shift is declined, an open swap request is created and
 * coordinators are told, flagged urgent close to the event.
 */
class DeclineAssignment
{
    public function __construct(
        private CreateSwap $createSwap,
        private ReplacementFlagger $flagger,
    ) {}

    public function handle(Assignment $assignment, Volunteer $volunteer): void
    {
        $assignment->loadMissing(['event', 'schedule']);

        if ($assignment->status !== AssignmentStatus::Confirmed
            || ! $assignment->schedule->isPublished()
            || $assignment->event->isCancelled()
            || $assignment->event->starts_at->isPast()) {
            throw ValidationException::withMessages(['assignment' => 'Este turno já não pode ser recusado.']);
        }

        DB::transaction(function () use ($assignment, $volunteer): void {
            $assignment->status = AssignmentStatus::Declined;
            $assignment->responded_at = now();
            $assignment->save();

            $this->createSwap->handle($assignment, $volunteer, fromDecline: true);
        });

        $this->flagger->notifyCoordinators($volunteer, $assignment);
    }
}
