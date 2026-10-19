<?php

namespace App\Actions\Swaps;

use App\Domain\Scheduling\SwapEligibility;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\Volunteer;
use App\Notifications\SwapRequested;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Asks a colleague, or every eligible colleague, to take a published upcoming shift
 * (FR-017). Only eligible people are told; one active request per shift.
 */
class CreateSwap
{
    public function __construct(
        private SwapEligibility $eligibility,
        private ReplacementFlagger $flagger,
    ) {}

    public function handle(Assignment $assignment, Volunteer $requester, ?Volunteer $target = null, bool $fromDecline = false): SwapRequest
    {
        $assignment->loadMissing(['event', 'schedule', 'position.teamRole']);

        $allowedStatus = $fromDecline ? AssignmentStatus::Declined : AssignmentStatus::Confirmed;

        if ($assignment->volunteer_id !== $requester->id
            || ! $assignment->schedule->isPublished()
            || $assignment->status !== $allowedStatus
            || $assignment->event->isCancelled()
            || $assignment->event->starts_at->isPast()) {
            throw ValidationException::withMessages(['assignment_id' => 'Só pode pedir troca de um turno seu, publicado e ainda por acontecer.']);
        }

        if (SwapRequest::query()->active()->where('assignment_id', $assignment->id)->exists()) {
            throw ValidationException::withMessages(['assignment_id' => 'Já existe um pedido de troca activo para este turno.']);
        }

        if ($target !== null && ! $this->eligibility->isEligible($assignment, $target)) {
            throw ValidationException::withMessages(['target_volunteer_id' => 'Este colega não pode assumir o turno (disponibilidade, nível ou outro turno).']);
        }

        $swap = SwapRequest::query()->create([
            'assignment_id' => $assignment->id,
            'requester_id' => $requester->id,
            'target_volunteer_id' => $target?->id,
            'is_urgent' => $this->flagger->isUrgent($assignment->event),
        ]);

        $recipients = $target !== null
            ? collect([$target->loadMissing('user')])
            : $this->eligibility->eligibleColleagues($assignment);

        Notification::send(
            $recipients->map(fn (Volunteer $volunteer) => $volunteer->user)->filter(),
            new SwapRequested($requester->shortName(), ShiftDescription::of($assignment), $swap->is_urgent),
        );

        return $swap;
    }
}
