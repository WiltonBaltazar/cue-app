<?php

namespace App\Actions\Swaps;

use App\Enums\AssignmentStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\ReplacementNeeded;
use App\Support\LocalTime;
use App\Support\Settings;
use App\Support\ShiftDescription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Marks a volunteer's upcoming published shifts in a date range as "Por substituir" and
 * tells coordinators (absence, pause; US4-1, FR-005a). The reason is never passed on.
 */
class ReplacementFlagger
{
    public function __construct(private Settings $settings) {}

    /**
     * @return int shifts flagged
     */
    public function flag(Volunteer $volunteer, CarbonImmutable $from, ?CarbonImmutable $to = null): int
    {
        $today = LocalTime::today();
        $from = $from->max($today);

        $shifts = $volunteer->assignments()
            ->active()
            ->whereHas('schedule', fn ($query) => $query->published())
            ->whereHas('event', fn ($query) => $query
                ->scheduled()
                ->where('date', '>=', $from->toDateString())
                ->when($to !== null, fn ($query) => $query->where('date', '<=', $to?->toDateString())))
            ->with(['event', 'position.teamRole'])
            ->get();

        foreach ($shifts as $assignment) {
            $assignment->status = AssignmentStatus::NeedsReplacement;
            $assignment->save();

            $this->notifyCoordinators($volunteer, $assignment);
        }

        return $shifts->count();
    }

    public function notifyCoordinators(Volunteer $volunteer, Assignment $assignment): void
    {
        Notification::send(
            User::query()->where('role', UserRole::Coordinator)->get(),
            new ReplacementNeeded($volunteer->shortName(), ShiftDescription::of($assignment), $this->isUrgent($assignment->event), $assignment->schedule_id),
        );
    }

    /**
     * Less than the configured threshold (default 48 h) before the event (US4-4).
     */
    public function isUrgent(Event $event): bool
    {
        return $event->starts_at->lessThan(now()->addHours($this->settings->urgentThresholdHours()));
    }
}
