<?php

namespace App\Http\Controllers\AppApi;

use App\Domain\Overview\AvailabilityMatrix;
use App\Domain\Overview\CoverageReport;
use App\Enums\Level;
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
 * contracts/app-api.md). Counts only: no names or contacts.
 */
class OverviewController extends Controller
{
    /** Longest coverage range one request may ask for. */
    private const MAX_COVERAGE_DAYS = 93;

    public function availability(Request $request, AvailabilityMatrix $matrix): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $validated = $request->validate([
            'role' => ['nullable', 'string', Rule::exists('team_roles', 'slug')],
            'minLevel' => ['nullable', Rule::enum(Level::class)],
        ]);

        $role = isset($validated['role']) ? TeamRole::query()->where('slug', $validated['role'])->first() : null;
        $minLevel = isset($validated['minLevel']) ? Level::from($validated['minLevel']) : null;

        return response()->json($matrix->build($role, $minLevel));
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
}
