<?php

use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Coordinator\ActivityTemplateController;
use App\Http\Controllers\Coordinator\AssignmentController;
use App\Http\Controllers\Coordinator\AuditController;
use App\Http\Controllers\Coordinator\ConsentController as CoordinatorConsentController;
use App\Http\Controllers\Coordinator\DashboardController;
use App\Http\Controllers\Coordinator\EventController;
use App\Http\Controllers\Coordinator\OverviewController;
use App\Http\Controllers\Coordinator\RegistrationController as CoordinatorRegistrationController;
use App\Http\Controllers\Coordinator\ScheduleController;
use App\Http\Controllers\Coordinator\SchedulePdfController;
use App\Http\Controllers\Coordinator\SettingsController;
use App\Http\Controllers\Coordinator\SwapController as CoordinatorSwapController;
use App\Http\Controllers\Coordinator\TeamRoleController;
use App\Http\Controllers\Coordinator\VolunteerController;
use App\Http\Controllers\Coordinator\VolunteerHistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Volunteer\AbsenceController;
use App\Http\Controllers\Volunteer\ConsentController;
use App\Http\Controllers\Volunteer\DataRemovalController;
use App\Http\Controllers\Volunteer\NotificationPreferenceController;
use App\Http\Controllers\Volunteer\PauseController;
use App\Http\Controllers\Volunteer\ProfileController as VolunteerProfileController;
use App\Http\Controllers\Volunteer\ShiftController;
use App\Http\Controllers\Volunteer\StatusController;
use App\Http\Controllers\Volunteer\SwapController;
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
    Route::get('minha-escala', [ShiftController::class, 'index'])->name('volunteer.shifts.index');
    Route::get('escala/{schedule}', [ShiftController::class, 'team'])->name('schedules.show');
    Route::post('minha-escala/{assignment}/recusar', [ShiftController::class, 'decline'])->name('volunteer.shifts.decline');

    Route::get('ausencias', [AbsenceController::class, 'index'])->name('absences.index');
    Route::post('ausencias', [AbsenceController::class, 'store'])->name('absences.store');
    Route::delete('ausencias/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');

    Route::get('trocas', [SwapController::class, 'index'])->name('swaps.index');
    Route::post('trocas', [SwapController::class, 'store'])->name('swaps.store');
    Route::post('trocas/{swap}/aceitar', [SwapController::class, 'accept'])->name('swaps.accept');
    Route::post('trocas/{swap}/cancelar', [SwapController::class, 'cancel'])->name('swaps.cancel');
});

