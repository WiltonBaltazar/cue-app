<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Scheduling\DeleteSchedule;
use App\Actions\Scheduling\GenerateSchedule;
use App\Actions\Scheduling\PublishSchedule;
use App\Domain\Scheduling\Violation;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schedules: create a period, generate, edit, publish, delete (US3).
 */
class ScheduleController extends Controller
{
    public function index(DeleteSchedule $delete): Response
    {
        Gate::authorize('viewAny', Schedule::class);

        $nextMonth = LocalTime::today()->addMonthNoOverflow()->startOfMonth();

        return Inertia::render('coord/schedules/index', [
            'schedules' => Schedule::query()->orderByDesc('starts_on')->withCount('assignments')->get()
                ->map(fn (Schedule $schedule) => [
                    'id' => $schedule->id,
                    'startsOn' => $schedule->starts_on->toDateString(),
                    'endsOn' => $schedule->ends_on->toDateString(),
                    'status' => $schedule->status->value,
                    'statusLabel' => $schedule->status->label(),
                    'assignments' => $schedule->assignments_count,
                    'deleteImpact' => $delete->impact($schedule),
                ])->values(),
            'suggestedStart' => $nextMonth->toDateString(),
            'suggestedEnd' => $nextMonth->endOfMonth()->toDateString(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $coordinator, GenerateSchedule $generate): RedirectResponse
    {
        Gate::authorize('create', Schedule::class);

        $validated = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.$request->date('starts_on')?->addDays(62)->toDateString()],
            'generate' => ['sometimes', 'boolean'],
        ], [
            'ends_on.before_or_equal' => 'Uma escala cobre no máximo dois meses.',
        ], [
            'starts_on' => 'início',
            'ends_on' => 'fim',
        ]);

        $overlaps = Schedule::query()
            ->where('starts_on', '<=', $validated['ends_on'])
            ->where('ends_on', '>=', $validated['starts_on'])
            ->exists();

        if ($overlaps) {
            return back()->withErrors(['starts_on' => 'Já existe uma escala que cobre parte destas datas.'])->withInput();
        }

        $schedule = Schedule::query()->create([
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'created_by' => $coordinator->id,
        ]);

        // An empty schedule is filled by hand; "Gerar de novo" can still propose later.
        $message = $request->boolean('generate', true)
            ? $this->generationMessage($generate->handle($schedule))
            : 'Escala criada vazia. Escolha as pessoas em cada posição.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('coord.schedules.edit', $schedule);
    }

    public function edit(Schedule $schedule): Response
    {
        Gate::authorize('update', $schedule);

        $events = $schedule->events()
            ->with([
                'positions.teamRole',
                'positions.assignments' => fn ($query) => $query->active()->with(['volunteer', 'updater']),
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        $report = collect($schedule->generation_report ?? []);
        $names = Volunteer::query()
            ->whereIn('id', $report->pluck('suggestions')->flatten(1)->pluck('volunteerId')->unique())
            ->pluck('full_name', 'id');

        return Inertia::render('coord/schedules/edit', [
            'schedule' => [
                'id' => $schedule->id,
                'startsOn' => $schedule->starts_on->toDateString(),
                'endsOn' => $schedule->ends_on->toDateString(),
                'status' => $schedule->status->value,
                'statusLabel' => $schedule->status->label(),
                'publishedAt' => $schedule->published_at?->toIso8601String(),
            ],
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date->toDateString(),
                'startTime' => LocalTime::timeOf($event->starts_at),
                'arrivalTime' => LocalTime::timeOf($event->arrival_at),
                'cancelled' => $event->isCancelled(),
                'positions' => $event->positions->map(fn (Position $position) => [
                    'id' => $position->id,
                    'role' => $position->teamRole->name,
                    'quantity' => $position->quantity,
                    'minLevel' => $position->min_level?->label(),
                    'assignments' => $position->assignments->map(fn (Assignment $assignment) => [
                        'id' => $assignment->id,
                        'volunteerId' => $assignment->volunteer_id,
                        'name' => $assignment->volunteer->full_name,
                        'lockVersion' => $assignment->lock_version,
                        'source' => $assignment->source,
                        'locked' => $assignment->is_locked,
                        'overrideNote' => $assignment->override_note,
                        'conflict' => $assignment->conflict_flagged_at !== null,
                    ])->values(),
                ])->values(),
            ])->values(),
            'gaps' => $report->map(fn (array $gap) => [
                ...$gap,
                'suggestions' => collect($gap['suggestions'])->map(fn (array $suggestion) => [
                    'name' => $names[$suggestion['volunteerId']] ?? '',
                    'reasons' => array_map(fn (string $value) => Violation::from($value)->message(), $suggestion['violations']),
                ])->values(),
            ])->values(),
            'volunteersToNotify' => $schedule->assignments()->active()->distinct()->count('volunteer_id'),
        ]);
    }

    public function generate(Schedule $schedule, GenerateSchedule $generate): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $result = $generate->handle($schedule);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->generationMessage($result)]);

        return to_route('coord.schedules.edit', $schedule);
    }

    public function publish(Schedule $schedule, #[CurrentUser] User $coordinator, PublishSchedule $publish): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $notified = $publish->handle($schedule, $coordinator);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Escala publicada. {$notified} voluntários notificados."]);

        return to_route('coord.schedules.edit', $schedule);
    }

    public function destroy(Schedule $schedule, DeleteSchedule $delete): RedirectResponse
    {
        Gate::authorize('delete', $schedule);

        $notified = $delete->handle($schedule);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->deletedMessage(1, $notified)]);

        return to_route('coord.schedules.index');
    }

    public function destroyMany(Request $request, DeleteSchedule $delete): RedirectResponse
    {
        Gate::authorize('delete', Schedule::class);

        $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:schedules,id'],
        ], [
            'ids.required' => 'Seleccione pelo menos uma escala.',
        ]);

        $schedules = Schedule::query()->whereKey($request->array('ids'))->get();
        $notified = $schedules->sum(fn (Schedule $schedule) => $delete->handle($schedule));

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->deletedMessage($schedules->count(), $notified)]);

        return to_route('coord.schedules.index');
    }

    private function deletedMessage(int $deleted, int $notified): string
    {
        $text = $deleted === 1 ? 'Escala apagada.' : "{$deleted} escalas apagadas.";

        return $notified > 0 ? "{$text} {$notified} voluntários foram avisados." : $text;
    }

    /**
     * @param  array{created: int, missing: int}  $result
     */
    private function generationMessage(array $result): string
    {
        return $result['missing'] === 0
            ? "Proposta gerada: {$result['created']} atribuições, sem lacunas."
            : "Proposta gerada: {$result['created']} atribuições, {$result['missing']} lugares por preencher.";
    }
}
