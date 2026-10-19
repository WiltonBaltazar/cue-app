<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\UserRole;
use App\Enums\VolunteerStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RegistrationSubmitted;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registration status for volunteers not yet approved (FR-038 status states).
 */
class StatusController extends Controller
{
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        $volunteer = $user->volunteer;

        if ($volunteer === null || $volunteer->status === VolunteerStatus::Active) {
            return to_route('home');
        }

        return Inertia::render('volunteer/status', [
            'status' => $volunteer->status->value,
            'statusLabel' => $volunteer->status->label(),
            // Only the coordinator's own words, and only if they wrote any.
            'notes' => $volunteer->review_notes,
            'submittedAt' => $volunteer->created_at?->toIso8601String(),
        ]);
    }

    /**
     * After fixing their data, the volunteer sends the registration back for review.
     */
    public function resubmit(#[CurrentUser] User $user): RedirectResponse
    {
        $volunteer = $user->volunteer;

        if ($volunteer?->status !== VolunteerStatus::NeedsCorrection) {
            throw ValidationException::withMessages(['volunteer' => 'Este cadastro não está à espera de correcção.']);
        }

        $volunteer->status = VolunteerStatus::Pending;
        $volunteer->save();

        Notification::send(
            User::query()->where('role', UserRole::Coordinator)->get(),
            new RegistrationSubmitted($volunteer),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cadastro reenviado para revisão.']);

        return to_route('volunteer.status');
    }
}
