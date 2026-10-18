<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:coordinator'). Route-level gate; resources still go through Policies.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        abort_unless($user instanceof User && in_array($user->role, $allowed, true), 403);

        return $next($request);
    }
}
