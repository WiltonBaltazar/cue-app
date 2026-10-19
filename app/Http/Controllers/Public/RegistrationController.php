<?php

namespace App\Http\Controllers\Public;

use App\Actions\Registration\SubmitRegistration;
use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Availability;
use App\Models\ConsentVersion;
use App\Models\TeamRole;
use App\Models\Volunteer;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Honeypot\Honeypot;

/**
 * Public registration link (US1, FR-001). Coordinators can close it (FR-001, settings).
 */
class RegistrationController extends Controller
{
    public function create(Settings $settings, Honeypot $honeypot): Response
    {
        if (! $settings->registrationIsOpen()) {
            return Inertia::render('registration/closed');
        }

        $declaration = ConsentVersion::current();

        return Inertia::render('registration/create', [
            'declaration' => ['version' => $declaration->version, 'body' => $declaration->body],
            'teamRoles' => TeamRole::query()->active()->ordered()->get(['id', 'name', 'description']),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->selfAssessmentLabel()]),
            'frequencies' => collect(Frequency::cases())->map(fn (Frequency $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()]),
            'periods' => collect(Period::cases())->map(fn (Period $period) => ['value' => $period->value, 'label' => $period->label()]),
            'weekdays' => Availability::weekdayOptions(),
            'trainingAvailabilities' => collect(TrainingAvailability::cases())->map(fn (TrainingAvailability $option) => ['value' => $option->value, 'label' => $option->label()]),
            'socialNetworks' => Volunteer::SOCIAL_NETWORKS,
            'honeypot' => $honeypot->toArray(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    public function store(StoreRegistrationRequest $request, SubmitRegistration $submitRegistration, Settings $settings): RedirectResponse
    {
        abort_unless($settings->registrationIsOpen(), 403, 'O cadastro está fechado.');

        $submitRegistration->handle($request->validated() + $request->only(['phone_e164', 'guardian_phone_e164', 'whatsapp_phone_e164']));

        return to_route('registration.submitted');
    }

    public function submitted(): Response
    {
        return Inertia::render('registration/submitted');
    }
}
