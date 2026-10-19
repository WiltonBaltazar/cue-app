<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Volunteers\UpdateAvailability;
use App\Enums\Frequency;
use App\Enums\Period;
use App\Enums\VolunteerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVolunteerProfileRequest;
use App\Models\Availability;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\VolunteerPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
            'canEditIdentity' => in_array($volunteer->status, [VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection], true),
            'teamRoles' => TeamRole::query()->active()->ordered()->get(['id', 'name', 'description']),
            'frequencies' => collect(Frequency::cases())->map(fn (Frequency $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()]),
            'periods' => collect(Period::cases())->map(fn (Period $period) => ['value' => $period->value, 'label' => $period->label()]),
            'weekdays' => collect(Availability::WEEKDAYS)->map(fn (string $label, int $day) => ['value' => $day, 'label' => $label])->values(),
        ]);
    }

    public function update(UpdateVolunteerProfileRequest $request, UpdateAvailability $updateAvailability): RedirectResponse
    {
        $volunteer = $request->volunteer();
        abort_if($volunteer === null, 404);

        $data = $request->validated();

        DB::transaction(function () use ($volunteer, $data, $request, $updateAvailability): void {
            $volunteer->fill([
                ...array_intersect_key($data, array_flip([
                    'full_name', 'birth_date', 'neighbourhood', 'guardian_name', 'education_level',
                    'training', 'commitments', 'motivations', 'comments', 'can_support_others',
                    'available_for_training',
                ])),
                'guardian_phone' => $request->input('guardian_phone_e164'),
            ]);
            $volunteer->save();

            if (isset($data['full_name'])) {
                $volunteer->user?->update(['name' => $data['full_name']]);
            }

            $this->syncSkills($volunteer, $data['skills'], $data['preferred_role_id'] ?? null);

            $updateAvailability->handle(
                $volunteer,
                $data['availability'],
                Frequency::from($data['max_frequency']),
                (bool) ($data['variable_hours'] ?? false),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados guardados.']);

        return to_route('volunteer.profile.edit');
    }

    /**
     * Keeps coordinator-set levels for roles that stay; new roles start as beginner
     * (column default).
     *
     * @param  list<array{team_role_id: int, has_experience?: bool}>  $skills
     */
    private function syncSkills(Volunteer $volunteer, array $skills, ?int $preferredRoleId): void
    {
        $chosen = collect($skills)->keyBy(fn (array $skill) => (int) $skill['team_role_id']);

        $volunteer->skills()->whereNotIn('team_role_id', $chosen->keys())->delete();

        foreach ($chosen as $roleId => $skill) {
            $volunteer->skills()->updateOrCreate(
                ['team_role_id' => $roleId],
                [
                    'interested' => true,
                    'has_experience' => (bool) ($skill['has_experience'] ?? false),
                    'is_preferred' => $roleId === $preferredRoleId,
                ],
            );
        }
    }

    private function volunteerOf(User $user): Volunteer
    {
        abort_if($user->volunteer === null, 404, 'Esta conta não tem perfil de voluntário.');

        return $user->volunteer;
    }
}
