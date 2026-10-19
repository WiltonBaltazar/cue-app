<?php

use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Coordinator\ConsentController as CoordinatorConsentController;
use App\Http\Controllers\Coordinator\RegistrationController as CoordinatorRegistrationController;
use App\Http\Controllers\Coordinator\SettingsController;
use App\Http\Controllers\Coordinator\VolunteerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Volunteer\ConsentController;
use App\Http\Controllers\Volunteer\ProfileController as VolunteerProfileController;
use App\Http\Controllers\Volunteer\StatusController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

// Public registration link (US1). Abuse protection: rate limit + honeypot + Turnstile (FR-033).
Route::get('cadastro', [RegistrationController::class, 'create'])->name('registration.create');
Route::post('cadastro', [RegistrationController::class, 'store'])
    ->middleware(['throttle:registration', ProtectAgainstSpam::class])
    ->name('registration.store');
Route::get('cadastro/enviado', [RegistrationController::class, 'submitted'])->name('registration.submitted');

// Coordinator-issued password links for accounts without e-mail (FR-006).
Route::get('definir-senha/{token}', [PasswordSetupController::class, 'show'])->name('password-setup.show');
Route::post('definir-senha/{token}', [PasswordSetupController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('password-setup.store');

Route::middleware(['auth', 'volunteer.active'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
});

Route::middleware('auth')->group(function () {
    Route::get('estado', [StatusController::class, 'show'])->name('volunteer.status');
    Route::post('estado/reenviar', [StatusController::class, 'resubmit'])->name('volunteer.resubmit');

    Route::get('declaracao', [ConsentController::class, 'show'])->name('volunteer.consent');
    Route::post('declaracao', [ConsentController::class, 'accept'])->name('volunteer.consent.accept');

    // Reachable while a registration needs correction, so the volunteer can fix it.
    Route::get('perfil/dados', [VolunteerProfileController::class, 'edit'])->name('volunteer.profile.edit');
    Route::put('perfil/dados', [VolunteerProfileController::class, 'update'])->name('volunteer.profile.update');
});

Route::middleware(['auth', 'role:coordinator'])
    ->prefix('gestao')
    ->name('coord.')
    ->group(function () {
        Route::inertia('/', 'coord/dashboard')->name('dashboard');

        Route::get('cadastros', [CoordinatorRegistrationController::class, 'index'])->name('registrations.index');
        Route::get('cadastros/{volunteer}', [CoordinatorRegistrationController::class, 'show'])->name('registrations.show');
        Route::post('cadastros/{volunteer}/aprovar', [CoordinatorRegistrationController::class, 'approve'])->name('registrations.approve');
        Route::post('cadastros/{volunteer}/rejeitar', [CoordinatorRegistrationController::class, 'reject'])->name('registrations.reject');
        Route::post('cadastros/{volunteer}/correcao', [CoordinatorRegistrationController::class, 'correction'])->name('registrations.correction');
        Route::post('cadastros/{volunteer}/reverter', [CoordinatorRegistrationController::class, 'reverse'])->name('registrations.reverse');

        Route::get('voluntarios', [VolunteerController::class, 'index'])->name('volunteers.index');
        Route::get('voluntarios/{volunteer}', [VolunteerController::class, 'edit'])->name('volunteers.edit');
        Route::put('voluntarios/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update');
        Route::post('voluntarios/{volunteer}/link-redefinicao', [VolunteerController::class, 'issueResetLink'])->name('reset-links.store');

        Route::get('definicoes', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('definicoes', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('declaracao', [CoordinatorConsentController::class, 'edit'])->name('consent.edit');
        Route::post('declaracao', [CoordinatorConsentController::class, 'store'])->name('consent.store');
    });

require __DIR__.'/settings.php';
