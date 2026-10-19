<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Volunteers\ChangeUserRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Coordinators give or take away the coordinator role. It grants access to everyone's
 * personal data, so the acting coordinator confirms with their own password.
 */
class CoordinatorRoleController extends Controller
{
    public function store(Request $request, User $user, ChangeUserRole $changeRole): RedirectResponse
    {
        $this->confirmPassword($request);
        abort_if($user->volunteer?->isAnonymized() ?? false, 404);

        $changeRole->handle($user, UserRole::Coordinator);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} passou a ter o papel de coordenador."]);

        return back();
    }

    public function destroy(Request $request, #[CurrentUser] User $coordinator, User $user, ChangeUserRole $changeRole): RedirectResponse
    {
        $this->confirmPassword($request);

        $changeRole->handle($user, UserRole::Volunteer);

        if ($user->is($coordinator)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Já não tem o papel de coordenador.']);

            return to_route('home');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} já não tem o papel de coordenador."]);

        return back();
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'A palavra-passe não está correcta.'],
        );
    }
}
