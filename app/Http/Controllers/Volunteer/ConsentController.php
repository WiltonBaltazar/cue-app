<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\ConsentVersion;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Re-acceptance of a new declaration version (FR-002).
 */
class ConsentController extends Controller
{
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        $current = ConsentVersion::current();

        if ($user->volunteer === null || $user->volunteer->consent_version >= $current->version) {
            return to_route('home');
        }

        return Inertia::render('volunteer/consent', [
            'declaration' => ['version' => $current->version, 'body' => $current->body],
        ]);
    }

    public function accept(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $volunteer = $user->volunteer;
        abort_if($volunteer === null, 404);

        $validated = $request->validate([
            'consent_accepted' => ['accepted'],
            'consent_version' => ['required', 'integer', Rule::in([ConsentVersion::currentNumber()])],
        ], [
            'consent_accepted.accepted' => 'Para continuar tem de aceitar a declaração.',
            'consent_version.in' => 'A declaração foi actualizada entretanto. Leia a nova versão.',
        ]);

        $volunteer->consent_version = (int) $validated['consent_version'];
        $volunteer->consent_accepted_at = now();
        $volunteer->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Obrigado. Declaração aceite.']);

        return to_route('home');
    }
}
