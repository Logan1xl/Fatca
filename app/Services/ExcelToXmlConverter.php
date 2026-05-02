<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Service de conversion de fichiers Excel/CSV vers le format FATCA XML v2.0.
 */
class ExcelToXmlConverter
{
    private DOMDocument $dom;
    private array $data = [];
    private array $headers = [];

    /**
     * Convertit un fichier Excel en format FATCA XML.
     * Gère intelligemment le mappage des colonnes vers les éléments FATCA.
     */
    public function convert(string $excelPath, ?string $reportingPeriod = null): array
    {
        $this->data = $this->readExcel($excelPath);

        if (empty($this->data)) {
            return ['success' => false, 'error' => 'Le fichier Excel est vide.', 'xml' => null, 'records' => 0];
        }

        $this->headers = array_keys($this->data[0] ?? []);
        $mapping = $this->detectColumnMapping();

        $xmlContent = $this->buildFatcaXml($mapping, $reportingPeriod);

        return [
            'success' => true,
            'xml' => $xmlContent,
            'records' => count($this->data),
            'raw_data' => $this->data,
            'mapping' => $mapping,
            'headers_detected' => $this->headers,
        ];
    }

    /**
     * Lit le fichier Excel et retourne les données sous forme de tableau associatif.
     */
    private function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $rows = $worksheet->toArray(null, true, true, true);
            
            if (empty($rows)) continue;

            // Detect header row (first row with significant data)
            $headerRowIndex = null;
            $headers = [];
            
            foreach ($rows as $index => $row) {
                $nonEmptyCount = 0;
                foreach ($row as $val) {
                    if (!empty(trim((string)$val))) $nonEmptyCount++;
                }
                
                // If we find a row with at least 2 potential headers, assume it's the header row
                if ($nonEmptyCount >= 2) {
                    $headerRowIndex = $index;
                    foreach ($row as $col => $header) {
                        $headerText = (string)($header ?? '');
                        if (!empty(trim($headerText))) {
                            $headers[$col] = $this->normalizeHeader($headerText);
                        }
                    }
                    // Only accept if we actually got some valid headers
                    if (count($headers) >= 2) {
                        break;
                    } else {
                        $headerRowIndex = null;
                        $headers = [];
                    }
                }
            }

            if ($headerRowIndex === null) continue;

            $data = [];
            $isPastHeader = false;
            foreach ($rows as $index => $row) {
                if ($index === $headerRowIndex) {
                    $isPastHeader = true;
                    continue;
                }
                if (!$isPastHeader) continue;

                $record = [];
                $hasData = false;
                foreach ($headers as $col => $header) {
                    if (!empty($header)) {
                        $value = isset($row[$col]) ? (string)$row[$col] : '';
                        $value = $this->sanitizeCellValue($value);
                        $record[$header] = $value;
                        if (!empty($value)) $hasData = true;
                    }
                }
                
                if ($hasData) {
                    $record['_row_index'] = $index;
                    $data[] = $record;
                }
            }

