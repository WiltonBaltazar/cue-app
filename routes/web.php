<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
});

Route::middleware(['auth', 'role:coordinator'])
    ->prefix('gestao')
    ->name('coord.')
    ->group(function () {
        Route::inertia('/', 'coord/dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
