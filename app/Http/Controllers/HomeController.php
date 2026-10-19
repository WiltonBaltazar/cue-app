<?php

namespace App\Http\Controllers;

use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Support\OpenSwaps;
use App\Support\VolunteerShifts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * "/" is the volunteer home; coordinators start in the management area.
     */
    public function __invoke(#[CurrentUser] User $user, OpenSwaps $openSwaps): Response|RedirectResponse
    {
        if ($user->isCoordinator()) {
            return to_route('coord.dashboard');
        }

        $volunteer = $user->volunteer;
        $nextShifts = $volunteer === null ? [] : VolunteerShifts::upcoming($volunteer, 4);
        $next = $nextShifts[0] ?? null;

        return Inertia::render('volunteer/home', [
            'nextShifts' => $nextShifts,
            // The first shift is shown with its team, so the volunteer knows who to meet.
            'nextTeam' => $next === null || $volunteer === null ? null : VolunteerShifts::team($next['scheduleId'], $next['eventId'], $volunteer->id),
            'openSwaps' => $volunteer === null ? 0 : $openSwaps->for($volunteer)->count(),
            'paused' => $user->volunteer?->status === VolunteerStatus::Inactive,
            'pausedUntil' => $user->volunteer?->inactive_until?->toDateString(),
        ]);
    }
}
