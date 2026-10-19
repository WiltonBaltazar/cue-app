<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coordinator settings. Registration open/close now (FR-001); swap and reminder
 * settings join with User Story 4 (T104).
 */
class SettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('coord/settings', [
            'registrationOpen' => (bool) $settings->get('registration_open'),
            'registrationClosesAt' => $settings->registrationClosesAt()?->setTimezone(config('app.display_timezone'))->format('Y-m-d\TH:i'),
            'registrationIsOpen' => $settings->registrationIsOpen(),
            'registrationUrl' => route('registration.create'),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'registration_open' => ['required', 'boolean'],
            // Entered in Maputo time; stored in UTC.
            'registration_closes_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now'],
        ], [
            'registration_closes_at.after' => 'A data de encerramento tem de ser no futuro.',
        ]);

        $closesAt = isset($validated['registration_closes_at'])
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['registration_closes_at'], config('app.display_timezone'))?->utc()->toIso8601String()
            : null;

        $settings->set('registration_open', (bool) $validated['registration_open']);
        $settings->set('registration_closes_at', $closesAt);

        activity('audit')->causedBy($request->user())->event('settings_updated')
            ->withProperties(['attributes' => ['registration_open' => (bool) $validated['registration_open'], 'registration_closes_at' => $closesAt]])
            ->log('settings_updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Definições guardadas.']);

        return to_route('coord.settings.edit');
    }
}
