<?php
namespace App\Services;

use App\Models\Report;
use App\Models\ValidationError;
use DOMDocument;
use DOMXPath;

/**
 * Service de validation des fichiers FATCA XML selon les règles de la Publication 5124.
 */
class FatcaValidator
{
    private Report $report;
    private DOMDocument $dom;
    private DOMXPath $xpath;
    private array $errors = [];
    private array $isoCountries = ['AF','AL','DZ','AS','AD','AO','AG','AR','AM','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BT','BO','BA','BW','BR','BN','BG','BF','BI','KH','CM','CA','CV','CF','TD','CL','CN','CO','KM','CG','CD','CR','CI','HR','CU','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','ET','FJ','FI','FR','GA','GM','GE','DE','GH','GR','GD','GT','GN','GW','GY','HT','HN','HU','IS','IN','ID','IR','IQ','IE','IL','IT','JM','JP','JO','KZ','KE','KI','KP','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MK','MG','MW','MY','MV','ML','MT','MH','MR','MU','MX','FM','MD','MC','MN','ME','MA','MZ','MM','NA','NR','NP','NL','NZ','NI','NE','NG','NO','OM','PK','PW','PA','PG','PY','PE','PH','PL','PT','QA','RO','RU','RW','KN','LC','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SK','SI','SB','SO','ZA','ES','LK','SD','SR','SZ','SE','CH','SY','TW','TJ','TZ','TH','TL','TG','TO','TT','TN','TR','TM','TV','UG','UA','AE','GB','US','UY','UZ','VU','VE','VN','YE','ZM','ZW'];

    /**
     * Valide un contenu XML FATCA et enregistre les anomalies trouvées.
     */
    public function validate(Report $report, string $xmlContent): array
    {
        $this->report = $report;
        $this->errors = [];
        $this->dom = new DOMDocument();

        // 1. Check encoding
        if (!mb_check_encoding($xmlContent, 'UTF-8')) {
            $this->addError('error','encoding','XML','Encodage non UTF-8 détecté','UTF-8','Autre','Convertir le fichier en UTF-8','Section 2');
        }

        // 2. Check prohibited characters
        $this->validateProhibitedChars($xmlContent);

        // 3. Try loading XML
        libxml_use_internal_errors(true);
        $loaded = $this->dom->loadXML($xmlContent);
        if (!$loaded) {
            foreach (libxml_get_errors() as $e) {
                $this->addError('error','structure','XML','Erreur XML: '.trim($e->message),'XML valide','Ligne '.$e->line,'Corriger la structure XML','Section 2');
            }
            libxml_clear_errors();
            $this->saveErrors();
            return $this->errors;
        }
        libxml_clear_errors();

        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('ftc','urn:oecd:ties:fatca:v2');
        $this->xpath->registerNamespace('sfa','urn:oecd:ties:stffatcatypes:v2');

        // 4. Validate namespaces
        $this->validateNamespaces();
        // 5. Validate MessageSpec
        $this->validateMessageSpec();
        // 6. Validate ReportingFI
        $this->validateReportingFI();
        // 7. Validate Account Reports
        $this->validateAccountReports();
        // 8. Validate DocSpecs
        $this->validateDocSpecs();

        $this->saveErrors();
        return $this->errors;
    }

    /**
     * Vérifie la présence de séquences de caractères interdites (risques d'injection ou formats invalides).
     */
    private function validateProhibitedChars(string $xml): void
    {
        $sqlPatterns = ['--', '/*', '&#'];
        foreach ($sqlPatterns as $p) {
            if (str_contains($xml, $p)) {
                $pos = strpos($xml, $p);
                $this->addError('error', 'characters', 'XML', 'Séquence interdite détectée (risque injection) : "' . $p . '"', 'Aucune séquence interdite', $p, 'Supprimer "' . $p . '" du contenu', 'Section 2.3', true);
            }
        }

        // Check for non-escaped special characters (though DOMDocument usually handles this, we check raw XML)
        if (preg_match('/[<>&](?![a-zA-Z0-9#]+;)/', $xml)) {
             // This is a broad check, better to trust DOMDocument loading errors
        }
    }

