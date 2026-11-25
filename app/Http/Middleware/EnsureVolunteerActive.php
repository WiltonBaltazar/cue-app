<?php

namespace App\Http\Middleware;

use App\Enums\VolunteerStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Volunteers whose registration is pending, needs correction or was rejected, and those a
 * coordinator deactivated (FR-005b), only see their status page (FR-001). Applied to the volunteer area routes.
 */
class EnsureVolunteerActive
{
    private const UNDER_REVIEW = [
        VolunteerStatus::Pending,
        VolunteerStatus::NeedsCorrection,
        VolunteerStatus::Rejected,
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $status = $user instanceof User ? $user->volunteer?->status : null;

        // A coordinator taken off the team still runs the app.
        if ($status === VolunteerStatus::Deactivated && ! $user->isCoordinator()) {
            return redirect()->route('volunteer.status');
        }

        if ($status !== null && in_array($status, self::UNDER_REVIEW, true)) {
            return redirect()->route('volunteer.status');
        }

        return $next($request);
    }
}
