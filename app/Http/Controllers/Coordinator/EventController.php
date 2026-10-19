<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Events\CancelEvent;
use App\Actions\Events\SaveEvent;
use App\Enums\Level;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEventRequest;
use App\Models\Event;
use App\Models\TeamRole;
use App\Support\EventPresenter;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
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

    public function show(Event $event): Response
    {
        Gate::authorize('view', $event);

        $event->load('positions.teamRole');

        return Inertia::render('coord/events/show', [
            'event' => EventPresenter::full($event),
            // People to notify on cancellation; assignments arrive with User Story 3.
            'affectedVolunteers' => 0,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('coord/events/form', [
            ...$this->formOptions(),
            'event' => null,
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
            'defaultDate' => $event->date->toDateString(),
        ]);
    }

    public function update(SaveEventRequest $request, Event $event, SaveEvent $saveEvent): RedirectResponse
    {
        Gate::authorize('update', $event);

        $saveEvent->handle($event, $request->eventData());

        Inertia::flash('toast', ['type' => 'success', 'message' => $event->activity_template_id !== null
            ? 'Ocorrência alterada. As restantes datas mantêm o modelo.'
            : 'Evento actualizado.']);

        return to_route('coord.events.show', $event);
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