    /**
     * Valide l'espace de noms (namespace) et la version du schéma XML.
     */
    private function validateNamespaces(): void
    {
        $root = $this->dom->documentElement;
        if (!$root) return;
        $ns = $root->namespaceURI;
        if ($ns !== 'urn:oecd:ties:fatca:v2') {
            $this->addError('error', 'namespace', 'FATCA_OECD', 'Namespace racine incorrect', 'urn:oecd:ties:fatca:v2', $ns ?: 'absent', 'Utiliser xmlns:ftc="urn:oecd:ties:fatca:v2"', 'Section 2.4');
        }
        $version = $root->getAttribute('version');
        if (empty($version)) {
            $this->addError('warning', 'structure', 'FATCA_OECD', 'Attribut version manquant', '2.0', 'absent', 'Ajouter version="2.0"', 'Section 2.1');
        } elseif ($version !== '2.0') {
            $this->addError('error', 'format', 'FATCA_OECD', 'Version du schéma non supportée', '2.0', $version, 'Utiliser la version 2.0', 'Section 2.1');
        }
    }

    /**
     * Valide les éléments de la section MessageSpec.
     */
    private function validateMessageSpec(): void
    {
        // SendingCompanyIN
        $nodes = $this->xpath->query('//sfa:SendingCompanyIN');
        if ($nodes->length === 0) {
            $this->addError('error', 'required', 'SendingCompanyIN', 'SendingCompanyIN manquant', 'GIIN 19 car.', 'absent', 'Ajouter le GIIN de l\'émetteur', 'Section 3.1');
        } else {
            $val = $nodes->item(0)->textContent;
            $this->validateStringNoPadding($val, 'SendingCompanyIN', 'Section 3.1');
            if (!preg_match('/^[A-Z0-9]{6}\.[A-Z0-9]{5}\.[A-Z]{2}\.[0-9]{3}$/i', trim($val))) {
                $this->addError('error', 'format', 'SendingCompanyIN', 'Format GIIN invalide', 'XX9999.XXXXX.XX.999', $val, 'Le GIIN doit comporter 19 caractères au format standard', 'Section 3.1', true);
            }
        }

        // MessageRefId
        $nodes = $this->xpath->query('//sfa:MessageRefId');
        if ($nodes->length === 0) {
            $this->addError('error', 'required', 'MessageRefId', 'MessageRefId manquant', 'Max 200 car.', 'absent', 'Générer un ID unique', 'Section 3.6');
        } else {
            $val = $nodes->item(0)->textContent;
            $this->validateStringNoPadding($val, 'MessageRefId', 'Section 3.6');
            $this->validateStringSize($val, 'MessageRefId', 1, 200, 'Section 3.6');
            if (preg_match('/[^a-zA-Z0-9+_.\-]/', $val)) {
                $this->addError('warning', 'characters', 'MessageRefId', 'Caractères spéciaux non recommandés', 'Alphanumériques, +, _, -, .', $val, 'Éviter les espaces et caractères spéciaux', 'Section 2.3');
            }
        }
        
        // ReportingPeriod
        $nodes = $this->xpath->query('//sfa:ReportingPeriod');
        if ($nodes->length > 0) {
            $val = trim($nodes->item(0)->textContent);
            if (!preg_match('/^\d{4}-12-31$/', $val)) {
                $this->addError('warning', 'business_rule', 'ReportingPeriod', 'La période devrait se terminer le 31 décembre', 'YYYY-12-31', $val, 'Vérifier la période fiscale', 'Section 3.8');
            }
        }
    }

    /**
     * Valide les informations de l'Institution Financière Déclarante.
     */
    private function validateReportingFI(): void
    {
        $nodes = $this->xpath->query('//ftc:ReportingFI');
        if ($nodes->length === 0) return;

        // Name
        $name = $this->xpath->query('//ftc:ReportingFI/sfa:Name');
        if ($name->length > 0) {
            $val = $name->item(0)->textContent;
            $this->validateStringNoPadding($val, 'ReportingFI/Name', 'Section 4.5.3');
            $this->validateStringSize($val, 'ReportingFI/Name', 1, 200, 'Section 4.5.3');
        }
    }

    /**
     * Valide les rapports de compte individuels (AccountReport).
     */
    private function validateAccountReports(): void
    {
        $reports = $this->xpath->query('//ftc:AccountReport');
        for ($i = 0; $i < $reports->length; $i++) {
            $node = $reports->item($i);
            $idx = $i + 1;

            // AccountNumber
            $acct = $this->xpath->query('ftc:AccountNumber', $node);
            if ($acct->length > 0) {
                $val = $acct->item(0)->textContent;
                if (strlen($val) > 100) {
                    $this->addError('error', 'size', 'AccountNumber', 'Numéro de compte trop long (Max 100)', 'Max 100 car.', strlen($val), 'Vérifier le numéro de compte', 'Section 6.4.2');
                }
                if ($val !== trim($val)) {
                    $this->addError('warning', 'characters', 'AccountNumber', 'Espaces inutiles détectés', 'Pas d\'espaces en début/fin', '"'.$val.'"', 'Supprimer les espaces', 'Section 2.3', true);
                }
            }

            // AccountBalance
            $bal = $this->xpath->query('ftc:AccountBalance', $node);
            if ($bal->length > 0) {
                $amount = $bal->item(0)->textContent;
                if (str_contains($amount, ',')) {
                    $this->addError('error', 'format', 'AccountBalance', 'Virgule utilisée comme séparateur décimal', 'Point (.) obligatoire', $amount, 'Utiliser le point comme séparateur', 'Section 4.1', true);
                }
            }
        }
    }

