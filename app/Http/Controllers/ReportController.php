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
use Illuminate\Support\Str;

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
     * Traite l'importation d'un fichier Excel, extrait les données et lance la validation initiale.
     * Le XML n'est PAS généré à cette étape si des erreurs sont détectées.
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
            'user_id' => Auth::id() ?: 1,
            'original_filename' => $file->getClientOriginalName(),
            'excel_path' => $path,
            'reporting_period' => $request->reporting_period,
            'status' => 'analyzing',
        ]);

        // Extraction des données brutes et détection du mappage
        $conversionResult = $this->converter->convert(storage_path('app/public/' . $path), $request->reporting_period);
        
        if (!$conversionResult['success']) {
            $report->update(['status' => 'errors_found']);
            return back()->with('error', 'Erreur d\'analyse du fichier : ' . $conversionResult['error']);
        }

        // Sauvegarde du XML initial comme version de travail (Draft)
        $xmlPath = 'reports/xml/' . str_replace('.xlsx', '', $file->hashName()) . '_draft.xml';
        Storage::disk('public')->put($xmlPath, $conversionResult['xml']);

        // On stocke les données, le mappage et le chemin du XML de travail
        $report->update([
            'xml_path' => $xmlPath,
            'total_records' => $conversionResult['records'],
            'raw_data' => $conversionResult['raw_data'],
            'mapping' => $conversionResult['mapping'] ?? [],
        ]);

        // Validation immédiate des données
        $this->validator->validate($report, $conversionResult['xml']);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'Import Rapport',
            'details' => "Rapport ID #{$report->id} importé et analysé.",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Rapport analysé. Veuillez corriger les erreurs avant de générer le XML final.');
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
     * Applique les corrections automatiques au fichier draft et relance la validation.
     */
    public function applyAutoCorrection(Report $report)
    {
        try {
            $data = $report->raw_data;
            $mapping = $report->mapping ?? [];
            $modified = false;

            // 1. Correction directe des données brutes
            foreach ($data as $index => &$row) {
                // Correction des dates (BirthDate, etc.)
                $dateKeys = ['birth_date', 'reporting_period'];
                foreach ($dateKeys as $dk) {
                    $key = $mapping[$dk] ?? null;
                    if ($key && isset($row[$key])) {
                        $val = trim((string)$row[$key]);
                        // Format YYYYMMDD -> YYYY-MM-DD
                        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $val, $matches)) {
                            $row[$key] = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
                            $modified = true;
                        }
                        // Format YYYY/MM/DD -> YYYY-MM-DD
                        elseif (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $val, $matches)) {
                            $row[$key] = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
                            $modified = true;
                        }
                        // Format DD-MM-YY ou DD/MM/YYYY
                        elseif (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{2,4})$/', $val, $matches)) {
                            $year = strlen($matches[3]) == 2 ? '19' . $matches[3] : $matches[3];
                            // Supposons par défaut que c'est JJ-MM, sauf si le mois > 12
                            $day = $matches[1];
                            $month = $matches[2];
                            if ((int)$month > 12) { // C'était probablement MM-DD-YYYY
                                $day = $matches[2];
                                $month = $matches[1];
                            }
                            $row[$key] = "{$year}-{$month}-{$day}";
                            $modified = true;
                        }
                        // Format YYYY-MM-DD déjà bon, on ne fait rien
                        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                            // C'est bon
                        }
                        // Si c'est complètement invalide (ex: "14"), on vide le champ (optionnel dans P5124) pour ne pas bloquer
                        elseif (!empty($val)) {
                            $row[$key] = '';
                            $modified = true;
                        }
                    }
                }

                // Correction du TIN (enlever espaces, tirets inutiles)
                $tinKey = $mapping['tin'] ?? null;
                if ($tinKey && isset($row[$tinKey])) {
                    $oldTin = (string)$row[$tinKey];
                    $newTin = trim($oldTin);
                    $newTin = preg_replace('/[^A-Z0-9.\-]/i', '', $newTin); // Garde alphanum, points et tirets
                    if ($newTin !== $oldTin) {
                        $row[$tinKey] = $newTin;
                        $modified = true;
                    }
                }

                // Correction des codes pays (2 lettres en majuscules)
                $countryKeys = ['country_code', 'res_country_code'];
                foreach ($countryKeys as $ck) {
                    $key = $mapping[$ck] ?? null;
                    if ($key && isset($row[$key])) {
                        $oldVal = (string)$row[$key];
                        $newVal = strtoupper(substr(trim($oldVal), 0, 2));
                        if ($newVal !== $oldVal) {
                            $row[$key] = $newVal;
                            $modified = true;
                        }
                    }
                }

                // Correction des montants
                $amountFields = ['account_balance', 'payment_dividends', 'payment_interest', 'payment_gross_proceeds', 'payment_other'];
                foreach ($amountFields as $field) {
                    $key = $mapping[$field] ?? null;
                    if ($key && isset($row[$key])) {
                        $oldVal = (string)$row[$key];
                        $newVal = str_replace([' ', ','], ['', '.'], $oldVal);
                        if ($newVal !== $oldVal) {
                            $row[$key] = $newVal;
                            $modified = true;
                        }
                    }
                }

                // Nettoyage global
                foreach ($row as $k => $v) {
                    if ($k !== '_row_index' && is_string($v)) {
                        $newV = str_replace(['--', '/*', '&#'], '', $v);
                        if ($newV !== $v) {
                            $row[$k] = $newV;
                            $modified = true;
                        }
                    }
                }
            }

            // 2. Sauvegarde des données corrigées
            if ($modified) {
                $report->update(['raw_data' => $data]);
            }

            // 3. Régénération du XML draft à partir des données corrigées
            $xmlContent = $this->converter->generateFromRawData($data, $mapping, $report->reporting_period->format('Y-m-d'));
            
            $xmlPath = $report->xml_path;
            if (!$xmlPath) {
                $xmlPath = 'reports/xml/' . Str::slug($report->original_filename) . '_draft_' . time() . '.xml';
            }
            Storage::disk('public')->put($xmlPath, $xmlContent);
            
            // 4. Mise à jour du rapport et re-validation
            $report->update(['xml_path' => $xmlPath]);
            $report->validationErrors()->delete();
            $this->validator->validate($report, $xmlContent);

            // 5. SI PLUS D'ERREURS : Génération automatique du XML Final (pour répondre à l'attente de l'utilisateur)
            $report->refresh();
            if ($report->total_errors === 0) {
                return $this->generateXml($report);
            }

            AuditLog::create([
                'user_id' => Auth::id() ?: 1,
                'action' => 'Auto-Fix',
                'details' => "Corrections appliquées aux données et au XML du rapport #{$report->id}.",
                'ip_address' => request()->ip(),
            ]);

            return back()->with('success', 'Corrections automatiques appliquées. Les données du tableur ont été mises à jour.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'auto-correction : ' . $e->getMessage());
        }
    }

    /**
     * Génère (si nécessaire) et télécharge le fichier XML avec corrections automatiques.
     */
    public function downloadCorrectedXml(Report $report)
    {
        // ... (cette méthode pourra être supprimée plus tard si applyAutoCorrection suffit)
        if (!$report->xml_path || !Storage::disk('public')->exists($report->xml_path)) {
            return back()->with('error', 'Le fichier XML source est introuvable.');
        }
        
        $xmlContent = Storage::disk('public')->get($report->xml_path);
        $correctedXml = $this->corrector->correct($report, $xmlContent);
        return response()->streamDownload(function () use ($correctedXml) {
            echo $correctedXml;
        }, 'corrected_fatca.xml');
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
     * Affiche l'interface de modification manuelle des données du rapport.
     */
    public function editData(Report $report)
    {
        return view('reports.edit_data', compact('report'));
    }

    /**
     * Met à jour les données brutes du rapport et relance la validation.
     */
    public function updateData(Request $request, Report $report)
    {
        $newData = $request->input('data');
        
        // Nettoyage des index de lignes pour rester cohérent
        foreach ($newData as $index => &$row) {
            $row['_row_index'] = $index + 1;
        }
        
        // Mise à jour des données brutes
        $report->update(['raw_data' => $newData]);

        // Suppression des anciennes erreurs de validation
        $report->validationErrors()->delete();

        // Régénération du XML temporaire pour relancer la validation et mettre à jour le draft
        $xmlContent = $this->converter->generateFromRawData($newData, $report->mapping ?? [], $report->reporting_period->format('Y-m-d'));
        
        // Mise à jour du fichier draft sur le disque
        if ($report->xml_path) {
            Storage::disk('public')->put($report->xml_path, $xmlContent);
        }
        
        // Relance de la validation
        $this->validator->validate($report, $xmlContent);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'Modification Manuelle',
            'details' => "Données du rapport #{$report->id} modifiées manuellement.",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Données mises à jour. Analyse de conformité relancée.');
    }

    /**
     * Génère le fichier XML final si le rapport est conforme.
     */
    public function generateXml(Report $report)
    {
        // On permet la génération même s'il y a des warnings, mais pas d'erreurs critiques
        if ($report->total_errors > 0) {
            return back()->with('error', 'Impossible de générer le XML : il reste ' . $report->total_errors . ' erreurs critiques de conformité.');
        }

        // Génération du XML final à partir des données validées
        $xmlContent = $this->converter->generateFromRawData($report->raw_data, $report->mapping ?? [], $report->reporting_period->format('Y-m-d'));
        
        $xmlPath = 'reports/xml/' . Str::slug($report->original_filename) . '_' . time() . '.xml';
        Storage::disk('public')->put($xmlPath, $xmlContent);
        
        $report->update([
            'xml_path' => $xmlPath, // On remplace le draft par la version finale
            'status' => 'valid',
        ]);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'Génération XML',
            'details' => "Fichier XML final généré pour le rapport #{$report->id}.",
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('reports.show', $report->id)->with('success', 'Fichier XML FATCA v2.0 généré avec succès. Vous pouvez maintenant le télécharger ou le chiffrer.');
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
