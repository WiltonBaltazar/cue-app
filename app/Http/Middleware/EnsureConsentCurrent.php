<?php

namespace App\Http\Middleware;

use App\Models\ConsentVersion;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the declaration changes, volunteers accept the new version on their next request
 * before using the app (FR-002). Existing shifts are not affected.
 */
class EnsureConsentCurrent
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'volunteer.consent',
        'volunteer.consent.accept',
        'logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $volunteer = $user instanceof User ? $user->volunteer : null;

        if ($volunteer !== null
            && $volunteer->anonymized_at === null
            && $volunteer->consent_version < ConsentVersion::currentNumber()
            && ! $request->routeIs(...self::ALLOWED_ROUTES)
            && ! $request->expectsJson()) {
            return redirect()->route('volunteer.consent');
        }

        return $next($request);
    }
}
