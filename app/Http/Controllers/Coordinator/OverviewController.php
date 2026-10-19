<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\Level;
use App\Enums\Period;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\Event;
use App\Models\TeamRole;
use App\Models\Volunteer;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Availability and coverage pages (US5). The numbers come from /app-api so filters
 * update without a page reload; these only supply the filter options.
 */
class OverviewController extends Controller
{
    public function availability(): Response
    {
        Gate::authorize('viewAny', Volunteer::class);

        return Inertia::render('coord/availability', [
            'roles' => TeamRole::query()->active()->ordered()->get(['name', 'slug']),
            'levels' => collect(Level::cases())->map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()]),
            'weekdays' => Availability::WEEKDAYS,
            'periods' => collect(Period::cases())->mapWithKeys(fn (Period $period) => [$period->value => $period->label()]),
        ]);
    }

    public function coverage(): Response
    {
        Gate::authorize('viewAny', Event::class);

        return Inertia::render('coord/coverage');
    }
}
