<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\ActivityTemplate;
use App\Models\Assignment;
use App\Models\ConsentVersion;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SwapRequest;
use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerSkill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log (FR-029, FR-035). Rows only hold IDs; names are looked up now, so an
 * anonymised or deleted volunteer shows as "Voluntário #id" while coordinator names stay.
 */
class AuditController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * Filter key → subject classes and label.
     *
     * @var array<string, array{label: string, types: list<class-string<Model>>}>
     */
    private const FILTERS = [
        'volunteers' => ['label' => 'Voluntários e cadastros', 'types' => [Volunteer::class, VolunteerSkill::class, Absence::class]],
        'schedules' => ['label' => 'Escalas e atribuições', 'types' => [Schedule::class, Assignment::class]],
        'swaps' => ['label' => 'Trocas', 'types' => [SwapRequest::class]],
        'events' => ['label' => 'Eventos e actividades', 'types' => [Event::class, ActivityTemplate::class]],
        'accounts' => ['label' => 'Contas', 'types' => [User::class]],
        'settings' => ['label' => 'Definições, funções e declaração', 'types' => [TeamRole::class, ConsentVersion::class]],
    ];

    /**
     * @var array<class-string<Model>, string>
     */
    private const SUBJECT_LABELS = [
        Volunteer::class => 'Voluntário',
        VolunteerSkill::class => 'Função de voluntário',
        Absence::class => 'Ausência',
        Schedule::class => 'Escala',
        Assignment::class => 'Atribuição',
        SwapRequest::class => 'Troca',
        Event::class => 'Evento',
        ActivityTemplate::class => 'Actividade',
        User::class => 'Conta',
        TeamRole::class => 'Função',
        ConsentVersion::class => 'Declaração',
    ];

    /**
     * @var array<string, string>
     */
    private const ACTIONS = [
        'created' => 'Criou',
        'updated' => 'Alterou',
        'deleted' => 'Apagou',
        'viewed_sensitive_profile' => 'Consultou o perfil completo',
        'issued_reset_link' => 'Emitiu link de redefinição',
        'settings_updated' => 'Alterou as definições',
    ];

    public function __invoke(Request $request): Response
    {
        $filter = $request->string('tipo')->toString();
        $types = self::FILTERS[$filter]['types'] ?? null;

        $page = Activity::query()
            ->where('log_name', 'audit')
            ->when($types !== null, fn ($query) => $filter === 'settings'
                ? $query->where(fn ($query) => $query->whereIn('subject_type', $types)->orWhere('event', 'settings_updated'))
                : $query->whereIn('subject_type', $types))
            ->with('causer')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        /** @var Collection<int, Activity> $activities */
        $activities = collect($page->items());
        $subjects = $this->loadSubjects($activities);

        return Inertia::render('coord/audit', [
            'entries' => $activities->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'at' => $activity->created_at?->toIso8601String(),
                'actor' => $this->actorName($activity),
                'action' => self::ACTIONS[$activity->event ?? $activity->description] ?? $activity->description,
                'subjectType' => self::SUBJECT_LABELS[$activity->subject_type] ?? null,
                'subject' => $activity->subject_type === null ? null : $this->subjectName($activity, $subjects),
                'changes' => $this->changes($activity),
            ])->values(),
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filter' => $types === null ? null : $filter,
            'filters' => collect(self::FILTERS)->map(fn (array $definition, string $key) => ['value' => $key, 'label' => $definition['label']])->values(),
        ]);
    }

    /**
     * Subjects of this page grouped by class and keyed by ID; deleted ones are simply absent.
     *
     * @param  Collection<int, Activity>  $activities
     * @return array<string, Collection<int, Model>>
     */
    private function loadSubjects(Collection $activities): array
    {
        return $activities
            ->filter(fn (Activity $activity) => isset(self::SUBJECT_LABELS[$activity->subject_type]))
            ->groupBy('subject_type')
            ->map(function (Collection $group, string $type) {
                /** @var class-string<Model> $type */
                return $type::query()->whereKey($group->pluck('subject_id')->unique())->get()->keyBy('id');
            })
            ->all();
    }

    private function actorName(Activity $activity): string
    {
        if ($activity->causer_id === null) {
            return 'Sistema';
        }

        $causer = $activity->causer;

        return $causer instanceof User ? $causer->name : 'Conta removida';
    }

    /**
     * @param  array<string, Collection<int, Model>>  $subjects
     */
    private function subjectName(Activity $activity, array $subjects): string
    {
        $id = (int) $activity->subject_id;
        $subject = ($subjects[$activity->subject_type] ?? collect())->get($id);

        return match (true) {
            $activity->subject_type === Volunteer::class => $subject instanceof Volunteer ? $subject->full_name : Volunteer::anonymousName($id),
            $subject instanceof User => $subject->name,
            $subject instanceof Event => "{$subject->name} · {$subject->date->format('d/m/Y')}",
            $subject instanceof TeamRole, $subject instanceof ActivityTemplate => $subject->name,
            $subject instanceof Schedule => "{$subject->starts_on->format('d/m/Y')} – {$subject->ends_on->format('d/m/Y')}",
            $subject instanceof ConsentVersion => "Versão {$subject->version}",
            default => "#{$id}",
        };
    }

    /**
     * Changed fields with old and new values; personal values were never stored ("alterado").
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private function changes(Activity $activity): array
    {
        // Custom entries such as profile views carry no properties at all.
        $properties = $activity->properties ?? collect();
        $new = $properties->get('attributes', []);
        $old = $properties->get('old', []);

        if (! is_array($new) || ! is_array($old)) {
            return [];
        }

        return array_map(fn (int|string $field) => [
            'field' => (string) $field,
            'old' => $this->display($old[$field] ?? null),
            'new' => $this->display($new[$field] ?? null),
        ], array_keys($new + $old));
    }

    private function display(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'sim' : 'não',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
