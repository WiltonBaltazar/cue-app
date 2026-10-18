<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schedule;

// In-app notifications are kept 90 days (FR-034).
Schedule::call(fn () => DatabaseNotification::query()
    ->where('created_at', '<', now()->subDays(90))
    ->delete())
    ->daily()
    ->name('prune-notifications')
    ->withoutOverlapping();
