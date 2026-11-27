<?php

namespace App\Http\Controllers\Volunteer;

use App\Actions\Scheduling\RenderSchedulePdf;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The published team schedule as a PDF, with colleagues as "Nome A." (FR-027).
 */
class SchedulePdfController extends Controller
{
    public function __invoke(Schedule $schedule, RenderSchedulePdf $render): Response
    {
        abort_unless($schedule->isPublished(), 404);
        Gate::authorize('view', $schedule);

        return $render->download($schedule, fullNames: false);
    }
}
