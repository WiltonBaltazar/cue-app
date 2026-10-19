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

// 72 h "Ainda pode ir?" and 24 h reminder, each once per assignment (US6).
Schedule::command('shifts:remind')->everyFiveMinutes()->withoutOverlapping();

// Rejected registrations are deleted after 30 days, keeping only the phone hash (FR-003a).
Schedule::command('registrations:purge-rejected')->dailyAt('03:00')->withoutOverlapping();

// Data-removal requests are carried out once their 7 days are over (FR-028).
Schedule::command('volunteers:process-removals')->hourly()->withoutOverlapping();

// Nightly encrypted off-site backup kept 30 days; a daily check mails when the newest is
// missing or older than a day (research R21, docs/deployment.md).
Schedule::command('backup:clean')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('07:00');

// Shared hosting has no Supervisor: a worker started every minute runs the queue
// (push, e-mail) for 50 s and exits. Off where Supervisor runs one (deployment.md).
if (config('queue.work_from_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --sleep=3 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(2)
        ->runInBackground();
}
