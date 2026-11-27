<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Scheduling\RenderSchedulePdf;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable schedule for coordinators, drafts included, with first and last names (FR-036).
 */
class SchedulePdfController extends Controller
{
    public function __invoke(Schedule $schedule, RenderSchedulePdf $render): Response
    {
        Gate::authorize('update', $schedule);

        return $render->download($schedule, fullNames: true);
    }
}
