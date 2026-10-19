<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Volunteers\CreateServingProfile;
use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\ConsentVersion;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Também sirvo na equipa": a coordinator creates their own volunteer profile so they
 * can be placed on schedules. Afterwards it is edited like any volunteer's (Os meus dados).
 */
class ServingProfileController extends Controller
{
    public function create(#[CurrentUser] User $user): Response|RedirectResponse
    {
        if ($user->volunteer !== null) {
            return to_route('volunteer.profile.edit');
        }

        $declaration = ConsentVersion::current();

        return Inertia::render('coord/serving-profile/create', [
            'name' => $user->name,
            'declaration' => ['version' => $declaration->version, 'body' => $declaration->body],
            'teamRoles' => TeamRole::query()->active()->ordered()->get(['id', 'name', 'description']),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
            'frequencies' => collect(Frequency::cases())->map(fn (Frequency $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()]),
            'periods' => collect(Period::cases())->map(fn (Period $period) => ['value' => $period->value, 'label' => $period->label()]),
            'weekdays' => Availability::weekdayOptions(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateServingProfile $createProfile): RedirectResponse
    {
        $today = LocalTime::today();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:160'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today->subYears(Volunteer::MINIMUM_AGE)->toDateString()],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*.team_role_id' => ['required', 'integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'skills.*.level' => ['required', new Enum(Level::class)],
            'preferred_role_id' => ['nullable', 'integer', Rule::in($request->collect('skills')->pluck('team_role_id')->all())],
            'availability' => ['required', 'array', 'min:1'],
            'availability.*.weekday' => ['required', 'integer', 'between:1,7'],
            'availability.*.period' => ['required', new Enum(Period::class)],
            'max_frequency' => ['required', new Enum(Frequency::class)],
            'consent_accepted' => ['accepted'],
            'consent_version' => ['required', 'integer', Rule::in([ConsentVersion::currentNumber()])],
        ], [
            'skills.required' => 'Escolha pelo menos uma função.',
            'availability.required' => 'Indique pelo menos um dia e período em que pode servir.',
            'consent_accepted.accepted' => 'Para continuar tem de aceitar a declaração.',
            'consent_version.in' => 'A declaração foi actualizada entretanto. Leia a nova versão.',
        ]);

        $createProfile->handle($user, [
            ...$validated,
            'consent_version' => (int) $validated['consent_version'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Já pode ser escalado. Vai aparecer na lista de cada função que escolheu.']);

        return to_route('coord.volunteers.index');
    }
}