    /**
     * Vérifie qu'une chaîne ne contient pas d'espaces inutiles en début ou fin.
     */
    private function validateStringNoPadding(string $value, string $element, string $section): void
    {
        if ($value !== trim($value)) {
            $this->addError('warning', 'characters', $element, 'Espaces de remplissage (padding) détectés', 'Pas d\'espaces en début/fin', '"' . $value . '"', 'Supprimer les espaces inutiles', $section, true);
        }
    }

    /**
     * Valide le format des numéros d'identification fiscale (TIN ou GIIN).
     */
    private function validateTIN(string $value, string $element, string $section): void
    {
        $val = trim($value);
        if (empty($val)) return;
        
        // Remove common separators for format check if needed, but FATCA prefers certain formats
        if (preg_match('/\s/', $value)) {
            $this->addError('warning', 'characters', $element, 'Espaces présents dans le TIN', 'Pas d\'espaces', $value, 'Supprimer les espaces dans le TIN', $section, true);
        }

        $giinPattern = '/^[A-Z0-9]{6}\.[A-Z0-9]{5}\.[A-Z]{2}\.[0-9]{3}$/i';
        $tin9 = '/^\d{9}$/';
        $tinDash1 = '/^\d{3}-\d{2}-\d{4}$/';
        $tinDash2 = '/^\d{2}-\d{7}$/';
        
        if (!preg_match($giinPattern, $val) && !preg_match($tin9, $val) && !preg_match($tinDash1, $val) && !preg_match($tinDash2, $val)) {
            $this->addError('error', 'format', $element, 'Format TIN non conforme (doit être 9 chiffres ou GIIN)', 'SSN, ITIN, EIN ou GIIN', $val, 'Corriger le format du TIN', $section, true);
        }
    }

    /**
     * Valide la longueur d'une chaîne de caractères.
     */
    private function validateStringSize(string $value, string $element, int $min, int $max, string $section): void
    {
        $val = trim($value);
        $len = strlen($val);
        if ($len < $min) {
            $this->addError('error', 'size', $element, 'Valeur trop courte (' . $len . ' car.)', "Min $min car.", $val, 'Compléter l\'information', $section, true);
        }
        if ($len > $max) {
            $this->addError('error', 'size', $element, 'Valeur trop longue (' . $len . ' car.)', "Max $max car.", substr($val, 0, 50) . '...', 'Tronquer la valeur', $section, true);
        }
    }

    /**
     * Ajoute une erreur à la liste interne pour traitement ultérieur.
     */
    private function addError(string $severity, string $category, string $element, string $message, string $expected, string $actual, string $suggestion, string $section, bool $correctable = false): void
    {
        $this->errors[] = [
            'severity' => $severity,
            'category' => $category,
            'element' => $element,
            'message' => $message,
            'expected_value' => $expected,
            'actual_value' => (string)$actual,
            'suggestion' => $suggestion,
            'fatca_section' => $section,
            'auto_correctable' => $correctable,
        ];
    }

    /**
     * Enregistre toutes les erreurs détectées en base de données et met à jour le statut du rapport.
     */
    private function saveErrors(): void
    {
        $errorCount = 0;
        $warningCount = 0;
        foreach ($this->errors as $err) {
            ValidationError::create(array_merge($err, ['report_id' => $this->report->id]));
            if ($err['severity'] === 'error') $errorCount++;
            else $warningCount++;
        }
        
        $complianceRate = 100;
        if ($this->report->total_records > 0) {
            $deduction = ($errorCount * 2) + ($warningCount * 0.5);
            $complianceRate = max(0, 100 - ($deduction / max(1, $this->report->total_records / 10)));
        }

        $this->report->update([
            'total_errors' => $errorCount,
            'total_warnings' => $warningCount,
            'status' => $errorCount > 0 ? 'errors_found' : 'valid',
            // Note: total_records is updated in controller, but we could update compliance here if we add the column
        ]);
    }
}
