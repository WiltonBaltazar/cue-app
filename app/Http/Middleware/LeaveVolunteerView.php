<?php

namespace App\Http\Middleware;

use App\Support\VolunteerView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opening any management page brings a coordinator back from the volunteer view.
 */
class LeaveVolunteerView
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        VolunteerView::leave();

        return $next($request);
    }
}
