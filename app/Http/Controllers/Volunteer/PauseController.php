<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Volunteers\PauseVolunteer;
use App\Actions\Volunteers\ResumeVolunteer;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A volunteer pauses or resumes their own participation (FR-005a).
 */
class PauseController extends Controller
{
    public function pause(Request $request, #[CurrentUser] User $user, PauseVolunteer $pause): RedirectResponse
    {
        $volunteer = $user->volunteer;
        abort_if($volunteer === null, 404);

        $validated = $request->validate([
            'until' => ['nullable', 'date_format:Y-m-d', 'after:'.LocalTime::today()->toDateString()],
        ], ['until.after' => 'A data de regresso tem de ser no futuro.']);

        $flagged = $pause->handle($volunteer, $user, isset($validated['until']) ? CarbonImmutable::parse($validated['until']) : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $flagged > 0
            ? "Participação em pausa. {$flagged} turnos foram marcados para substituição."
            : 'Participação em pausa.']);

        return back();
    }

    public function resume(#[CurrentUser] User $user, ResumeVolunteer $resume): RedirectResponse
    {
        $volunteer = $user->volunteer;
        abort_if($volunteer === null, 404);

        $resume->handle($volunteer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bem-vindo de volta! Já pode ser escalado.']);

        return back();
    }
}
