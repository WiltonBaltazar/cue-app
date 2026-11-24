<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable schedule (FR-015): schedule data with first and last names, no contacts (FR-036).
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

        // dompdf writes the metrics of the embedded fonts here and fails if it is missing.
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('pdf.schedule', ['schedule' => $schedule, 'events' => $events])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        $this->numberPages($pdf);

        return $pdf->download("escala-{$schedule->starts_on->format('Y-m-d')}.pdf");
    }

    private function numberPages(DomPdfDocument $pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Instrument Sans');

        if ($font === null) {
            return;
        }

        $canvas->page_text(36, $canvas->get_height() - 30, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8.5, [0.33, 0.38, 0.47]);
    }
}
