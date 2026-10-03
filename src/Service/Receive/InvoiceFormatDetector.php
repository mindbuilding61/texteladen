<?php

declare(strict_types=1);

namespace App\Service\Receive;

use App\Entity\IncomingInvoice;

final class InvoiceFormatDetector
{
    public const TYPE_PDF = 'pdf';
    public const TYPE_XML_UBL = 'xml_ubl';
    public const TYPE_XML_CII = 'xml_cii';
    public const TYPE_XML_UNKNOWN = 'xml_unknown';
    public const TYPE_UNKNOWN = 'unknown';

    public function detectType(string $content, string $filename = ''): string
    {
        if ($this->looksLikePdf($content)) {
            return self::TYPE_PDF;
        }

        $trimmed = ltrim($content);
        if ($trimmed === '' || $trimmed[0] !== '<') {
            if (str_ends_with(strtolower($filename), '.xml')) {
                return self::TYPE_XML_UNKNOWN;
            }
            return self::TYPE_UNKNOWN;
        }

        $head = substr($content, 0, 8192);
        if (str_contains($head, 'oasis:names:specification:ubl:schema:xsd:Invoice-2')
            || preg_match('/<([A-Za-z0-9_]+:)?Invoice[\s>]/', $head) === 1
            || preg_match('/<([A-Za-z0-9_]+:)?CreditNote[\s>]/', $head) === 1
        ) {
            return self::TYPE_XML_UBL;
        }

        if (str_contains($head, 'CrossIndustryInvoice')
            || str_contains($head, 'un/cefact')
            || str_contains($head, 'rsm:CrossIndustryInvoice')
        ) {
            return self::TYPE_XML_CII;
        }

        return self::TYPE_XML_UNKNOWN;
    }

    public function detectProfile(string $xml): string
    {
        $head = substr($xml, 0, 16384);
        if (preg_match('#<(?:[A-Za-z0-9_]+:)?GuidelineSpecifiedDocumentContextParameter>\s*<(?:[A-Za-z0-9_]+:)?ID>([^<]+)</#s', $head, $m) === 1) {
            return trim($m[1]);
        }
        if (preg_match('#<(?:[A-Za-z0-9_]+:)?CustomizationID>([^<]+)</#s', $head, $m) === 1) {
            return trim($m[1]);
        }
        return 'unknown';
    }

    public function classifyFormat(string $xml, string $sourceType): string
    {
        $profile = $this->detectProfile($xml);
        $isXRechnung = str_contains(strtolower($profile), 'xrechnung');

        if ($sourceType === self::TYPE_XML_UBL) {
            return $isXRechnung ? IncomingInvoice::FORMAT_XRECHNUNG_UBL : IncomingInvoice::FORMAT_XRECHNUNG_UBL;
        }

        if ($sourceType === self::TYPE_XML_CII) {
            if ($isXRechnung) {
                return IncomingInvoice::FORMAT_XRECHNUNG_CII;
            }
            if (str_contains(strtolower($profile), 'factur-x')) {
                return IncomingInvoice::FORMAT_FACTUR_X;
            }
            return IncomingInvoice::FORMAT_ZUGFERD;
        }

        if ($sourceType === self::TYPE_PDF) {
            if (str_contains(strtolower($profile), 'factur-x')) {
                return IncomingInvoice::FORMAT_FACTUR_X;
            }
            if (str_contains(strtolower($profile), 'xrechnung')) {
                return IncomingInvoice::FORMAT_XRECHNUNG_CII;
            }
            return IncomingInvoice::FORMAT_ZUGFERD;
        }

        return IncomingInvoice::FORMAT_UNKNOWN;
    }

    private function looksLikePdf(string $content): bool
    {
        return str_starts_with($content, '%PDF-');
    }
}
