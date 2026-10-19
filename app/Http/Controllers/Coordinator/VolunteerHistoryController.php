<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\AssignmentStatus;
use App\Enums\EventStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SwapStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SwapRequest;
use App\Models\Volunteer;
use App\Support\LocalTime;
use App\Support\ShiftDescription;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One volunteer's participation: shifts done, declines, swaps and real vs declared
 * frequency (US5-4). Absence reasons are never shown here.
 */
class VolunteerHistoryController extends Controller
{
    /** Months used for the real frequency. */
    public const MONTHS = 6;

    public function __invoke(Volunteer $volunteer): Response
    {
        Gate::authorize('review', $volunteer);

        $today = LocalTime::today();
        // Count from approval when it was less than MONTHS ago, so new volunteers are not
        // shown as serving less than they really do.
        $since = $today->subMonths(self::MONTHS);
        $joined = $volunteer->reviewed_at ?? $volunteer->created_at;
        if ($joined !== null && $joined->greaterThan($since)) {
            $since = $joined->setTimezone(LocalTime::zone())->startOfDay();
        }

        $assignments = Assignment::query()
            ->where('volunteer_id', $volunteer->id)
            ->whereHas('schedule', fn ($query) => $query->where('status', ScheduleStatus::Published))
            ->with(['event', 'position.teamRole'])
            ->get()
            ->sortByDesc(fn (Assignment $assignment) => $assignment->event->starts_at->getTimestamp())
            ->values();

        $isPast = fn (Assignment $assignment) => $assignment->event->date->lessThan($today);
        $done = $assignments->filter(fn (Assignment $assignment) => $isPast($assignment)
            && $assignment->status === AssignmentStatus::Confirmed
            && $assignment->event->status === EventStatus::Scheduled);

        $doneInWindow = $done->filter(fn (Assignment $assignment) => $assignment->event->date->greaterThanOrEqualTo($since->startOfDay()))->count();
        $months = max(1.0, $since->diffInDays($today) / 30.44);

        return Inertia::render('coord/volunteers/history', [
            'volunteer' => [
                'id' => $volunteer->id,
                'name' => $volunteer->full_name,
                'status' => $volunteer->status->label(),
                'declaredFrequency' => $volunteer->max_frequency->label(),
            ],
            'stats' => [
                'shiftsDone' => $done->count(),
                'upcoming' => $assignments->filter(fn (Assignment $assignment) => ! $isPast($assignment)
                    && $assignment->status === AssignmentStatus::Confirmed
                    && $assignment->event->status === EventStatus::Scheduled)->count(),
                // A decline records when the volunteer answered; a shift handed over by an
                // approved swap does not.
                'declines' => $assignments->filter(fn (Assignment $assignment) => $assignment->status === AssignmentStatus::Declined
                    && $assignment->responded_at !== null)->count(),
                'replaced' => $assignments->where('status', AssignmentStatus::NeedsReplacement)->count(),
                'swapsRequested' => SwapRequest::query()->where('requester_id', $volunteer->id)->count(),
                'swapsTaken' => SwapRequest::query()->where('accepted_by_id', $volunteer->id)->where('status', SwapStatus::Approved)->count(),
                'absences' => $volunteer->absences()->count(),
            ],
            'frequency' => [
                'perMonth' => round($doneInWindow / $months, 1),
                'months' => (int) round($months),
            ],
            'shifts' => $assignments->take(50)->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'date' => $assignment->event->date->toDateString(),
                'shift' => ShiftDescription::of($assignment),
                'status' => match (true) {
                    $assignment->event->status === EventStatus::Cancelled => 'Evento cancelado',
                    $assignment->status === AssignmentStatus::Declined && $assignment->responded_at === null => 'Trocado',
                    $assignment->status === AssignmentStatus::Confirmed && $isPast($assignment) => 'Feito',
                    $assignment->status === AssignmentStatus::Confirmed => 'Agendado',
                    default => $assignment->status->label(),
                },
            ]),
        ]);
    }
}
