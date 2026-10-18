<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coordinators can see sensitive data, so they must confirm two-factor authentication
 * before using anything except the screens needed to set it up (FR-006).
 */
class EnsureCoordinatorTwoFactor
{
    /**
     * Routes a coordinator without 2FA may still use.
     *
     * @var list<string>
     */
    private const SETUP_ROUTES = [
        'security.edit',
        'two-factor.*',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'user-password.update',
        'logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && $user->isCoordinator()
            && ! $user->hasConfirmedTwoFactor()
            && ! $request->routeIs(...self::SETUP_ROUTES)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Os coordenadores têm de activar a verificação em dois passos antes de continuar.',
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
