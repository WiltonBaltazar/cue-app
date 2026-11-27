<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\VolunteerView;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a coordinator views the app as a volunteer, every volunteer page reads that volunteer's
 * data (their account's `volunteer` relation is swapped for the request) and nothing can be changed.
 * Opening any management page ends the view.
 */
class ApplyVolunteerView
{
    /**
     * Requests that still work in the view: leaving it and signing out.
     */
    private const ALLOWED_WRITES = ['logout', 'volunteer-view.enter', 'coord.volunteers.view-as'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('coord.*')) {
            VolunteerView::leave();

            return $next($request);
        }

        $user = $request->user();
        $volunteer = $user instanceof User ? VolunteerView::target($user) : null;

        if ($volunteer === null) {
            return $next($request);
        }

        if (! $request->isMethodSafe() && ! $request->routeIs(...self::ALLOWED_WRITES)) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Está a ver como voluntário: só leitura.']);

            return back();
        }

        $user->setRelation('volunteer', $volunteer);

        return $next($request);
    }
}
