<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Swaps\RegisterAbsence;
use App\Actions\Swaps\RemoveAbsence;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Absences a coordinator registers for a volunteer, e.g. after a phone call (FR-016). No
 * reason is asked for; the volunteer's own absences can only be removed by the volunteer.
 */
class VolunteerAbsenceController extends Controller
{
    public function store(Request $request, Volunteer $volunteer, #[CurrentUser] User $coordinator, RegisterAbsence $registerAbsence): RedirectResponse
    {
        Gate::authorize('registerAbsence', $volunteer);

        $validated = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.LocalTime::today()->toDateString()],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], [
            'starts_on.after_or_equal' => 'A ausência não pode começar no passado.',
            'ends_on.after_or_equal' => 'O fim tem de ser igual ou depois do início.',
        ]);

        $registerAbsence->handle(
            $volunteer,
            CarbonImmutable::parse($validated['starts_on']),
            CarbonImmutable::parse($validated['ends_on']),
            null,
            $coordinator,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ausência registada. Os turnos publicados nessas datas ficam por substituir.']);

        return back();
    }

    public function destroy(Volunteer $volunteer, Absence $absence, RemoveAbsence $removeAbsence): RedirectResponse
    {
        Gate::authorize('registerAbsence', $volunteer);
        abort_unless($absence->volunteer_id === $volunteer->id, 404);
        abort_unless($absence->isRemovableByCoordinator($volunteer), 403);

        $restored = $removeAbsence->handle($volunteer, $absence);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($restored) {
            0 => 'Ausência removida.',
            1 => 'Ausência removida. 1 turno voltou ao voluntário.',
            default => "Ausência removida. {$restored} turnos voltaram ao voluntário.",
        }]);

        return back();
    }
}
