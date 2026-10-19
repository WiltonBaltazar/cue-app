<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Swaps\RegisterAbsence;
use App\Actions\Swaps\RemoveAbsence;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The volunteer's own absences (FR-016). The reason stays private (FR-026).
 */
class AbsenceController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        $volunteer = $user->volunteer;
        abort_if($volunteer === null, 404);

        return Inertia::render('volunteer/absences/index', [
            'absences' => $volunteer->absences()
                ->where('ends_on', '>=', LocalTime::today()->toDateString())
                ->orderBy('starts_on')
                ->get()
                ->map(fn (Absence $absence) => [
                    'id' => $absence->id,
                    'startsOn' => $absence->starts_on->toDateString(),
                    'endsOn' => $absence->ends_on->toDateString(),
                    'reason' => $absence->reason,
                ])->values(),
            'today' => LocalTime::today()->toDateString(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, RegisterAbsence $registerAbsence): RedirectResponse
    {
        $volunteer = $user->volunteer;
        abort_if($volunteer === null, 404);

        $validated = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.LocalTime::today()->toDateString()],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'starts_on.after_or_equal' => 'A ausência não pode começar no passado.',
            'ends_on.after_or_equal' => 'O fim tem de ser igual ou depois do início.',
        ]);

        $registerAbsence->handle(
            $volunteer,
            CarbonImmutable::parse($validated['starts_on']),
            CarbonImmutable::parse($validated['ends_on']),
            $validated['reason'] ?? null,
            $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ausência registada. Os coordenadores foram avisados dos turnos afectados.']);

        return to_route('absences.index');
    }

    public function destroy(#[CurrentUser] User $user, Absence $absence, RemoveAbsence $removeAbsence): RedirectResponse
    {
        $volunteer = $user->volunteer;
        abort_unless($volunteer !== null && $absence->volunteer_id === $volunteer->id, 403);

        $restored = $removeAbsence->handle($volunteer, $absence);

        Inertia::flash('toast', ['type' => 'success', 'message' => $restored > 0
            ? "Ausência removida. {$restored} turnos voltaram a ser seus."
            : 'Ausência removida.']);

        return to_route('absences.index');
    }
}