            if (!empty($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Normalise les noms d'en-tête pour un mappage cohérent.
     */
    private function normalizeHeader(string $header): string
    {
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9_\s]/', '', $header);
        $header = preg_replace('/\s+/', '_', $header);
        return trim($header, '_');
    }

    /**
     * Détecte intelligemment le mappage des colonnes vers les éléments FATCA XML.
     */
    private function detectColumnMapping(): array
    {
        $mapping = [];
        $patterns = [
            // MessageSpec
            'sending_company' => ['giin', 'sending_company', 'sendingcompanyin', 'company_in', 'filer_giin', 'giin_emetteur'],
            'transmitting_country' => ['transmitting_country', 'pays_emetteur', 'country_code_sender', 'pays_envoi'],
            'receiving_country' => ['receiving_country', 'pays_destinataire', 'country_code_receiver', 'pays_reception'],
            'reporting_period' => ['reporting_period', 'periode', 'period', 'annee', 'year', 'tax_year', 'annee_fiscale'],

            // Account Holder - Individual
            'first_name' => ['first_name', 'firstname', 'prenom', 'given_name', 'nom_prenom', 'first'],
            'last_name' => ['last_name', 'lastname', 'nom', 'family_name', 'surname', 'nom_famille'],
            'middle_name' => ['middle_name', 'middlename', 'deuxieme_prenom', 'second_prenom'],
            'birth_date' => ['birth_date', 'birthdate', 'date_naissance', 'dob', 'date_of_birth', 'naissance'],

            // TIN
            'tin' => ['tin', 'tax_id', 'tax_identification', 'numero_fiscal', 'ssn', 'itin', 'ein', 'nif', 'tin_titulaire'],
            'tin_issuer' => ['tin_issued_by', 'tin_issuer', 'pays_tin', 'tin_country', 'issued_by'],

            // Address
            'country_code' => ['country_code', 'code_pays', 'country', 'pays', 'pays_residence', 'res_country', 'rescountrycode'],
            'address' => ['address', 'adresse', 'address_free', 'adresse_libre', 'full_address'],
            'city' => ['city', 'ville', 'town', 'localite'],
            'street' => ['street', 'rue', 'avenue', 'road'],
            'postal_code' => ['postal_code', 'code_postal', 'zip', 'postcode', 'cp'],

            // Account
            'account_number' => ['account_number', 'numero_compte', 'account_no', 'acct_number', 'no_compte', 'iban'],
            'account_balance' => ['account_balance', 'solde', 'balance', 'solde_compte', 'montant_solde'],
            'currency' => ['currency', 'devise', 'currency_code', 'monnaie', 'curr'],
            'account_closed' => ['account_closed', 'compte_ferme', 'closed', 'ferme', 'cloture'],

            // Payments
            'payment_dividends' => ['dividends', 'dividendes', 'payment_dividends', 'fatca501'],
            'payment_interest' => ['interest', 'interets', 'payment_interest', 'fatca502'],
            'payment_gross_proceeds' => ['gross_proceeds', 'produits_bruts', 'payment_gross', 'fatca503'],
            'payment_other' => ['other_payment', 'autres_paiements', 'payment_other', 'fatca504', 'other'],

            // Account Holder Type
            'account_holder_type' => ['account_holder_type', 'type_titulaire', 'acct_holder_type', 'holder_type', 'type_compte'],

            // Organisation
            'org_name' => ['organisation_name', 'org_name', 'entity_name', 'nom_entite', 'nom_organisation', 'raison_sociale'],

            // Reporting FI
            'fi_name' => ['fi_name', 'reporting_fi', 'nom_fi', 'institution_name', 'nom_institution', 'banque'],
            'fi_giin' => ['fi_giin', 'reporting_fi_giin', 'giin_fi', 'institution_giin'],
            'filer_category' => ['filer_category', 'categorie', 'category', 'type_declarant'],
        ];

        $matchedCount = 0;
        foreach ($patterns as $fatcaField => $possibleHeaders) {
            foreach ($this->headers as $header) {
                $normalizedHeader = $this->normalizeHeader($header);
                foreach ($possibleHeaders as $pattern) {
                    if ($normalizedHeader === $pattern || preg_match('/(?:\b|_)' . preg_quote($pattern, '/') . '(?:\b|_)/i', $normalizedHeader)) {
                        $mapping[$fatcaField] = $header;
                        $matchedCount++;
                        break 2;
                    }
                }
            }
        }

        // Positional Fallback if no headers were detected
        if ($matchedCount < 3 && count($this->headers) >= 10) {
            $positional = [
                'last_name' => 0,
                'first_name' => 1,
                'middle_name' => 2,
                'city' => 3,
                'address' => 4,
                'postal_code' => 5,
                'account_number' => 7,
                'account_balance' => 9,
                'birth_date' => 10,
                'country_code' => 13,
                'tin' => 14,
            ];
            foreach ($positional as $field => $pos) {
                if (isset($this->headers[$pos]) && !isset($mapping[$field])) {
                    $mapping[$field] = $this->headers[$pos];
                }
            }
        }

        return $mapping;
    }

    /**
     * Construit le document FATCA XML à partir des données mappées.
     */
    private function buildFatcaXml(array $mapping, ?string $reportingPeriod): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Root element with namespaces
        $root = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:FATCA_OECD');
        $root->setAttribute('version', '2.0');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sfa', 'urn:oecd:ties:stffatcatypes:v2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ftc', 'urn:oecd:ties:fatca:v2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso', 'urn:oecd:ties:isofatcatypes:v1');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:stf', 'urn:oecd:ties:stf:v4');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->dom->appendChild($root);

