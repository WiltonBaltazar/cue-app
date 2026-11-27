<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\VolunteerView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Ver como voluntário": a coordinator opens the volunteer app as one volunteer sees it (read-only).
 * Without a volunteer chosen, a coordinator who also serves sees their own.
 */
class VolunteerViewController extends Controller
{
    public function __invoke(#[CurrentUser] User $coordinator, ?Volunteer $volunteer = null): RedirectResponse
    {
        $volunteer ??= $coordinator->volunteer;

        if ($volunteer === null) {
            return to_route('coord.volunteers.index');
        }

        Gate::authorize('viewSensitive', $volunteer);
        abort_if($volunteer->isAnonymized(), 404);

        if ($volunteer->user_id !== $coordinator->id) {
            activity('audit')
                ->performedOn($volunteer)
                ->causedBy($coordinator)
                ->event('viewed_as_volunteer')
                ->log('viewed_as_volunteer');
        }

        VolunteerView::enter($volunteer);

        return to_route('home');
    }
}
