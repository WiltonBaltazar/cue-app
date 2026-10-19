<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Swaps\DeclineAssignment;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\VolunteerShifts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Volunteer views of published schedules (FR-027): my shifts and the team schedule,
 * where colleagues appear only as "Nome A." with their role.
 */
class ShiftController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('volunteer/shifts/index', [
            'shifts' => $user->volunteer === null ? [] : VolunteerShifts::upcoming($user->volunteer),
        ]);
    }

    /**
     * "Não posso ir" (US4-5).
     */
    public function decline(#[CurrentUser] User $user, Assignment $assignment, DeclineAssignment $decline): RedirectResponse
    {
        abort_unless($user->volunteer !== null && $assignment->volunteer_id === $user->volunteer->id, 403);

        $decline->handle($assignment, $user->volunteer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Turno recusado. Os coordenadores foram avisados e o turno foi oferecido aos colegas disponíveis.']);

        return back();
    }

    /**
     * The whole published schedule, or one event's team with `?evento=` (from a shift card).
     */
    public function team(Request $request, #[CurrentUser] User $user, Schedule $schedule): Response
    {
        abort_unless($schedule->isPublished(), 404);
        Gate::authorize('view', $schedule);

        $myId = $user->volunteer?->id;
        $eventId = $request->integer('evento') ?: null;

        if ($eventId !== null && ! $schedule->events()->whereKey($eventId)->exists()) {
            $eventId = null;
        }

        $events = $schedule->events()
            ->when($eventId !== null, fn ($query) => $query->whereKey($eventId))
            ->with([
                'positions.teamRole',
                'positions.assignments' => fn ($query) => $query->active()->with('volunteer'),
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('schedules/show', [
            'schedule' => [
                'id' => $schedule->id,
                'startsOn' => $schedule->starts_on->toDateString(),
                'endsOn' => $schedule->ends_on->toDateString(),
            ],
            'focusedEventId' => $eventId,
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date->toDateString(),
                'arrivalTime' => LocalTime::timeOf($event->arrival_at),
                'startTime' => LocalTime::timeOf($event->starts_at),
                'cancelled' => $event->isCancelled(),
                'positions' => $event->positions->map(fn (Position $position) => [
                    'role' => $position->teamRole->name,
                    'people' => $position->assignments->map(fn (Assignment $assignment) => [
                        'name' => $assignment->volunteer->shortName(),
                        'isMe' => $assignment->volunteer_id === $myId,
                    ])->values(),
                ])->values(),
            ])->values(),
        ]);
    }
}