Route::middleware('auth')->group(function () {
    Route::get('estado', [StatusController::class, 'show'])->name('volunteer.status');
    Route::post('estado/reenviar', [StatusController::class, 'resubmit'])->name('volunteer.resubmit');

    Route::get('declaracao', [ConsentController::class, 'show'])->name('volunteer.consent');
    Route::post('declaracao', [ConsentController::class, 'accept'])->name('volunteer.consent.accept');

    // Reachable while a registration needs correction, so the volunteer can fix it.
    Route::get('perfil/dados', [VolunteerProfileController::class, 'edit'])->name('volunteer.profile.edit');
    Route::put('perfil/dados', [VolunteerProfileController::class, 'update'])->name('volunteer.profile.update');

    // Coordinators have notification preferences too (FR-025).
    Route::get('perfil/notificacoes', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('perfil/notificacoes', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    // Paused volunteers keep access so they can resume (FR-005a).
    Route::post('perfil/pausa', [PauseController::class, 'pause'])->name('profile.pause');
    Route::post('perfil/retomar', [PauseController::class, 'resume'])->name('profile.resume');

    // Data removal: anonymised 7 days after the request, cancellable until then (FR-028).
    Route::post('perfil/remover-dados', [DataRemovalController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('data-removal.store');
    Route::delete('perfil/remover-dados', [DataRemovalController::class, 'destroy'])->name('data-removal.destroy');
});

Route::middleware(['auth', 'role:coordinator'])
    ->prefix('gestao')
    ->name('coord.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('cadastros', [CoordinatorRegistrationController::class, 'index'])->name('registrations.index');
        Route::get('cadastros/{volunteer}', [CoordinatorRegistrationController::class, 'show'])->name('registrations.show');
        Route::post('cadastros/{volunteer}/aprovar', [CoordinatorRegistrationController::class, 'approve'])->name('registrations.approve');
        Route::post('cadastros/{volunteer}/rejeitar', [CoordinatorRegistrationController::class, 'reject'])->name('registrations.reject');
        Route::post('cadastros/{volunteer}/correcao', [CoordinatorRegistrationController::class, 'correction'])->name('registrations.correction');
        Route::post('cadastros/{volunteer}/reverter', [CoordinatorRegistrationController::class, 'reverse'])->name('registrations.reverse');

        Route::get('voluntarios', [VolunteerController::class, 'index'])->name('volunteers.index');
        Route::get('voluntarios/{volunteer}', [VolunteerController::class, 'edit'])->name('volunteers.edit');
        Route::put('voluntarios/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update');
        Route::get('voluntarios/{volunteer}/historico', VolunteerHistoryController::class)->name('volunteers.history');
        Route::post('voluntarios/{volunteer}/link-redefinicao', [VolunteerController::class, 'issueResetLink'])->name('reset-links.store');
        Route::post('voluntarios/{volunteer}/pausa', [VolunteerController::class, 'pause'])->name('volunteers.pause');
        Route::post('voluntarios/{volunteer}/retomar', [VolunteerController::class, 'resume'])->name('volunteers.resume');

        Route::get('trocas', [CoordinatorSwapController::class, 'index'])->name('swaps.index');
        Route::post('trocas/{swap}/decidir', [CoordinatorSwapController::class, 'decide'])->name('swaps.decide');

        Route::get('funcoes', [TeamRoleController::class, 'index'])->name('team-roles.index');
        Route::post('funcoes', [TeamRoleController::class, 'store'])->name('team-roles.store');
        Route::put('funcoes/{teamRole}', [TeamRoleController::class, 'update'])->name('team-roles.update');

        Route::get('modelos', [ActivityTemplateController::class, 'index'])->name('templates.index');
        Route::get('modelos/novo', [ActivityTemplateController::class, 'create'])->name('templates.create');
        Route::post('modelos', [ActivityTemplateController::class, 'store'])->name('templates.store');
        Route::get('modelos/{template}', [ActivityTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('modelos/{template}', [ActivityTemplateController::class, 'update'])->name('templates.update');

        Route::get('eventos', [EventController::class, 'index'])->name('events.index');
        Route::get('eventos/novo', [EventController::class, 'create'])->name('events.create');
        Route::post('eventos', [EventController::class, 'store'])->name('events.store');
        Route::get('eventos/{event}', [EventController::class, 'show'])->name('events.show');
        Route::get('eventos/{event}/editar', [EventController::class, 'edit'])->name('events.edit');
        Route::put('eventos/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('eventos/{event}/cancelar', [EventController::class, 'cancel'])->name('events.cancel');

        Route::get('escalas', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('escalas', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('escalas/{schedule}', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::post('escalas/{schedule}/gerar', [ScheduleController::class, 'generate'])->name('schedules.generate');
        Route::post('escalas/{schedule}/publicar', [ScheduleController::class, 'publish'])->name('schedules.publish');
        Route::get('escalas/{schedule}/pdf', SchedulePdfController::class)->name('schedules.pdf');

        Route::post('atribuicoes', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::put('atribuicoes/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('atribuicoes/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');

        Route::get('disponibilidade', [OverviewController::class, 'availability'])->name('availability');
        Route::get('cobertura', [OverviewController::class, 'coverage'])->name('coverage');

        Route::get('definicoes', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('definicoes', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('declaracao', [CoordinatorConsentController::class, 'edit'])->name('consent.edit');
        Route::post('declaracao', [CoordinatorConsentController::class, 'store'])->name('consent.store');

        Route::get('auditoria', AuditController::class)->name('audit');
    });

require __DIR__.'/settings.php';
