<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\ScheduleStatus;
use App\Models\Assignment;
use App\Notifications\AppNotification;
use App\Notifications\ShiftCheck;
use App\Notifications\ShiftReminder;
use App\Support\Settings;
use App\Support\ShiftDescription;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('shifts:remind')]
#[Description('Send the 72 h shift check and the 24 h reminder, once per assignment')]
class SendShiftReminders extends Command
{
    public function handle(Settings $settings): int
    {
        $now = CarbonImmutable::now();

        // Reminders first: a shift booked inside the last 24 h gets only the reminder.
        $reminders = $this->send(
            $now,
            $settings->reminderHoursBefore(),
            'reminder_sent_at',
            fn (Assignment $assignment, string $shift, int $seconds) => new ShiftReminder($assignment->id, $shift, $seconds),
            alsoMark: ['check_sent_at'],
        );

        $checks = $this->send(
            $now,
            $settings->checkReminderHoursBefore(),
            'check_sent_at',
            fn (Assignment $assignment, string $shift, int $seconds) => new ShiftCheck($assignment->id, $shift, $seconds),
        );

        $this->info("{$checks} confirmações e {$reminders} lembretes enviados.");

        return self::SUCCESS;
    }

    /**
     * @param  callable(Assignment, string, int): AppNotification  $notification
     * @param  list<string>  $alsoMark
     */
    private function send(CarbonImmutable $now, int $hoursBefore, string $column, callable $notification, array $alsoMark = []): int
    {
        $sent = 0;

        Assignment::query()
            ->active()
            ->whereNull($column)
            ->whereHas('schedule', fn (Builder $query) => $query->where('status', ScheduleStatus::Published))
            ->whereHas('event', fn (Builder $query) => $query
                ->where('status', '!=', EventStatus::Cancelled)
                ->where('arrival_at', '>', $now)
                ->where('arrival_at', '<=', $now->addHours($hoursBefore)))
            ->with(['event', 'position.teamRole', 'volunteer.user'])
            ->eachById(function (Assignment $assignment) use ($now, $column, $notification, $alsoMark, &$sent): void {
                // Claim the row first so overlapping runs never send twice. A query update keeps
                // the coordinator's lock_version and the audit log untouched.
                $marks = [$column => $now];

                foreach ($alsoMark as $other) {
                    if ($assignment->{$other} === null) {
                        $marks[$other] = $now;
                    }
                }

                $claimed = Assignment::query()->whereKey($assignment->id)->whereNull($column)->update($marks);

                if ($claimed === 0 || $assignment->volunteer->user === null) {
                    return;
                }

                $secondsToStart = (int) $now->diffInSeconds($assignment->event->starts_at);

                $assignment->volunteer->user->notify($notification($assignment, ShiftDescription::of($assignment), $secondsToStart));
                $sent++;
            });

        return $sent;
    }
}
