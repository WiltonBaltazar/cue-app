<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('perfil', '/perfil/conta');

    Route::get('perfil/conta', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('perfil/conta', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('perfil/seguranca', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('perfil/palavra-passe', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('perfil/sessoes', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('perfil/sessoes/outras', [SessionController::class, 'destroyOthers'])
        ->middleware(RequirePassword::class)
        ->name('sessions.destroy-others');
    Route::delete('perfil/sessoes/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    Route::inertia('perfil/aparencia', 'settings/appearance')->name('appearance.edit');
});
