<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Swaps\AcceptSwap;
use App\Actions\Swaps\CreateSwap;
use App\Enums\SwapStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use App\Support\OpenSwaps;
use App\Support\ShiftDescription;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Volunteer swaps (US4): my requests, and requests I can take. Open requests are shown
 * only to colleagues eligible for the shift (US4-2).
 */
class SwapController extends Controller
{
    public function index(#[CurrentUser] User $user, OpenSwaps $openSwaps): Response
    {
        $me = $user->volunteer;
        abort_if($me === null, 404);

        $mine = SwapRequest::query()
            ->where('requester_id', $me->id)
            ->where('created_at', '>=', now()->subDays(60))
            ->with(['assignment.event', 'assignment.position.teamRole', 'target', 'acceptedBy'])
            ->latest()
            ->get();

        $openToMe = $openSwaps->for($me);

        return Inertia::render('volunteer/swaps/index', [
            'mine' => $mine->map(fn (SwapRequest $swap) => [
                ...$this->present($swap),
                'target' => $swap->target?->shortName(),
                'acceptedBy' => $swap->acceptedBy?->shortName(),
                'canCancel' => $swap->status === SwapStatus::Open,
            ])->values(),
            'openToMe' => $openToMe->map(fn (SwapRequest $swap) => [
                ...$this->present($swap),
                'requester' => $swap->requester->shortName(),
                'directedToMe' => $swap->target_volunteer_id === $me->id,
            ])->values(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateSwap $createSwap): RedirectResponse
    {
        $me = $user->volunteer;
        abort_if($me === null, 404);

        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
            'target_volunteer_id' => ['nullable', 'integer', 'exists:volunteers,id'],
        ]);

        $assignment = Assignment::query()->whereKey($validated['assignment_id'])->firstOrFail();
        $target = isset($validated['target_volunteer_id'])
            ? Volunteer::query()->whereKey($validated['target_volunteer_id'])->firstOrFail()
            : null;

        $createSwap->handle($assignment, $me, $target);

        Inertia::flash('toast', ['type' => 'success', 'message' => $target === null
            ? 'Pedido de troca enviado aos colegas disponíveis.'
            : "Pedido de troca enviado a {$target->shortName()}."]);

        return to_route('swaps.index');
    }

    public function accept(#[CurrentUser] User $user, SwapRequest $swap, AcceptSwap $acceptSwap): RedirectResponse
    {
        $me = $user->volunteer;

        if ($me === null) {
            throw ValidationException::withMessages(['swap' => 'Esta conta não tem perfil de voluntário.']);
        }

        $acceptSwap->handle($swap, $me);

        $swap->refresh();
        Inertia::flash('toast', ['type' => 'success', 'message' => $swap->status === SwapStatus::Approved
            ? 'Troca aprovada. O turno já está na sua escala.'
            : 'Troca aceite. Falta a aprovação de um coordenador.']);

        return to_route('swaps.index');
    }

    public function cancel(SwapRequest $swap): RedirectResponse
    {
        Gate::authorize('cancel', $swap);

        if ($swap->status !== SwapStatus::Open) {
            throw ValidationException::withMessages(['swap' => 'Só é possível cancelar um pedido em aberto.']);
        }

        $swap->status = SwapStatus::Cancelled;
        $swap->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido de troca cancelado.']);

        return to_route('swaps.index');
    }

    /**
     * Shift details only; never the reason a volunteer cannot go (FR-016).
     *
     * @return array<string, mixed>
     */
    private function present(SwapRequest $swap): array
    {
        $event = $swap->assignment->event;

        return [
            'id' => $swap->id,
            'status' => $swap->status->value,
            'statusLabel' => $swap->status->label(),
            'isUrgent' => $swap->is_urgent,
            'shift' => ShiftDescription::of($swap->assignment),
            'eventName' => $event->name,
            'role' => $swap->assignment->position->teamRole->name,
            'date' => $event->date->toDateString(),
            'arrivalTime' => LocalTime::timeOf($event->arrival_at),
            'startTime' => LocalTime::timeOf($event->starts_at),
        ];
    }
}