        // MessageSpec
        $messageSpec = $this->buildMessageSpec($mapping, $reportingPeriod);
        $root->appendChild($messageSpec);

        // FATCA element
        $fatca = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:FATCA');
        $root->appendChild($fatca);

        // ReportingFI
        $reportingFI = $this->buildReportingFI($mapping);
        $fatca->appendChild($reportingFI);

        // ReportingGroup
        $reportingGroup = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:ReportingGroup');
        $fatca->appendChild($reportingGroup);

        // Account Reports
        foreach ($this->data as $row) {
            $accountReport = $this->buildAccountReport($row, $mapping);
            $reportingGroup->appendChild($accountReport);
        }

        return $this->dom->saveXML();
    }

    /**
     * Construit l'élément MessageSpec.
     */
    private function buildMessageSpec(array $mapping, ?string $reportingPeriod): DOMElement
    {
        $ms = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:MessageSpec');

        $sendingIN = $this->getFirstValue($mapping, 'sending_company') ?: '000000.00000.LE.000';
        $ms->appendChild($this->createSfaElement('SendingCompanyIN', $sendingIN));

        $transCountry = $this->getFirstValue($mapping, 'transmitting_country') ?: 'CM';
        $ms->appendChild($this->createSfaElement('TransmittingCountry', $transCountry));

        $ms->appendChild($this->createSfaElement('ReceivingCountry', 'US'));
        $ms->appendChild($this->createSfaElement('MessageType', 'FATCA'));

        $ms->appendChild($this->createSfaElement('MessageRefId', Str::uuid()->toString()));

        $period = $reportingPeriod ?: date('Y') . '-12-31';
        $ms->appendChild($this->createSfaElement('ReportingPeriod', $period));
        $ms->appendChild($this->createSfaElement('Timestamp', date('Y-m-d\TH:i:s')));

        return $ms;
    }

    /**
     * Construit l'élément ReportingFI (Institution Financière Déclarante).
     */
    private function buildReportingFI(array $mapping): DOMElement
    {
        $rfi = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:ReportingFI');

        // ResCountryCode
        $country = $this->getFirstValue($mapping, 'transmitting_country') ?: 'CM';
        $rfi->appendChild($this->createSfaElement('ResCountryCode', $country));

        // TIN (GIIN)
        $giin = $this->getFirstValue($mapping, 'fi_giin') ?: $this->getFirstValue($mapping, 'sending_company') ?: '';
        if (!empty($giin)) {
            $tin = $this->createSfaElement('TIN', $giin);
            $tin->setAttribute('issuedBy', $country);
            $rfi->appendChild($tin);
        }

        // Name
        $name = $this->getFirstValue($mapping, 'fi_name') ?: 'CBC BANK';
        $nameElem = $this->createSfaElement('Name', $name);
        $rfi->appendChild($nameElem);

        // Address
        $address = $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:Address');
        $address->appendChild($this->createSfaElement('CountryCode', $country));
        $address->appendChild($this->createSfaElement('AddressFree', 'N/A'));
        $rfi->appendChild($address);

        // FilerCategory
        $category = $this->getFirstValue($mapping, 'filer_category') ?: 'FATCA602';
        $rfi->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:FilerCategory', $category));

        // DocSpec
        $docSpec = $this->buildDocSpec('FATCA1');
        $rfi->appendChild($docSpec);

        return $rfi;
    }

    /**
     * Construit un élément AccountReport pour une ligne de données.
     */
    private function buildAccountReport(array $row, array $mapping): DOMElement
    {
        $ar = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountReport');

        if (isset($row['_row_index'])) {
            $ar->setAttribute('rowIndex', $row['_row_index']);
        }

        // DocSpec
        $ar->appendChild($this->buildDocSpec('FATCA1'));

        // AccountNumber
        $acctNum = $this->getMappedValue($row, $mapping, 'account_number');
        if (!empty($acctNum)) {
            $ar->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountNumber', $this->escapeXml($acctNum)));
        } else {
             $ar->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountNumber', ''));
        }

        // AccountClosed
        $closed = $this->getMappedValue($row, $mapping, 'account_closed');
        if (!empty($closed) && in_array(strtolower($closed), ['yes', 'true', '1', 'oui'])) {
            $ar->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountClosed', 'true'));
        }

        // AccountHolder
        $holder = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountHolder');

        // Determine if individual or organisation
        $orgName = $this->getMappedValue($row, $mapping, 'org_name');
        $firstName = $this->getMappedValue($row, $mapping, 'first_name');

        if (!empty($orgName)) {
            // Organisation
            $org = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:Organisation');

            $orgCountry = $this->getMappedValue($row, $mapping, 'country_code');
            if (!empty($orgCountry)) {
                $org->appendChild($this->createSfaElement('ResCountryCode', $orgCountry));
            }

            $tin = $this->getMappedValue($row, $mapping, 'tin');
            if (!empty($tin)) {
                $tinElem = $this->createSfaElement('TIN', $this->escapeXml($tin));
                $issuer = $this->getMappedValue($row, $mapping, 'tin_issuer') ?: $orgCountry ?: 'US';
                $tinElem->setAttribute('issuedBy', $issuer);
                $org->appendChild($tinElem);
            }

            $org->appendChild($this->createSfaElement('Name', $this->escapeXml($orgName)));
            $org->appendChild($this->buildAddress($row, $mapping));
            $holder->appendChild($org);

            // AcctHolderType
            $holderType = $this->getMappedValue($row, $mapping, 'account_holder_type');
            if (!empty($holderType)) {
                $holder->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AcctHolderType', $holderType));
            }
        } else {
            // Individual
            $individual = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:Individual');

            $indCountry = $this->getMappedValue($row, $mapping, 'country_code');
            if (!empty($indCountry)) {
                $individual->appendChild($this->createSfaElement('ResCountryCode', $indCountry));
            }

            $tin = $this->getMappedValue($row, $mapping, 'tin');
            if (!empty($tin)) {
                $tinElem = $this->createSfaElement('TIN', $this->escapeXml($tin));
                $issuer = $this->getMappedValue($row, $mapping, 'tin_issuer') ?: $indCountry ?: 'US';
                $tinElem->setAttribute('issuedBy', $issuer);
                $individual->appendChild($tinElem);
            }

            // Name
            $nameElem = $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:Name');
            $nameElem->appendChild($this->createSfaElement('FirstName', $this->escapeXml($firstName)));

            $mn = $this->getMappedValue($row, $mapping, 'middle_name');
            if (!empty($mn)) {
                $nameElem->appendChild($this->createSfaElement('MiddleName', $this->escapeXml($mn)));
            }

            $ln = $this->getMappedValue($row, $mapping, 'last_name');
            $nameElem->appendChild($this->createSfaElement('LastName', $this->escapeXml($ln)));
            $individual->appendChild($nameElem);

            // Address
            $individual->appendChild($this->buildAddress($row, $mapping));

            // BirthDate
            $dob = $this->getMappedValue($row, $mapping, 'birth_date');
            if (!empty($dob)) {
                $birthInfo = $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:BirthInfo');
                $birthInfo->appendChild($this->createSfaElement('BirthDate', $this->formatDate($dob)));
                $individual->appendChild($birthInfo);
            }

            $holder->appendChild($individual);
        }

        $ar->appendChild($holder);

        // AccountBalance
        $balance = $this->getMappedValue($row, $mapping, 'account_balance');
        $currency = $this->getMappedValue($row, $mapping, 'currency') ?: 'USD';
        $balElem = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:AccountBalance', $this->formatAmount($balance));
        $balElem->setAttribute('currCode', strtoupper($currency));
        $ar->appendChild($balElem);

        // Payments
        $paymentTypes = [
            'payment_dividends' => 'FATCA501',
            'payment_interest' => 'FATCA502',
            'payment_gross_proceeds' => 'FATCA503',
            'payment_other' => 'FATCA504',
        ];

        foreach ($paymentTypes as $field => $type) {
            $amount = $this->getMappedValue($row, $mapping, $field);
            if (!empty($amount) && floatval($amount) != 0) {
                $payment = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:Payment');
                $payment->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:Type', $type));
                $payAmt = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:PaymentAmnt', $this->formatAmount($amount));
                $payAmt->setAttribute('currCode', strtoupper($currency));
                $payment->appendChild($payAmt);
                $ar->appendChild($payment);
            }
        }

        return $ar;
    }

    /**
     * Construit l'élément Adresse.
     */
    private function buildAddress(array $row, array $mapping): DOMElement
    {
        $address = $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:Address');
        $countryCode = $this->getMappedValue($row, $mapping, 'country_code') ?: 'US';
        $address->appendChild($this->createSfaElement('CountryCode', $countryCode));

        $city = $this->getMappedValue($row, $mapping, 'city');
        $street = $this->getMappedValue($row, $mapping, 'street');

        if (!empty($city) || !empty($street)) {
            $fix = $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:AddressFix');
            if (!empty($street)) {
                $fix->appendChild($this->createSfaElement('Street', $this->escapeXml($street)));
            }
            $postalCode = $this->getMappedValue($row, $mapping, 'postal_code');
            if (!empty($postalCode)) {
                $fix->appendChild($this->createSfaElement('PostCode', $this->escapeXml($postalCode)));
            }
            $fix->appendChild($this->createSfaElement('City', $this->escapeXml($city ?: 'N/A')));
            $address->appendChild($fix);
        } else {
            $freeAddr = $this->getMappedValue($row, $mapping, 'address') ?: 'N/A';
            $address->appendChild($this->createSfaElement('AddressFree', $this->escapeXml($freeAddr)));
        }

        return $address;
    }

    /**
     * Construit l'élément DocSpec (Spécification du document).
     */
    private function buildDocSpec(string $docTypeIndic): DOMElement
    {
        $docSpec = $this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:DocSpec');
        $docSpec->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:DocTypeIndic', $docTypeIndic));

        $giin = $this->getFirstValue($this->detectColumnMapping(), 'fi_giin')
            ?: $this->getFirstValue($this->detectColumnMapping(), 'sending_company')
            ?: '000000.00000.LE.000';
        $docRefId = $giin . '.' . Str::uuid()->toString();
        $docSpec->appendChild($this->dom->createElementNS('urn:oecd:ties:fatca:v2', 'ftc:DocRefId', $docRefId));

        return $docSpec;
    }

    /**
     * Crée un élément avec l'espace de noms sfa:.
     */
    private function createSfaElement(string $name, string $value): DOMElement
    {
        return $this->dom->createElementNS('urn:oecd:ties:stffatcatypes:v2', 'sfa:' . $name, $value);
    }

    /**
     * Récupère la première valeur non vide pour une clé de mappage.
     */
    private function getFirstValue(array $mapping, string $key): string
    {
        if (!isset($mapping[$key])) return '';
        $header = $mapping[$key];
        foreach ($this->data as $row) {
            if (!empty($row[$header])) {
                return trim($row[$header]);
            }
        }
        return '';
    }

    /**
     * Nettoie la valeur de la cellule (trim et suppression des séquences interdites).
     */
    private function sanitizeCellValue(string $value): string
    {
        $value = trim($value);
        // Remove common restricted sequences from P5124 Section 2.3
        $value = str_replace(['--', '/*', '&#'], '', $value);
        return $value;
    }

    /**
     * Récupère une valeur mappée à partir d'une ligne.
     */
    private function getMappedValue(array $row, array $mapping, string $key): string
    {
        if (!isset($mapping[$key])) return '';
        $header = $mapping[$key];
        return isset($row[$header]) ? trim((string)$row[$header]) : '';
    }

    /**
     * Échappe les caractères spéciaux XML et protège contre les injections.
     */
    private function escapeXml(string $value): string
    {
        // Remove SQL injection patterns
        $value = str_replace(['--', '/*', '&#'], '', $value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Formate un montant avec 2 décimales.
     */
    private function formatAmount(string $value): string
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);
        return number_format((float)$cleaned, 2, '.', '');
    }

    /**
     * Formate une date au format YYYY-MM-DD.
     */
    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            // Try Excel numeric date
            if (is_numeric($value)) {
                $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float)$value);
            }
        }
        return $timestamp ? date('Y-m-d', $timestamp) : $value;
    }
}
