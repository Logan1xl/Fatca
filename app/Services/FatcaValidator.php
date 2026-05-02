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
    private ?int $currentRowIndex = null;
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
        
        // Check if mapping was successful (by checking for any AccountReport data)
        if ($this->xpath->query('//ftc:AccountReport')->length === 0) {
            $this->addError('warning', 'structure', 'XML', 'Aucune donnée de compte détectée. Vérifiez les en-têtes de votre fichier Excel.', 'Éléments AccountReport', '0 éléments', 'Assurez-vous que les colonnes sont correctement nommées', 'Global');
        }

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
            
            // Track Excel row reference
            $this->currentRowIndex = $node->hasAttribute('rowIndex') ? (int)$node->getAttribute('rowIndex') : null;

            // 1. AccountNumber
            $this->validateElement($node, 'ftc:AccountNumber', 'AccountNumber', 1, 100, 'Section 6.4.2', true);

            // 2. AccountBalance
            $this->validateAmount($node, 'ftc:AccountBalance', 'AccountBalance', 'Section 4.1');

            // 3. AccountHolder (Individual or Organisation)
            $this->validateAccountHolder($node);

            // 4. Payments
            $payments = $this->xpath->query('ftc:Payment', $node);
            foreach ($payments as $payment) {
                $this->validateAmount($payment, 'ftc:PaymentAmnt', 'PaymentAmnt', 'Section 4.1');
            }
        }
    }

    /**
     * Valide les détails du titulaire du compte.
     */
    private function validateAccountHolder(\DOMNode $accountReport): void
    {
        $individual = $this->xpath->query('ftc:AccountHolder/ftc:Individual', $accountReport);
        $organisation = $this->xpath->query('ftc:AccountHolder/ftc:Organisation', $accountReport);

        if ($individual->length > 0) {
            $ind = $individual->item(0);
            $this->validateElement($ind, 'sfa:ResCountryCode', 'ResCountryCode', 2, 2, 'Section 4.5.1', true, 'data');
            $this->validateTINElement($ind, 'sfa:TIN', 'TIN', 'Section 4.5.2');
            $this->validateElement($ind, 'sfa:Name/sfa:FirstName', 'FirstName', 1, 100, 'Section 4.5.3', true, 'data');
            $this->validateElement($ind, 'sfa:Name/sfa:LastName', 'LastName', 1, 100, 'Section 4.5.3', true, 'data');
            $this->validateBirthDate($ind);
            $this->validateAddress($ind);
            
            // US Indicia Check
            $this->checkUsIndicia($ind);
        } elseif ($organisation->length > 0) {
            $org = $organisation->item(0);
            $this->validateElement($org, 'sfa:ResCountryCode', 'ResCountryCode', 2, 2, 'Section 4.5.1', true, 'data');
            $this->validateTINElement($org, 'sfa:TIN', 'TIN', 'Section 4.5.2');
            $this->validateElement($org, 'sfa:Name', 'OrganisationName', 1, 200, 'Section 4.5.3', true, 'data');
            $this->validateAddress($org);
            
            // Organisation specific checks
            $holderType = $this->xpath->query('../ftc:AcctHolderType', $org);
            if ($holderType->length === 0) {
                $this->addError('error', 'fatca_status', 'AcctHolderType', 'Type de titulaire d\'entité manquant', 'FATCA101-FATCA105', 'absent', 'Préciser le statut FATCA de l\'entité', 'Section 4.6');
            }
        } else {
            $this->addError('error', 'data', 'AccountHolder', 'Titulaire de compte non identifié', 'Individual ou Organisation', 'absent', 'Vérifier les données du client', 'Section 4.5');
        }
    }

    /**
     * Valide l'adresse d'un titulaire.
     */
    private function validateAddress(\DOMNode $parent): void
    {
        $address = $this->xpath->query('sfa:Address', $parent);
        if ($address->length === 0) {
            $this->addError('error', 'data', 'Address', 'Adresse manquante', 'Adresse structurée ou libre', 'absent', 'Renseigner l\'adresse du client', 'Section 4.5.4');
            return;
        }

        $addr = $address->item(0);
        $this->validateElement($addr, 'sfa:CountryCode', 'Address/CountryCode', 2, 2, 'Section 4.5.4', true, 'data');
        
        $free = $this->xpath->query('sfa:AddressFree', $addr);
        $fix = $this->xpath->query('sfa:AddressFix', $addr);
        
        if ($free->length === 0 && $fix->length === 0) {
            $this->addError('error', 'data', 'Address', 'Détails d\'adresse manquants', 'AddressFree ou AddressFix', 'vide', 'Préciser la rue et la ville', 'Section 4.5.4');
        }
    }

    /**
     * Valide la date de naissance.
     */
    private function validateBirthDate(\DOMNode $individual): void
    {
        $dob = $this->xpath->query('sfa:BirthInfo/sfa:BirthDate', $individual);
        if ($dob->length > 0) {
            $val = trim($dob->item(0)->textContent);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $this->addError('error', 'format', 'BirthDate', 'Format de date de naissance invalide', 'AAAA-MM-JJ', $val, 'Utiliser le format ISO (Ex: 1980-01-31)', 'Section 4.5.5', true);
            } else {
                $year = (int)substr($val, 0, 4);
                if ($year < 1900 || $year > date('Y')) {
                    $this->addError('warning', 'coherence', 'BirthDate', 'Date de naissance incohérente', 'Année entre 1900 et '.date('Y'), $val, 'Vérifier la date de naissance', 'Section 4.5.5');
                }
            }
        }
    }

    /**
     * Vérifie les indices d'américanité (US Indicia).
     */
    private function checkUsIndicia(\DOMNode $individual): void
    {
        $countryNodes = $this->xpath->query('sfa:ResCountryCode', $individual);
        $country = $countryNodes->length > 0 ? trim($countryNodes->item(0)->textContent) : '';
        
        $tinNodes = $this->xpath->query('sfa:TIN', $individual);
        $tin = $tinNodes->length > 0 ? trim($tinNodes->item(0)->textContent) : '';

        // Check if US TIN is present for non-US resident
        if ($country !== 'US' && !empty($tin)) {
            if (preg_match('/^\d{3}-\d{2}-\d{4}$/', $tin) || preg_match('/^\d{9}$/', $tin)) {
                $this->addError('warning', 'coherence', 'USIndicia', 'Client non-US avec un TIN américain', 'Cohérence TIN/Pays', $tin, 'Vérifier le statut FATCA du client', 'Section 4.5.2');
            }
        }
    }

    /**
     * Valide un élément générique (existence, longueur, padding).
     */
    private function validateElement(\DOMNode $parent, string $query, string $label, int $min, int $max, string $section, bool $required = false, string $category = 'format'): void
    {
        $nodes = $this->xpath->query($query, $parent);
        if ($nodes->length === 0) {
            if ($required) {
                $this->addError('error', 'required', $label, "Champ obligatoire '$label' manquant", "Min $min car.", 'absent', "Renseigner la valeur pour $label", $section);
            }
            return;
        }

        $val = $nodes->item(0)->textContent;
        if (empty(trim($val)) && $required) {
            $this->addError('error', 'required', $label, "Champ '$label' vide", "Min $min car.", 'vide', "Renseigner la valeur pour $label", $section);
            return;
        }

        $this->validateStringNoPadding($val, $label, $section);
        $this->validateStringSize($val, $label, $min, $max, $section, $category);
        
        // Country code specific check
        if (str_contains(strtolower($label), 'countrycode')) {
            $code = strtoupper(trim($val));
            if (!in_array($code, $this->isoCountries)) {
                $this->addError('error', 'regulatory', $label, "Code pays ISO '$code' invalide", 'ISO 3166-1 (2 car.)', $code, 'Utiliser un code pays standard (Ex: CM, US, FR)', $section);
            }
        }
    }

    /**
     * Valide un montant financier.
     */
    private function validateAmount(\DOMNode $parent, string $query, string $label, string $section): void
    {
        $nodes = $this->xpath->query($query, $parent);
        if ($nodes->length === 0) return;

        $node = $nodes->item(0);
        $val = $node->textContent;
        $curr = $node->hasAttribute('currCode') ? $node->getAttribute('currCode') : '';

        if (empty($curr)) {
            $this->addError('error', 'financial', $label.'/currCode', 'Devise manquante pour le montant', 'Code ISO (ex: USD, XAF)', 'absent', 'Préciser la devise', $section);
        }

        if (!is_numeric(str_replace(',', '.', $val))) {
            $this->addError('error', 'format', $label, 'Format numérique invalide', 'Nombre (Ex: 1250.50)', $val, 'Utiliser des chiffres et le point comme séparateur', $section, true);
        } elseif (str_contains($val, ',')) {
            $this->addError('error', 'format', $label, 'Séparateur décimal invalide (virgule)', 'Point (.)', $val, 'Remplacer la virgule par un point', $section, true);
        }
    }

    /**
     * Valide spécifiquement l'élément TIN.
     */
    private function validateTINElement(\DOMNode $parent, string $query, string $label, string $section): void
    {
        $nodes = $this->xpath->query($query, $parent);
        if ($nodes->length === 0) return;

        $val = $nodes->item(0)->textContent;
        $this->validateTIN($val, $label, $section, 'data');
    }

    /**
     * Vérifie qu'une chaîne ne contient pas d'espaces inutiles en début ou fin.
     */
    private function validateStringNoPadding(string $value, string $element, string $section): void
    {
        if ($value !== trim($value)) {
            $this->addError('warning', 'format', $element, 'Espaces de remplissage (padding) détectés', 'Pas d\'espaces en début/fin', '"' . $value . '"', 'Supprimer les espaces inutiles', $section, true);
        }
    }

    /**
     * Valide le format des numéros d'identification fiscale (TIN ou GIIN).
     */
    private function validateTIN(string $value, string $element, string $section, string $category = 'format'): void
    {
        $val = trim($value);
        if (empty($val)) return;
        
        // Remove common separators for format check if needed, but FATCA prefers certain formats
        if (preg_match('/\s/', $value)) {
            $this->addError('warning', 'format', $element, 'Espaces présents dans le TIN', 'Pas d\'espaces', $value, 'Supprimer les espaces dans le TIN', $section, true);
        }

        $giinPattern = '/^[A-Z0-9]{6}\.[A-Z0-9]{5}\.[A-Z]{2}\.[0-9]{3}$/i';
        $tin9 = '/^\d{9}$/';
        $tinDash1 = '/^\d{3}-\d{2}-\d{4}$/';
        $tinDash2 = '/^\d{2}-\d{7}$/';
        
        if (!preg_match($giinPattern, $val) && !preg_match($tin9, $val) && !preg_match($tinDash1, $val) && !preg_match($tinDash2, $val)) {
            $this->addError('error', $category, $element, 'Format TIN non conforme (doit être 9 chiffres ou GIIN)', 'SSN, ITIN, EIN ou GIIN', $val, 'Corriger le format du TIN', $section, true);
        }
    }

    /**
     * Valide la longueur d'une chaîne de caractères.
     */
    private function validateStringSize(string $value, string $element, int $min, int $max, string $section, string $category = 'format'): void
    {
        $val = trim($value);
        $len = strlen($val);
        if ($len < $min) {
            $this->addError('error', $category, $element, 'Valeur trop courte (' . $len . ' car.)', "Min $min car.", $val, 'Compléter l\'information', $section, true);
        }
        if ($len > $max) {
            $this->addError('error', $category, $element, 'Valeur trop longue (' . $len . ' car.)', "Max $max car.", substr($val, 0, 50) . '...', 'Tronquer la valeur', $section, true);
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
            'row_reference' => $this->currentRowIndex,
            'message' => $message,
            'expected_value' => $expected,
            'actual_value' => (string)$actual,
            'suggestion' => $suggestion,
            'fatca_section' => $section,
            'auto_correctable' => $correctable,
        ];
    }

    /**
     * Valide les éléments DocSpec dans tout le document.
     */
    private function validateDocSpecs(): void
    {
        $nodes = $this->xpath->query('//ftc:DocSpec');
        foreach ($nodes as $node) {
            // DocTypeIndic
            $typeIndic = $this->xpath->query('ftc:DocTypeIndic', $node);
            if ($typeIndic->length > 0) {
                $val = $typeIndic->item(0)->textContent;
                if (!in_array($val, ['FATCA1', 'FATCA2', 'FATCA3', 'FATCA4'])) {
                    $this->addError('error', 'format', 'DocTypeIndic', 'Code DocTypeIndic invalide', 'FATCA1, FATCA2, FATCA3 ou FATCA4', $val, 'Utiliser un code FATCA valide', 'Section 5');
                }
            }

            // DocRefId
            $refId = $this->xpath->query('ftc:DocRefId', $node);
            if ($refId->length === 0) {
                $this->addError('error', 'required', 'DocRefId', 'DocRefId manquant', 'ID unique', 'absent', 'Générer un DocRefId unique', 'Section 5');
            }
        }
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
