<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Events\CancelEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\SaveEvent;
use App\Actions\Scheduling\CreateEventSchedule;
use App\Enums\Level;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEventRequest;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\TeamRole;
use App\Models\User;
use App\Support\EventPresenter;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendar, special events and per-occurrence edits (US2, FR-008, FR-009).
 */
class EventController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Event::class);

        $month = $this->monthFrom($request->string('mes')->toString());

        $events = Event::query()
            ->betweenDates($month->startOfMonth(), $month->endOfMonth())
            ->with('positions')
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('coord/events/index', [
            'month' => $month->format('Y-m'),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
            'today' => LocalTime::today()->toDateString(),
            'events' => $events->map(fn (Event $event) => EventPresenter::summary($event))->values(),
        ]);
    }

    public function show(Event $event, CancelEvent $cancelEvent): Response
    {
        Gate::authorize('view', $event);

        $event->load('positions.teamRole');

        $schedule = Schedule::forEvent($event);
        $published = $schedule?->isPublished() ?? false;

        return Inertia::render('coord/events/show', [
            'event' => EventPresenter::full($event),
            'affectedVolunteers' => $cancelEvent->affectedCount($event),
            'schedule' => $schedule === null ? null : [
                'id' => $schedule->id,
                'singleEvent' => $schedule->isForSingleEvent(),
                'startsOn' => $schedule->starts_on->toDateString(),
                'endsOn' => $schedule->ends_on->toDateString(),
                'published' => $published,
            ],
            'team' => $published ? $this->publishedTeam($event, $schedule) : [],
        ]);
    }

    /**
     * Opens this event's own schedule, creating it on first use, to pick its volunteers.
     */
    public function schedule(Event $event, #[CurrentUser] User $coordinator, CreateEventSchedule $create): RedirectResponse
    {
        Gate::authorize('create', Schedule::class);

        $schedule = $create->handle($event, $coordinator);

        return to_route('coord.schedules.edit', $schedule);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('coord/events/form', [
            ...$this->formOptions(),
            'event' => null,
            'peopleToNotify' => 0,
            'defaultDate' => $request->string('data')->toString() ?: LocalTime::today()->toDateString(),
        ]);
    }

    public function store(SaveEventRequest $request, SaveEvent $saveEvent): RedirectResponse
    {
        $event = $saveEvent->handle(null, $request->eventData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento criado.']);

        return to_route('coord.events.show', $event);
    }

    public function edit(Event $event): Response|RedirectResponse
    {
        Gate::authorize('update', $event);

        $event->load('positions.teamRole');

        return Inertia::render('coord/events/form', [
            ...$this->formOptions(),
            'event' => EventPresenter::full($event),
            'peopleToNotify' => $event->assignments()->active()
                ->whereHas('schedule', fn ($query) => $query->published())
                ->count(),
            'defaultDate' => $event->date->toDateString(),
        ]);
    }

    public function update(SaveEventRequest $request, Event $event, SaveEvent $saveEvent): RedirectResponse
    {
        Gate::authorize('update', $event);

        $conflicts = fn (): array => $event->assignments()->active()->whereNotNull('conflict_flagged_at')->pluck('id')->all();
        $before = $conflicts();

        $saveEvent->handle($event, $request->eventData());

        $new = count(array_diff($conflicts(), $before));
        $message = $event->activity_template_id !== null
            ? 'Ocorrência alterada. As restantes datas mantêm o modelo.'
            : 'Evento actualizado.';

        Inertia::flash('toast', $new === 0
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'warning', 'message' => $message.' '.($new === 1
                ? '1 pessoa escalada não pode na nova data ou hora: veja a escala.'
                : "{$new} pessoas escaladas não podem na nova data ou hora: veja a escala.")]);

        return to_route('coord.events.show', $event);
    }

    public function destroy(Event $event, DeleteEvent $deleteEvent): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $deleteEvent->handle($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento apagado.']);

        return to_route('coord.events.index');
    }

    public function cancel(Request $request, Event $event, CancelEvent $cancelEvent): RedirectResponse
    {
        Gate::authorize('cancel', $event);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $cancelEvent->handle($event, $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento cancelado.']);

        return to_route('coord.events.show', $event);
    }

    /**
     * The people a published schedule put on this event, grouped by position.
     *
     * @return list<array{position: string, people: list<array{id: int, name: string, status: string, statusLabel: string}>}>
     */
    private function publishedTeam(Event $event, Schedule $schedule): array
    {
        $assignments = $event->assignments()
            ->where('schedule_id', $schedule->id)
            ->with('volunteer:id,full_name')
            ->orderBy('id')
            ->get()
            ->groupBy('position_id');

        return $event->positions
            ->map(fn ($position) => [
                'position' => $position->teamRole->name,
                'people' => $assignments->get($position->id, collect())
                    ->map(fn (Assignment $assignment) => [
                        'id' => $assignment->id,
                        'name' => $assignment->volunteer->full_name,
                        'status' => $assignment->status->value,
                        'statusLabel' => $assignment->status->label(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'teamRoles' => TeamRole::query()->active()->ordered()->get(['id', 'name']),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
        ];
    }

    private function monthFrom(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $value, LocalTime::zone()) ?: LocalTime::today()->startOfMonth();
        }

        return LocalTime::today()->startOfMonth();
    }
}
