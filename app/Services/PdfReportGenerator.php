<?php

namespace App\Services;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Service de génération de rapports de conformité au format PDF.
 */
class PdfReportGenerator
{
    /**
     * Génère un rapport PDF professionnel résumant les anomalies FATCA détectées.
     */
    public function generate(Report $report): string
    {
        $data = [
            'report' => $report,
            'errors' => $report->validationErrors()->orderBy('severity')->get(),
            'logo_path' => public_path('images/logo.jpg'),
            'date' => now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('reports.pdf', $data);
        
        $filename = 'FATCA_Report_' . $report->id . '_' . now()->format('YmdHis') . '.pdf';
        $path = 'reports/pdfs/' . $filename;
        
        // Ensure directory exists
        if (!file_exists(storage_path('app/public/reports/pdfs'))) {
            mkdir(storage_path('app/public/reports/pdfs'), 0755, true);
        }
        
        $pdf->save(storage_path('app/public/' . $path));
        
        return $path;
    }
}
