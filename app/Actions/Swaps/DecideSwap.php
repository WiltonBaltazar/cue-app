<?php

namespace App\Actions\Swaps;

use App\Domain\Scheduling\SwapEligibility;
use App\Enums\AssignmentStatus;
use App\Enums\SwapStatus;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapDecided;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approves or rejects an accepted swap (FR-018). On approval the original shift stays as
 * history (declined) and the substitute gets a new confirmed shift; both are told.
 */
class DecideSwap
{
    public function __construct(private SwapEligibility $eligibility) {}

    public function handle(SwapRequest $swap, bool $approve, ?User $coordinator): void
    {
        $swap->loadMissing(['assignment.event', 'assignment.position.teamRole', 'acceptedBy.user', 'requester.user']);

        if ($swap->status !== SwapStatus::Accepted || $swap->acceptedBy === null) {
            throw ValidationException::withMessages(['swap' => 'Este pedido já não está à espera de decisão.']);
        }

        $original = $swap->assignment;
        $substitute = $swap->acceptedBy;

        if ($approve && ! $this->eligibility->isEligible($original, $substitute)) {
            throw ValidationException::withMessages(['swap' => "{$substitute->shortName()} já não pode assumir este turno. Rejeite a troca ou escolha outra pessoa."]);
        }

        DB::transaction(function () use ($swap, $approve, $coordinator, $original, $substitute): void {
            if ($approve) {
                if ($original->status === AssignmentStatus::Confirmed) {
                    $original->status = AssignmentStatus::Declined;
                    $original->save();
                }

                Assignment::query()->create([
                    'schedule_id' => $original->schedule_id,
                    'position_id' => $original->position_id,
                    'event_id' => $original->event_id,
                    'volunteer_id' => $substitute->id,
                    'source' => Assignment::SOURCE_SWAP,
                    'is_locked' => true,
                ]);
            }

            $swap->status = $approve ? SwapStatus::Approved : SwapStatus::Rejected;
            $swap->decided_by = $coordinator?->id;
            $swap->decided_at = now();
            $swap->auto_approved = $approve && $coordinator === null;
            $swap->save();
        });

        $shift = ShiftDescription::of($original);
        $swap->requester->user?->notify(new SwapDecided($approve, $shift));
        $substitute->user?->notify(new SwapDecided($approve, $shift));
    }
}
