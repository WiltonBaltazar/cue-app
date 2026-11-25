<?php

namespace App\Http\Controllers\AppApi;

use App\Domain\Overview\AvailabilityMatrix;
use App\Domain\Overview\CoverageReport;
use App\Domain\Overview\VolunteerAvailability;
use App\Enums\Level;
use App\Enums\Period;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TeamRole;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Availability matrix and event coverage for the coordinator overview (US5,
 * contracts/app-api.md). Coordinator-only; names appear where a person has to be chosen,
 * contacts and reasons of absences never.
 */
class OverviewController extends Controller
{
    /** Longest coverage range one request may ask for. */
    private const MAX_COVERAGE_DAYS = 93;

    public function availability(Request $request, AvailabilityMatrix $matrix): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        return response()->json($matrix->build(...$this->roleFilter($request)));
    }

    public function people(Request $request, AvailabilityMatrix $matrix): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'period' => ['required', Rule::enum(Period::class)],
        ]);

        return response()->json(['data' => $matrix->people(
            (int) $validated['weekday'],
            Period::from($validated['period']),
            ...$this->roleFilter($request),
        )]);
    }

    public function onDate(Request $request, VolunteerAvailability $availability): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json($availability->on(CarbonImmutable::parse($validated['date'])));
    }

    public function notServing(Request $request, VolunteerAvailability $availability): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return response()->json($availability->notServing(CarbonImmutable::parse($validated['month'].'-01')));
    }

    public function coverage(Request $request, CoverageReport $report): JsonResponse
    {
        Gate::authorize('viewAny', Event::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : LocalTime::today();
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : $from->addDays(30);

        if ($from->diffInDays($to) > self::MAX_COVERAGE_DAYS) {
            return response()->json([
                'message' => 'Escolha um período de até 3 meses.',
                'errors' => ['to' => ['Escolha um período de até 3 meses.']],
            ], 422);
        }

        return response()->json(['data' => $report->between($from, $to)]);
    }

    /**
     * @return array{TeamRole|null, Level|null}
     */
    private function roleFilter(Request $request): array
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', Rule::exists('team_roles', 'slug')],
            'minLevel' => ['nullable', Rule::enum(Level::class)],
        ]);

        return [
            isset($validated['role']) ? TeamRole::query()->where('slug', $validated['role'])->first() : null,
            isset($validated['minLevel']) ? Level::from($validated['minLevel']) : null,
        ];
    }
}
