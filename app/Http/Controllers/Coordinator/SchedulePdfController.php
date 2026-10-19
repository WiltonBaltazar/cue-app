<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable schedule (FR-015): schedule data and short names only (FR-036).
 */
class SchedulePdfController extends Controller
{
    public function __invoke(Schedule $schedule): Response
    {
        Gate::authorize('update', $schedule);

        $events = $schedule->events()
            ->scheduled()
            ->with([
                'positions.teamRole',
                'positions.assignments' => fn ($query) => $query->active()->with('volunteer'),
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return Pdf::loadView('pdf.schedule', ['schedule' => $schedule, 'events' => $events])
            ->setPaper('a4')
            ->download("escala-{$schedule->starts_on->format('Y-m-d')}.pdf");
    }
}
