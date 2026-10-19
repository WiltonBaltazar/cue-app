<?php

use App\Models\TeamRole;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\TeamRoleSeeder;

test('settings fall back to defaults and persist changes', function () {
    $settings = app(Settings::class);

    expect($settings->reminderHoursBefore())->toBe(24)
        ->and($settings->registrationIsOpen())->toBeTrue();

    $settings->set('reminder_hours_before', 12);

    expect(app(Settings::class)->reminderHoursBefore())->toBe(12);
});

test('registration closes when switched off or after the closing date', function () {
    $settings = app(Settings::class);

    $settings->set('registration_closes_at', now()->subMinute()->toIso8601String());
    expect($settings->registrationIsOpen())->toBeFalse();

    $settings->set('registration_closes_at', now()->addDay()->toIso8601String());
    expect($settings->registrationIsOpen())->toBeTrue();

    $settings->set('registration_open', false);
    expect($settings->registrationIsOpen())->toBeFalse();
});

test('unknown settings are rejected', function () {
    app(Settings::class)->get('not_a_setting');
})->throws(InvalidArgumentException::class);

test('team role seeder loads the 11 roles from the registration form', function () {
    $this->seed(TeamRoleSeeder::class);
    $this->seed(TeamRoleSeeder::class);

    expect(TeamRole::query()->count())->toBe(11)
        ->and(TeamRole::query()->ordered()->first()?->name)->toBe('Operação de câmara');
});

test('coordinators save swap, horizon and reminder settings', function () {
    $coordinator = User::factory()->coordinator()->create();

    $this->actingAs($coordinator)
        ->put(route('coord.settings.update'), [
            'registration_open' => true,
            'registration_closes_at' => null,
            'swap_auto_approve' => true,
            'urgent_threshold_hours' => 72,
            'event_horizon_months' => 6,
            'check_reminder_hours_before' => 48,
            'reminder_hours_before' => 12,
        ])
        ->assertSessionHasNoErrors();

    $settings = app(Settings::class);

    expect($settings->swapAutoApprove())->toBeTrue()
        ->and($settings->urgentThresholdHours())->toBe(72)
        ->and($settings->eventHorizonMonths())->toBe(6)
        ->and($settings->checkReminderHoursBefore())->toBe(48)
        ->and($settings->reminderHoursBefore())->toBe(12);

    $this->actingAs($coordinator)
        ->put(route('coord.settings.update'), [
            'registration_open' => true,
            'swap_auto_approve' => false,
            'urgent_threshold_hours' => 0,
            'event_horizon_months' => 40,
            'check_reminder_hours_before' => 48,
            'reminder_hours_before' => 12,
        ])
        ->assertSessionHasErrors(['urgent_threshold_hours', 'event_horizon_months']);
});
