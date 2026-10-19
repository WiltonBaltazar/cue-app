<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Registration\CancelDataRemoval;
use App\Actions\Registration\RequestDataRemoval;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A volunteer asks for their data to be removed, or changes their mind within 7 days
 * (FR-028). Requesting needs the password.
 */
class DataRemovalController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, RequestDataRemoval $requestRemoval): RedirectResponse
    {
        $volunteer = $this->volunteerOf($user);

        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.current_password' => 'A palavra-passe não está correcta.',
        ]);

        $requestRemoval->handle($volunteer);

        $date = $volunteer->removal_scheduled_for?->setTimezone(LocalTime::zone())->format('d/m/Y');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pedido registado. Os seus dados serão removidos a {$date}; até lá pode cancelar."]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, CancelDataRemoval $cancel): RedirectResponse
    {
        $cancel->handle($this->volunteerOf($user));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido cancelado. Os seus dados mantêm-se.']);

        return back();
    }

    private function volunteerOf(User $user): Volunteer
    {
        abort_if($user->volunteer === null, 404, 'Esta conta não tem perfil de voluntário.');

        return $user->volunteer;
    }
}
