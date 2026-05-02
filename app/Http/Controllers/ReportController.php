<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\AuditLog;
use App\Services\ExcelToXmlConverter;
use App\Services\FatcaValidator;
use App\Services\XmlCorrectionService;
use App\Services\PdfReportGenerator;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur gérant les rapports FATCA.
 * Il orchestre la conversion, la validation, la correction et le chiffrement des données.
 */
class ReportController extends Controller
{
    protected $converter;
    protected $validator;
    protected $corrector;
    protected $pdfGenerator;
    protected $encryption;

    /**
     * Initialise le contrôleur avec les services nécessaires.
     */
    public function __construct(
        ExcelToXmlConverter $converter,
        FatcaValidator $validator,
        XmlCorrectionService $corrector,
        PdfReportGenerator $pdfGenerator,
        EncryptionService $encryption
    ) {
        $this->converter = $converter;
        $this->validator = $validator;
        $this->corrector = $corrector;
        $this->pdfGenerator = $pdfGenerator;
        $this->encryption = $encryption;
    }

    /**
     * Affiche la liste paginée de tous les rapports.
     */
    public function index()
    {
        $reports = Report::with('user')->latest()->paginate(10);
        return view('reports.index', compact('reports'));
    }

    /**
     * Affiche le formulaire de création d'un nouveau rapport (Import Excel).
     */
    public function create()
    {
        return view('reports.create');
    }

    /**
     * Traite l'importation d'un fichier Excel et lance le workflow de conversion/validation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
            'reporting_period' => 'required|date',
        ]);

        $file = $request->file('excel_file');
        $path = $file->store('reports/excel', 'public');

        $report = Report::create([
            'user_id' => Auth::id() ?: 1, // Fallback to 1 for demo if no auth
            'original_filename' => $file->getClientOriginalName(),
            'excel_path' => $path,
            'reporting_period' => $request->reporting_period,
            'status' => 'analyzing',
        ]);

        // 1. Convert Excel to XML
        $conversionResult = $this->converter->convert(storage_path('app/public/' . $path), $request->reporting_period);
        
        if (!$conversionResult['success']) {
            $report->update(['status' => 'errors_found']);
            return back()->with('error', 'Erreur de conversion : ' . $conversionResult['error']);
        }

        $xmlPath = 'reports/xml/' . str_replace('.xlsx', '', $file->hashName()) . '.xml';
        Storage::disk('public')->put($xmlPath, $conversionResult['xml']);
        
        $report->update([
            'xml_path' => $xmlPath,
            'total_records' => $conversionResult['records'],
            'raw_data' => $conversionResult['raw_data'],
            'mapping' => $conversionResult['mapping'] ?? [],
        ]);

        // 2. Validate FATCA rules
        $this->validator->validate($report, $conversionResult['xml']);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'Import Rapport',
            'details' => "Rapport ID #{$report->id} importé et analysé.",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Rapport analysé avec succès.');
    }

    /**
     * Affiche les détails d'analyse d'un rapport spécifique.
     */
    public function show(Report $report)
    {
        $report->load('validationErrors');
        return view('reports.show', compact('report'));
    }

    /**
     * Télécharge le fichier XML brut généré à partir de l'Excel.
     */
    public function downloadXml(Report $report)
    {
        if (!$report->xml_path || !Storage::disk('public')->exists($report->xml_path)) {
            return back()->with('error', 'Le fichier XML n\'est pas encore généré ou est introuvable.');
        }
        return Storage::disk('public')->download($report->xml_path);
    }

    /**
     * Génère (si nécessaire) et télécharge le fichier XML avec corrections automatiques.
     */
    public function downloadCorrectedXml(Report $report)
    {
        if (!$report->xml_path || !Storage::disk('public')->exists($report->xml_path)) {
            return back()->with('error', 'Le fichier XML source est introuvable.');
        }

        try {
            if (!$report->xml_corrected_path || !Storage::disk('public')->exists($report->xml_corrected_path)) {
                $xmlContent = Storage::disk('public')->get($report->xml_path);
                $correctedXml = $this->corrector->correct($report, $xmlContent);
                
                $path = str_replace('.xml', '_corrected.xml', $report->xml_path);
                Storage::disk('public')->put($path, $correctedXml);
                
                $report->update([
                    'xml_corrected_path' => $path,
                    'status' => 'corrected',
                ]);

                AuditLog::create([
                    'user_id' => Auth::id() ?: 1,
                    'action' => 'Correction Auto',
                    'details' => "Fichier XML corrigé généré pour le rapport #{$report->id}.",
                    'ip_address' => request()->ip(),
                ]);
            }

            return Storage::disk('public')->download($report->xml_corrected_path);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération du fichier corrigé : ' . $e->getMessage());
        }
    }

    /**
     * Génère et télécharge le rapport de conformité au format PDF.
     */
    public function downloadPdf(Report $report)
    {
        try {
            if (!$report->pdf_report_path || !Storage::disk('public')->exists($report->pdf_report_path)) {
                $path = $this->pdfGenerator->generate($report);
                $report->update(['pdf_report_path' => $path]);
            }

            return Storage::disk('public')->download($report->pdf_report_path);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération du rapport PDF : ' . $e->getMessage());
        }
    }

    /**
     * Chiffre le fichier XML corrigé (ou brut) en utilisant la clé publique RSA.
     */
    public function encrypt(Report $report)
    {
        if (!$this->encryption->keysExist()) {
            return back()->with('error', 'Veuillez générer les clés RSA dans les paramètres.');
        }

        $sourcePath = $report->xml_corrected_path ?: $report->xml_path;

        if (!$sourcePath || !Storage::disk('public')->exists($sourcePath)) {
            return back()->with('error', 'Aucun fichier XML disponible pour le chiffrement. Veuillez d\'abord convertir et valider le rapport.');
        }

        $destPath = str_replace('.xml', '.enc', $sourcePath);
        
        if ($this->encryption->encryptFile($sourcePath, $destPath)) {
            $report->update(['encrypted_xml_path' => $destPath]);
            return back()->with('success', 'Fichier XML chiffré avec succès.');
        }

        return back()->with('error', 'Erreur lors du chiffrement.');
    }

    /**
     * Supprime un rapport et tous les fichiers associés sur le disque.
     */
    public function destroy(Report $report)
    {
        Storage::disk('public')->delete([
            $report->excel_path,
            $report->xml_path,
            $report->xml_corrected_path,
            $report->encrypted_xml_path,
            $report->pdf_report_path
        ]);
        
        $report->delete();
        return redirect()->route('reports.index')->with('success', 'Rapport supprimé.');
    }
}
