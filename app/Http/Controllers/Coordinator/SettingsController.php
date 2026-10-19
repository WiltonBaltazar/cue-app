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
 * Coordinator settings (FR-001, FR-018, US2-1, US4-4, US6-2).
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
            'swapAutoApprove' => $settings->swapAutoApprove(),
            'urgentThresholdHours' => $settings->urgentThresholdHours(),
            'eventHorizonMonths' => $settings->eventHorizonMonths(),
            'checkReminderHoursBefore' => $settings->checkReminderHoursBefore(),
            'reminderHoursBefore' => $settings->reminderHoursBefore(),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'registration_open' => ['required', 'boolean'],
            // Entered in Maputo time; stored in UTC.
            'registration_closes_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now'],
            'swap_auto_approve' => ['required', 'boolean'],
            'urgent_threshold_hours' => ['required', 'integer', 'between:1,336'],
            'event_horizon_months' => ['required', 'integer', 'between:1,12'],
            'check_reminder_hours_before' => ['required', 'integer', 'between:1,336'],
            'reminder_hours_before' => ['required', 'integer', 'between:1,168'],
        ], [
            'registration_closes_at.after' => 'A data de encerramento tem de ser no futuro.',
        ]);

        $closesAt = isset($validated['registration_closes_at'])
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['registration_closes_at'], config('app.display_timezone'))?->utc()->toIso8601String()
            : null;

        $values = [
            'registration_open' => (bool) $validated['registration_open'],
            'registration_closes_at' => $closesAt,
            'swap_auto_approve' => (bool) $validated['swap_auto_approve'],
            'urgent_threshold_hours' => (int) $validated['urgent_threshold_hours'],
            'event_horizon_months' => (int) $validated['event_horizon_months'],
            'check_reminder_hours_before' => (int) $validated['check_reminder_hours_before'],
            'reminder_hours_before' => (int) $validated['reminder_hours_before'],
        ];

        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }

        // Settings changes are audited (FR-035); none of these values are personal.
        activity('audit')->causedBy($request->user())->event('settings_updated')
            ->withProperties(['attributes' => $values])
            ->log('settings_updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Definições guardadas.']);

        return to_route('coord.settings.edit');
    }
}
