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

// Recurring activities always have occurrences for the configured horizon (US2).
Schedule::command('events:generate')->dailyAt('02:00')->withoutOverlapping();

// Swap requests stop being actionable once the event starts (US4).
Schedule::command('swaps:expire')->everyFifteenMinutes()->withoutOverlapping();

// Pauses with a return date end on that date (FR-005a).
Schedule::command('volunteers:resume-paused')->dailyAt('00:15')->withoutOverlapping();
