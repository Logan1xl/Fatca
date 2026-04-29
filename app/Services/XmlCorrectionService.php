<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ValidationError;
use DOMDocument;
use DOMXPath;

/**
 * Service de correction automatique des fichiers XML FATCA.
 */
class XmlCorrectionService
{
    /**
     * Corrige automatiquement les erreurs dans un fichier XML FATCA en se basant sur les résultats de validation.
     */
    public function correct(Report $report, string $xmlContent): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        // Try to load, if fails, we might need basic string replacements first
        if (!$dom->loadXML($xmlContent)) {
            // Fix basic encoding if possible
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', mb_detect_encoding($xmlContent));
            // Fix prohibited chars that break XML loading
            $xmlContent = str_replace(['&', '<', '>', "'", '"'], ['&amp;', '&lt;', '&gt;', '&apos;', '&quot;'], $xmlContent);
            // Re-try loading
            if (!$dom->loadXML($xmlContent)) {
                return $xmlContent; // Cannot auto-correct structural failure via DOM
            }
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ftc', 'urn:oecd:ties:fatca:v2');
        $xpath->registerNamespace('sfa', 'urn:oecd:ties:stffatcatypes:v2');

        $errors = $report->validationErrors()->where('auto_correctable', true)->get();

        foreach ($errors as $error) {
            $this->applyCorrection($dom, $xpath, $error);
        }

        return $dom->saveXML();
    }

    /**
     * Applique une correction spécifique au DOM.
     */
    private function applyCorrection(DOMDocument $dom, DOMXPath $xpath, ValidationError $error): void
    {
        // Category based correction logic
        switch ($error->category) {
            case 'characters':
                // Handled globally if it was SQL injection, but for specific elements:
                $this->sanitizeElement($xpath, $error->element);
                break;

            case 'size':
                $this->truncateElement($xpath, $error->element, $this->extractLimit($error->expected_value));
                break;

            case 'format':
                if ($error->element === 'ReportingPeriod') {
                    $this->fixDateFormat($xpath, $error->element);
                } elseif ($error->element === 'Timestamp') {
                    $this->fixTimestampFormat($xpath, $error->element);
                } elseif (str_contains($error->element, 'TIN')) {
                    $this->fixTinFormat($xpath, $error->element);
                }
                break;

            case 'business_rule':
                if ($error->element === 'DocTypeIndic' && str_contains($error->actual_value, '11')) {
                    $this->replaceValue($xpath, $error->element, str_replace('11', '1', $error->actual_value));
                }
                break;
        }
    }

    /**
     * Nettoie le contenu d'un élément (supprime les séquences interdites).
     */
    private function sanitizeElement(DOMXPath $xpath, string $elementName): void
    {
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            $val = $node->textContent;
            $val = str_replace(['--', '/*', '&#'], '', $val);
            $node->textContent = $val;
        }
    }

    /**
     * Tronque le texte d'un élément s'il dépasse la limite autorisée.
     */
    private function truncateElement(DOMXPath $xpath, string $elementName, int $limit): void
    {
        if ($limit <= 0) return;
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            if (strlen($node->textContent) > $limit) {
                $node->textContent = substr($node->textContent, 0, $limit);
            }
        }
    }

    /**
     * Corrige le format de date (YYYY-MM-DD).
     */
    private function fixDateFormat(DOMXPath $xpath, string $elementName): void
    {
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            $ts = strtotime($node->textContent);
            if ($ts) {
                $node->textContent = date('Y-m-d', $ts);
            }
        }
    }

    /**
     * Corrige le format du timestamp (ISO 8601).
     */
    private function fixTimestampFormat(DOMXPath $xpath, string $elementName): void
    {
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            $ts = strtotime($node->textContent);
            if ($ts) {
                $node->textContent = date('Y-m-d\TH:i:s', $ts);
            }
        }
    }

    /**
     * Corrige le format des numéros TIN/GIIN.
     */
    private function fixTinFormat(DOMXPath $xpath, string $elementName): void
    {
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            $val = preg_replace('/[^A-Z0-9.\-]/i', '', $node->textContent);
            $node->textContent = strtoupper($val);
        }
    }

    /**
     * Remplace la valeur d'un élément par une nouvelle valeur.
     */
    private function replaceValue(DOMXPath $xpath, string $elementName, string $newValue): void
    {
        $nodes = $xpath->query("//*[local-name()='$elementName']");
        foreach ($nodes as $node) {
            $node->textContent = $newValue;
        }
    }

    /**
     * Extrait la limite numérique à partir d'une chaîne descriptive.
     */
    private function extractLimit(string $expected): int
    {
        if (preg_match('/Max (\d+)/i', $expected, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
}
