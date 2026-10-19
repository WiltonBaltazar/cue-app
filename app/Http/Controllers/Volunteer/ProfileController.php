<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Volunteers\UpdateAvailability;
use App\Enums\Frequency;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
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
            'weekdays' => Availability::weekdayOptions(),
            'socialNetworks' => Volunteer::SOCIAL_NETWORKS,
            'trainingAvailabilities' => collect(TrainingAvailability::cases())->map(fn (TrainingAvailability $option) => ['value' => $option->value, 'label' => $option->label()]),
        ]);
    }

    public function update(UpdateVolunteerProfileRequest $request, UpdateAvailability $updateAvailability): RedirectResponse
    {
        $volunteer = $request->volunteer();
        abort_if($volunteer === null, 404);

        $data = $request->validated();

        $noExperience = (bool) ($data['no_experience'] ?? false);
        $experienceRoleIds = $noExperience ? [] : array_values(array_map('intval', $data['experience_role_ids'] ?? []));

        DB::transaction(function () use ($volunteer, $data, $request, $updateAvailability, $noExperience, $experienceRoleIds): void {
            $volunteer->fill([
                ...array_intersect_key($data, array_flip([
                    'full_name', 'nickname', 'birth_date', 'neighbourhood', 'phone_has_whatsapp',
                    'guardian_name', 'education_level', 'training', 'other_area', 'experience_description',
                    'commitments', 'motivations', 'expectations', 'goals', 'comments', 'suggestions',
                    'can_support_others', 'frequency_note', 'other_days_by_arrangement', 'training_availability',
                ])),
                'experience_role_ids' => $experienceRoleIds,
                'other_experience' => $noExperience ? null : ($data['other_experience'] ?? null),
                'guardian_phone' => $request->input('guardian_phone_e164'),
                'whatsapp_phone' => ($data['phone_has_whatsapp'] ?? false) ? null : $request->input('whatsapp_phone_e164'),
                'social_media' => array_values($data['social_media'] ?? []),
            ]);
            $volunteer->save();

            if (isset($data['full_name'])) {
                $volunteer->user?->update(['name' => $data['full_name']]);
            }

            $this->syncSkills($volunteer, $data['skills'] ?? [], $experienceRoleIds, $data['preferred_role_id'] ?? null);

            $updateAvailability->handle(
                $volunteer,
                $data['availability'] ?? [],
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
     * @param  list<array{team_role_id: int}>  $skills
     * @param  list<int>  $experienceRoleIds
     */
    private function syncSkills(Volunteer $volunteer, array $skills, array $experienceRoleIds, ?int $preferredRoleId): void
    {
        $chosen = collect($skills)->keyBy(fn (array $skill) => (int) $skill['team_role_id']);

        $volunteer->skills()->whereNotIn('team_role_id', $chosen->keys())->delete();

        foreach ($chosen as $roleId => $skill) {
            $volunteer->skills()->updateOrCreate(
                ['team_role_id' => $roleId],
                [
                    'interested' => true,
                    'has_experience' => in_array($roleId, $experienceRoleIds, true),
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
