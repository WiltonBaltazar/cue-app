<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Events\GenerateOccurrences;
use App\Enums\Level;
use App\Enums\Period;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveActivityTemplateRequest;
use App\Models\ActivityTemplate;
use App\Models\Availability;
use App\Models\TeamRole;
use App\Models\TemplatePosition;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recurring activities (US2, FR-008). Saving regenerates the template's occurrences, so
 * changes reach future non-customised dates at once.
 */
class ActivityTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ActivityTemplate::class);

        $templates = ActivityTemplate::query()->with('positions')->orderBy('weekday')->orderBy('start_time')->get();

        return Inertia::render('coord/templates/index', [
            'templates' => $templates->map(fn (ActivityTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'recurrenceLabel' => $template->recurrenceLabel(),
                'startTime' => substr($template->start_time, 0, 5),
                'location' => $template->location,
                'isActive' => $template->is_active,
                'requiredPeople' => (int) $template->positions->sum('quantity'),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ActivityTemplate::class);

        return Inertia::render('coord/templates/form', [...$this->formOptions(), 'template' => null]);
    }

    public function store(SaveActivityTemplateRequest $request, GenerateOccurrences $generateOccurrences): RedirectResponse
    {
        $template = $this->save(new ActivityTemplate, $request->validated());
        $generateOccurrences->handle($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Actividade criada. As ocorrências já estão no calendário.']);

        return to_route('coord.templates.index');
    }

    public function edit(ActivityTemplate $template): Response
    {
        Gate::authorize('update', $template);

        $template->load('positions');

        return Inertia::render('coord/templates/form', [
            ...$this->formOptions(),
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'weekday' => $template->weekday,
                'intervalWeeks' => $template->interval_weeks,
                'monthWeek' => $template->month_week,
                'startsOn' => $template->starts_on->toDateString(),
                'arrivalTime' => substr($template->arrival_time, 0, 5),
                'startTime' => substr($template->start_time, 0, 5),
                'endTime' => substr($template->end_time, 0, 5),
                'teardownMinutes' => $template->teardown_minutes,
                'location' => $template->location,
                'isActive' => $template->is_active,
                'positions' => $template->positions->map(fn (TemplatePosition $position) => [
                    'teamRoleId' => $position->team_role_id,
                    'quantity' => $position->quantity,
                    'minLevel' => $position->min_level?->value,
                ])->values(),
            ],
        ]);
    }

    public function update(SaveActivityTemplateRequest $request, ActivityTemplate $template, GenerateOccurrences $generateOccurrences): RedirectResponse
    {
        Gate::authorize('update', $template);

        $this->save($template, $request->validated());
        $generateOccurrences->handle($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Actividade guardada. As datas futuras não personalizadas foram actualizadas.']);

        return to_route('coord.templates.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(ActivityTemplate $template, array $data): ActivityTemplate
    {
        return DB::transaction(function () use ($template, $data): ActivityTemplate {
            $template->fill([
                'name' => $data['name'],
                'weekday' => $data['weekday'],
                'interval_weeks' => $data['interval_weeks'],
                'month_week' => $data['month_week'] ?? null,
                'starts_on' => $data['starts_on'],
                'period' => Period::fromTime(CarbonImmutable::parse("2000-01-01 {$data['start_time']}")),
                'arrival_time' => $data['arrival_time'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'teardown_minutes' => $data['teardown_minutes'],
                'location' => $data['location'],
                'is_active' => $data['is_active'],
            ])->save();

            $template->positions()->delete();

            /** @var list<array{team_role_id: int, quantity: int, min_level?: string|null}> $positions */
            $positions = $data['positions'];
            foreach ($positions as $position) {
                $template->positions()->create([
                    'team_role_id' => $position['team_role_id'],
                    'quantity' => $position['quantity'],
                    'min_level' => $position['min_level'] ?? null,
                ]);
            }

            return $template;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'teamRoles' => TeamRole::query()->active()->ordered()->get(['id', 'name']),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
            'weekdays' => collect(Availability::WEEKDAYS)->map(fn (string $label, int $day) => ['value' => $day, 'label' => $label])->values(),
            'today' => LocalTime::today()->toDateString(),
        ];
    }
}
