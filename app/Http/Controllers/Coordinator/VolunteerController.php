<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Auth\IssueResetLink;
use App\Actions\Volunteers\PauseVolunteer;
use App\Actions\Volunteers\ResumeVolunteer;
use App\Enums\Level;
use App\Enums\VolunteerStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use App\Support\VolunteerPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Approved volunteers: list (with the "sem turnos há 3 meses" suggestion filter), levels
 * per role (FR-004), pause/resume (FR-005a) and reset links (FR-006).
 */
class VolunteerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Volunteer::class);

        $filter = $request->string('filtro')->toString() === 'sem-turnos' ? 'sem-turnos' : null;
        $since = LocalTime::today()->subMonths(3);

        $volunteers = Volunteer::query()
            ->whereIn('status', [VolunteerStatus::Active, VolunteerStatus::Inactive])
            // Suggestions only, never automatic inactivation (FR-005a): active for 3+ months
            // without any shift in that time.
            ->when($filter === 'sem-turnos', fn ($query) => $query
                ->where('status', VolunteerStatus::Active)
                ->where('created_at', '<', $since)
                ->whereDoesntHave('assignments', fn ($assignments) => $assignments
                    ->whereHas('event', fn ($events) => $events->where('date', '>=', $since->toDateString()))))
            ->with('skills.teamRole')
            ->orderBy('full_name')
            ->get();

        return Inertia::render('coord/volunteers/index', [
            'volunteers' => $volunteers->map(fn (Volunteer $volunteer) => VolunteerPresenter::summary($volunteer))->values(),
            'filter' => $filter,
        ]);
    }

    public function pause(Request $request, #[CurrentUser] User $coordinator, Volunteer $volunteer, PauseVolunteer $pause): RedirectResponse
    {
        Gate::authorize('pause', $volunteer);

        $validated = $request->validate([
            'until' => ['nullable', 'date_format:Y-m-d', 'after:'.LocalTime::today()->toDateString()],
        ]);

        $flagged = $pause->handle($volunteer, $coordinator, isset($validated['until']) ? CarbonImmutable::parse($validated['until']) : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Voluntário em pausa. {$flagged} turnos marcados para substituição."]);

        return back();
    }

    public function resume(Volunteer $volunteer, ResumeVolunteer $resume): RedirectResponse
    {
        Gate::authorize('pause', $volunteer);

        $resume->handle($volunteer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voluntário activo de novo.']);

        return back();
    }

    public function edit(#[CurrentUser] User $coordinator, Volunteer $volunteer): Response
    {
        Gate::authorize('viewSensitive', $volunteer);

        activity('audit')
            ->performedOn($volunteer)
            ->causedBy($coordinator)
            ->event('viewed_sensitive_profile')
            ->log('viewed_sensitive_profile');

        $volunteer->load(['skills.teamRole', 'availabilities', 'user']);

        return Inertia::render('coord/volunteers/edit', [
            'volunteer' => VolunteerPresenter::full($volunteer),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
            // A freshly issued link is shown once, then gone (FR-006).
            'resetLink' => session('resetLink'),
            'resetLinkHours' => IssueResetLink::VALID_HOURS,
        ]);
    }

    public function update(Request $request, Volunteer $volunteer): RedirectResponse
    {
        Gate::authorize('review', $volunteer);

        $validated = $request->validate([
            'levels' => ['required', 'array'],
            'levels.*' => [Rule::enum(Level::class)],
        ]);

        DB::transaction(function () use ($volunteer, $validated): void {
            foreach ($volunteer->skills as $skill) {
                if (isset($validated['levels'][$skill->team_role_id])) {
                    $skill->update(['level' => Level::from($validated['levels'][$skill->team_role_id])]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Níveis actualizados.']);

        return to_route('coord.volunteers.edit', $volunteer);
    }

    public function issueResetLink(#[CurrentUser] User $coordinator, Volunteer $volunteer, IssueResetLink $issueResetLink): RedirectResponse
    {
        Gate::authorize('issueResetLink', $volunteer);

        $link = $issueResetLink->handle($volunteer, $coordinator);

        return to_route('coord.volunteers.edit', $volunteer)->with('resetLink', $link);
    }
}
