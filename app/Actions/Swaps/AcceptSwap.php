<?php

namespace App\Actions\Swaps;

use App\Domain\Scheduling\SwapEligibility;
use App\Enums\SwapStatus;
use App\Enums\UserRole;
use App\Models\SwapRequest;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\SwapAccepted;
use App\Support\Settings;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A colleague takes the shift (US4-3). Eligibility is re-checked now; approval is automatic
 * when enabled and the substitute's level is equal or higher (FR-018).
 */
class AcceptSwap
{
    public function __construct(
        private SwapEligibility $eligibility,
        private DecideSwap $decideSwap,
        private Settings $settings,
    ) {}

    public function handle(SwapRequest $swap, Volunteer $volunteer): void
    {
        $swap->loadMissing(['assignment.event', 'assignment.position.teamRole', 'requester.skills']);
        $assignment = $swap->assignment;

        if ($swap->status !== SwapStatus::Open
            || $volunteer->id === $swap->requester_id
            || ($swap->target_volunteer_id !== null && $swap->target_volunteer_id !== $volunteer->id)
            || $assignment->event->starts_at->isPast()) {
            throw ValidationException::withMessages(['swap' => 'Este pedido de troca já não está disponível para si.']);
        }

        if (! $this->eligibility->isEligible($assignment, $volunteer)) {
            throw ValidationException::withMessages(['swap' => 'Não pode assumir este turno: verifique a disponibilidade, o nível ou outros turnos.']);
        }

        $swap->status = SwapStatus::Accepted;
        $swap->accepted_by_id = $volunteer->id;
        $swap->save();

        if ($this->qualifiesForAutoApproval($swap, $volunteer)) {
            $this->decideSwap->handle($swap, approve: true, coordinator: null);

            return;
        }

        $shift = ShiftDescription::of($assignment);

        Notification::send(
            User::query()->where('role', UserRole::Coordinator)->get(),
            new SwapAccepted($volunteer->shortName(), $shift, forCoordinator: true),
        );
        $swap->requester->user?->notify(new SwapAccepted($volunteer->shortName(), $shift, forCoordinator: false));
    }

    private function qualifiesForAutoApproval(SwapRequest $swap, Volunteer $substitute): bool
    {
        if (! $this->settings->swapAutoApprove()) {
            return false;
        }

        $roleId = $swap->assignment->position->team_role_id;
        $original = $swap->requester->skills->firstWhere('team_role_id', $roleId)?->level;
        $replacement = $substitute->skills()->where('team_role_id', $roleId)->first()?->level;

        return $replacement !== null && ($original === null || $replacement->meets($original));
    }
}
