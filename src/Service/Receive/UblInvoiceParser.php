<?php

declare(strict_types=1);

namespace App\Service\Receive;

/**
 * Parses OASIS UBL 2.1 Invoice / CreditNote (XRechnung UBL profile).
 * Uses DOMXPath because XRechnung UBL maps 1:1 to BT codes we can reach via XPath.
 */
final class UblInvoiceParser
{
    private const NS = [
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    ];

    public function parse(string $xml): ParsedInvoice
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            if (!$dom->loadXML($xml)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new \RuntimeException('Ungültiges UBL-XML: '.($errors[0]->message ?? 'unbekannt'));
            }
        } finally {
            libxml_use_internal_errors($prev);
        }

        $xpath = new \DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $parsed = new ParsedInvoice();

        $parsed->invoiceNumber = $this->str($xpath, '/*/cbc:ID');
        $parsed->invoiceTypeCode = $this->str($xpath, '/*/cbc:InvoiceTypeCode')
            ?? $this->str($xpath, '/*/cbc:CreditNoteTypeCode');
        $parsed->issueDate = $this->date($xpath, '/*/cbc:IssueDate');
        $parsed->dueDate = $this->date($xpath, '/*/cbc:DueDate');
        $parsed->currency = $this->str($xpath, '/*/cbc:DocumentCurrencyCode');
        $parsed->buyerReference = $this->str($xpath, '/*/cbc:BuyerReference');
        if ($parsed->buyerReference !== null && preg_match('/^\d{2,3}-/', $parsed->buyerReference)) {
            $parsed->leitwegId = $parsed->buyerReference;
        }
        $parsed->paymentReference = $this->str($xpath, '/*/cac:PaymentMeans/cbc:PaymentID');

        $parsed->periodStart = $this->date($xpath, '/*/cac:InvoicePeriod/cbc:StartDate');
        $parsed->periodEnd = $this->date($xpath, '/*/cac:InvoicePeriod/cbc:EndDate');

        $parsed->sellerName = $this->str($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')
            ?? $this->str($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name');
        $parsed->sellerVatId = $this->str($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID');
        $parsed->sellerEmail = $this->str($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail');
        $parsed->sellerAddress = $this->address($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress');

        $parsed->buyerName = $this->str($xpath, '/*/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')
            ?? $this->str($xpath, '/*/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name');
        $parsed->buyerVatId = $this->str($xpath, '/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID');
        $parsed->buyerAddress = $this->address($xpath, '/*/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress');

        $parsed->sellerIban = $this->str($xpath, '/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID');
        $parsed->sellerBic = $this->str($xpath, '/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID');

        $parsed->totalNet = $this->str($xpath, '/*/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount')
            ?? $this->str($xpath, '/*/cac:LegalMonetaryTotal/cbc:LineExtensionAmount');
        $parsed->totalGross = $this->str($xpath, '/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        $parsed->amountDue = $this->str($xpath, '/*/cac:LegalMonetaryTotal/cbc:PayableAmount');
        $parsed->totalTax = $this->str($xpath, '/*/cac:TaxTotal/cbc:TaxAmount');

        $terms = $this->str($xpath, '/*/cac:PaymentTerms/cbc:Note');
        $parsed->paymentTermsText = $terms;

        $noteNodes = $xpath->query('/*/cbc:Note');
        if ($noteNodes !== false) {
            foreach ($noteNodes as $n) {
                $txt = trim($n->textContent);
                if ($txt !== '') {
                    $parsed->notes[] = $txt;
                }
            }
        }

        $taxNodes = $xpath->query('/*/cac:TaxTotal/cac:TaxSubtotal');
        if ($taxNodes !== false) {
            foreach ($taxNodes as $t) {
                $parsed->taxBreakdown[] = [
                    'category' => $this->str($xpath, 'cac:TaxCategory/cbc:ID', $t),
                    'percent' => $this->str($xpath, 'cac:TaxCategory/cbc:Percent', $t),
                    'basis' => $this->str($xpath, 'cbc:TaxableAmount', $t),
                    'amount' => $this->str($xpath, 'cbc:TaxAmount', $t),
                    'exemptionReason' => $this->str($xpath, 'cac:TaxCategory/cbc:TaxExemptionReason', $t),
                ];
            }
        }

        $lineQueries = ['/*/cac:InvoiceLine', '/*/cac:CreditNoteLine'];
        foreach ($lineQueries as $q) {
            $lineNodes = $xpath->query($q);
            if ($lineNodes === false || $lineNodes->length === 0) {
                continue;
            }
            foreach ($lineNodes as $l) {
                $parsed->lines[] = [
                    'lineId' => $this->str($xpath, 'cbc:ID', $l),
                    'name' => $this->str($xpath, 'cac:Item/cbc:Name', $l),
                    'description' => $this->str($xpath, 'cac:Item/cbc:Description', $l),
                    'quantity' => $this->str($xpath, 'cbc:InvoicedQuantity', $l)
                        ?? $this->str($xpath, 'cbc:CreditedQuantity', $l),
                    'unit' => $this->attr($xpath, 'cbc:InvoicedQuantity/@unitCode', $l)
                        ?? $this->attr($xpath, 'cbc:CreditedQuantity/@unitCode', $l),
                    'unitPrice' => $this->str($xpath, 'cac:Price/cbc:PriceAmount', $l),
                    'lineNet' => $this->str($xpath, 'cbc:LineExtensionAmount', $l),
                ];
            }
        }

        return $parsed;
    }

    private function str(\DOMXPath $xpath, string $query, ?\DOMNode $ctx = null): ?string
    {
        $nodes = $xpath->query($query, $ctx);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $v = trim($nodes->item(0)->textContent);
        return $v === '' ? null : $v;
    }

    private function attr(\DOMXPath $xpath, string $query, ?\DOMNode $ctx = null): ?string
    {
        $nodes = $xpath->query($query, $ctx);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $v = trim($nodes->item(0)->nodeValue ?? '');
        return $v === '' ? null : $v;
    }

    private function date(\DOMXPath $xpath, string $query): ?\DateTimeImmutable
    {
        $v = $this->str($xpath, $query);
        if ($v === null) {
            return null;
        }
        try {
            return new \DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function address(\DOMXPath $xpath, string $base): ?string
    {
        $parts = [
            $this->str($xpath, "$base/cbc:StreetName"),
            $this->str($xpath, "$base/cbc:AdditionalStreetName"),
            trim((string) ($this->str($xpath, "$base/cbc:PostalZone") ?? '').' '.($this->str($xpath, "$base/cbc:CityName") ?? '')),
            $this->str($xpath, "$base/cac:Country/cbc:IdentificationCode"),
        ];
        $parts = array_values(array_filter(array_map(static fn ($p) => $p === null ? null : trim($p), $parts), static fn ($p) => $p !== null && $p !== ''));
        return $parts === [] ? null : implode("\n", $parts);
    }
}
