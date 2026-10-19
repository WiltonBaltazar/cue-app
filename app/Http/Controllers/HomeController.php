<?php

namespace App\Http\Controllers;

use App\Enums\VolunteerStatus;
use App\Models\User;
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
    public function __invoke(#[CurrentUser] User $user): Response|RedirectResponse
    {
        if ($user->isCoordinator()) {
            return to_route('coord.dashboard');
        }

        return Inertia::render('volunteer/home', [
            'nextShifts' => $user->volunteer === null ? [] : VolunteerShifts::upcoming($user->volunteer, 3),
            'paused' => $user->volunteer?->status === VolunteerStatus::Inactive,
            'pausedUntil' => $user->volunteer?->inactive_until?->toDateString(),
        ]);
    }
}
