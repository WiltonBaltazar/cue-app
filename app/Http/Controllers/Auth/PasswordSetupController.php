<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EndOtherSessions;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\PasswordSetupLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a coordinator-issued reset link leads (FR-006). Unknown, used and expired links
 * all look the same: not found.
 */
class PasswordSetupController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token): Response
    {
        $link = PasswordSetupLink::findValid($token);
        abort_if($link === null, 404);

        return Inertia::render('auth/set-password', [
            'token' => $token,
            'name' => $link->user->name,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token, EndOtherSessions $endOtherSessions): RedirectResponse
    {
        $link = PasswordSetupLink::findValid($token);
        abort_if($link === null, 404);

        $validated = $request->validate(['password' => $this->passwordRules()]);

        DB::transaction(function () use ($link, $validated, $endOtherSessions): void {
            $user = $link->user;
            $user->forceFill(['password' => $validated['password']])->save();
            $link->delete();
            $endOtherSessions->handle($user, null);
        });

        return to_route('login')->with('status', 'Palavra-passe definida. Já pode entrar.');
    }
}
