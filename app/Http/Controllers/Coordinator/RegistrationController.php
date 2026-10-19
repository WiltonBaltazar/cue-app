<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Registration\ApproveVolunteer;
use App\Actions\Registration\RejectVolunteer;
use App\Actions\Registration\RequestCorrection;
use App\Actions\Registration\ReverseRejection;
use App\Enums\GuardianConsentMethod;
use App\Enums\Level;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\VolunteerPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coordinator review of registrations (US1, FR-003, FR-003a, FR-002a).
 */
class RegistrationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Volunteer::class);

        $registrations = Volunteer::query()
            ->awaitingReview()
            ->with('skills.teamRole')
            ->oldest()
            ->get();

        return Inertia::render('coord/registrations/index', [
            'registrations' => $registrations->map(fn (Volunteer $volunteer) => VolunteerPresenter::summary($volunteer))->values(),
        ]);
    }

    public function show(#[CurrentUser] User $coordinator, Volunteer $volunteer): Response
    {
        Gate::authorize('viewSensitive', $volunteer);

        // Every view of a full profile is audited (FR-035); no values are logged.
        activity('audit')
            ->performedOn($volunteer)
            ->causedBy($coordinator)
            ->event('viewed_sensitive_profile')
            ->log('viewed_sensitive_profile');

        $volunteer->load(['skills.teamRole', 'availabilities', 'user']);

        return Inertia::render('coord/registrations/show', [
            'volunteer' => VolunteerPresenter::full($volunteer),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
            'guardianConsentMethods' => collect(GuardianConsentMethod::cases())->map(fn (GuardianConsentMethod $method) => ['value' => $method->value, 'label' => $method->label()]),
            'canReverse' => $volunteer->reviewed_at?->addDays(RejectVolunteer::REVERSIBLE_DAYS)->isFuture() ?? false,
        ]);
    }

    public function approve(Request $request, #[CurrentUser] User $coordinator, Volunteer $volunteer, ApproveVolunteer $approve): RedirectResponse
    {
        Gate::authorize('review', $volunteer);

        $validated = $request->validate([
            'levels' => ['array'],
            'levels.*' => [Rule::enum(Level::class)],
            'guardian_consent_method' => ['nullable', Rule::enum(GuardianConsentMethod::class)],
        ]);

        /** @var array<int, string> $levels */
        $levels = $validated['levels'] ?? [];

        $approve->handle(
            $volunteer,
            $coordinator,
            array_map(fn (string $level) => Level::from($level), $levels),
            isset($validated['guardian_consent_method']) ? GuardianConsentMethod::from($validated['guardian_consent_method']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$volunteer->shortName()} foi aprovado."]);

        return to_route('coord.registrations.index');
    }

    public function reject(Request $request, #[CurrentUser] User $coordinator, Volunteer $volunteer, RejectVolunteer $reject): RedirectResponse
    {
        Gate::authorize('review', $volunteer);

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $reject->handle($volunteer, $coordinator, $validated['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cadastro rejeitado. Pode reverter durante 30 dias.']);

        return to_route('coord.registrations.index');
    }

    public function correction(Request $request, #[CurrentUser] User $coordinator, Volunteer $volunteer, RequestCorrection $requestCorrection): RedirectResponse
    {
        Gate::authorize('review', $volunteer);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']], [
            'notes.required' => 'Explique ao voluntário o que tem de corrigir.',
        ]);

        $requestCorrection->handle($volunteer, $coordinator, $validated['notes']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido de correcção enviado.']);

        return to_route('coord.registrations.index');
    }

    public function reverse(#[CurrentUser] User $coordinator, Volunteer $volunteer, ReverseRejection $reverse): RedirectResponse
    {
        Gate::authorize('review', $volunteer);

        $reverse->handle($volunteer, $coordinator);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rejeição revertida. O cadastro voltou a pendente.']);

        return to_route('coord.registrations.show', $volunteer);
    }
}
