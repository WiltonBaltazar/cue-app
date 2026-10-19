<?php

use App\Http\Controllers\AppApi\NotificationController;
use App\Http\Controllers\AppApi\OverviewController;
use App\Http\Controllers\AppApi\PositionCandidateController;
use App\Http\Controllers\AppApi\PushSubscriptionController;
use App\Http\Controllers\AppApi\SwapCandidateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal JSON endpoints (contracts/app-api.md)
|--------------------------------------------------------------------------
|
| Same-origin only, used by TanStack Query inside Inertia pages. Loaded from
| bootstrap/app.php with the "api" group, Sanctum's stateful session
| (`auth:sanctum`), the "app-api" rate limiter, the /app-api prefix and
| the "app-api." name prefix. Not a public API: no tokens are issued.
|
*/

Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

// This device's Web Push subscription (US6).
Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

// Colleagues who could take one of my shifts (directed swap).
Route::get('assignments/{assignment}/swap-candidates', SwapCandidateController::class)->name('assignments.swap-candidates');

// Coordinator-only: schedule editor helpers.
Route::middleware('role:coordinator')->group(function () {
    Route::get('positions/{position}/candidates', [PositionCandidateController::class, 'index'])->name('positions.candidates');
    Route::post('assignments/check', [PositionCandidateController::class, 'check'])->name('assignments.check');

    // Overview (US5): availability matrix and event coverage.
    Route::get('availability-matrix', [OverviewController::class, 'availability'])->name('availability-matrix');
    Route::get('coverage', [OverviewController::class, 'coverage'])->name('coverage');
});
