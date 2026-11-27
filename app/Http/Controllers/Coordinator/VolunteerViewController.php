<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\VolunteerView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * "Ver como voluntário": a coordinator with a serving profile opens the volunteer app.
 */
class VolunteerViewController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): RedirectResponse
    {
        if ($user->volunteer === null) {
            return to_route('coord.serving-profile.create');
        }

        VolunteerView::enter();

        return to_route('home');
    }
}
