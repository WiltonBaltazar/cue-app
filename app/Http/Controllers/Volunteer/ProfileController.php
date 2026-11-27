<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Volunteers\UpdateVolunteerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVolunteerProfileRequest;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\VolunteerPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The volunteer's own profile data: contacts, education, roles, availability (FR-005).
 */
class ProfileController extends Controller
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $volunteer = $this->volunteerOf($user);
        Gate::authorize('viewSensitive', $volunteer);

        $volunteer->load(['skills.teamRole', 'availabilities', 'user']);

        return Inertia::render('volunteer/profile/edit', [
            'volunteer' => VolunteerPresenter::full($volunteer),
            'canEditBirthDate' => $user->isCoordinator() || $volunteer->canEditBirthDate(),
            ...VolunteerPresenter::formOptions(),
        ]);
    }

    public function update(UpdateVolunteerProfileRequest $request, UpdateVolunteerProfile $updateProfile): RedirectResponse
    {
        $volunteer = $request->volunteer();
        abort_if($volunteer === null, 404);

        $updateProfile->handle($volunteer, $request->validated(), $request->input('guardian_phone_e164'), $request->input('whatsapp_phone_e164'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados guardados.']);

        return to_route('volunteer.profile.edit');
    }

    private function volunteerOf(User $user): Volunteer
    {
        abort_if($user->volunteer === null, 404, 'Esta conta não tem perfil de voluntário.');

        return $user->volunteer;
    }
}
