<?php

namespace App\Actions\Scheduling;

use App\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable schedule (FR-015), no contacts. Coordinators get first and last names (FR-036);
 * volunteers get colleagues as "Nome A.", as on screen (FR-027).
 */
class RenderSchedulePdf
{
    public function download(Schedule $schedule, bool $fullNames): Response
    {
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

        $pdf = Pdf::loadView('pdf.schedule', ['schedule' => $schedule, 'events' => $events, 'fullNames' => $fullNames])
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
