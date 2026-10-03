<?php

declare(strict_types=1);

namespace App\Service\Receive;

use horstoeko\zugferd\ZugferdDocumentReader;

/**
 * Parses UN/CEFACT Cross-Industry-Invoice XML (XRechnung CII, ZUGFeRD, Factur-X)
 * using horstoeko/zugferd.
 */
final class CiiInvoiceParser
{
    public function parse(string $xml): ParsedInvoice
    {
        $reader = ZugferdDocumentReader::readAndGuessFromContent($xml);
        $parsed = new ParsedInvoice();

        $docNo = $typeCode = $currency = $taxCurrency = $docName = $docLang = null;
        $docDate = $effectivePeriod = null;
        $reader->getDocumentInformation($docNo, $typeCode, $docDate, $currency, $taxCurrency, $docName, $docLang, $effectivePeriod);
        $parsed->invoiceNumber = $docNo;
        $parsed->invoiceTypeCode = $typeCode;
        $parsed->issueDate = $this->toImmutable($docDate);
        $parsed->currency = $currency;

        $buyerRef = null;
        $reader->getDocumentBuyerReference($buyerRef);
        $parsed->buyerReference = $buyerRef;

        $routingId = null;
        $reader->getDocumentRoutingId($routingId);
        $parsed->leitwegId = $routingId ?: ($this->isLikelyLeitweg($buyerRef) ? $buyerRef : null);

        $sellerName = null; $sellerIds = []; $sellerDesc = null;
        $reader->getDocumentSeller($sellerName, $sellerIds, $sellerDesc);
        $parsed->sellerName = $sellerName;

        $sellerTax = [];
        $reader->getDocumentSellerTaxRegistration($sellerTax);
        $parsed->sellerVatId = $sellerTax['VA'] ?? ($sellerTax['vat'] ?? null);

        $s1 = $s2 = $s3 = $sPost = $sCity = $sCountry = null; $sSub = [];
        $reader->getDocumentSellerAddress($s1, $s2, $s3, $sPost, $sCity, $sCountry, $sSub);
        $parsed->sellerAddress = $this->formatAddress([$s1, $s2, $s3, trim(($sPost ?? '').' '.($sCity ?? '')), $sCountry]);

        if ($reader->firstDocumentSellerContact()) {
            $cName = $cDept = $cPhone = $cFax = $cEmail = null;
            $reader->getDocumentSellerContact($cName, $cDept, $cPhone, $cFax, $cEmail);
            $parsed->sellerEmail = $cEmail;
        }

        $buyerName = null; $buyerIds = []; $buyerDesc = null;
        $reader->getDocumentBuyer($buyerName, $buyerIds, $buyerDesc);
        $parsed->buyerName = $buyerName;

        $buyerTax = [];
        $reader->getDocumentBuyerTaxRegistration($buyerTax);
        $parsed->buyerVatId = $buyerTax['VA'] ?? ($buyerTax['vat'] ?? null);

        $b1 = $b2 = $b3 = $bPost = $bCity = $bCountry = null; $bSub = [];
        $reader->getDocumentBuyerAddress($b1, $b2, $b3, $bPost, $bCity, $bCountry, $bSub);
        $parsed->buyerAddress = $this->formatAddress([$b1, $b2, $b3, trim(($bPost ?? '').' '.($bCity ?? '')), $bCountry]);

        $grand = $due = $lineTotal = $chargeTotal = $allowanceTotal = $taxBasisTotal = $taxTotal = $rounding = $prepaid = null;
        $reader->getDocumentSummation($grand, $due, $lineTotal, $chargeTotal, $allowanceTotal, $taxBasisTotal, $taxTotal, $rounding, $prepaid);
        $parsed->totalNet = $this->fmt($taxBasisTotal ?? $lineTotal);
        $parsed->totalTax = $this->fmt($taxTotal);
        $parsed->totalGross = $this->fmt($grand);
        $parsed->amountDue = $this->fmt($due);

        $creditorRef = $paymentRef = null;
        $reader->getDocumentGeneralPaymentInformation($creditorRef, $paymentRef);
        $parsed->paymentReference = $paymentRef;

        if ($reader->firstGetDocumentPaymentMeans()) {
            do {
                $typeCode = $info = $cardType = $cardId = $cardHolder = $buyerIban = $payeeIban = $payeeAcct = $payeeProp = $payeeBic = null;
                $reader->getDocumentPaymentMeans($typeCode, $info, $cardType, $cardId, $cardHolder, $buyerIban, $payeeIban, $payeeAcct, $payeeProp, $payeeBic);
                if ($payeeIban !== null && $payeeIban !== '') {
                    $parsed->sellerIban = $payeeIban;
                    $parsed->sellerBic = $payeeBic;
                    break;
                }
            } while ($reader->nextGetDocumentPaymentMeans());
        }

        $periodStart = $periodEnd = null;
        $reader->getDocumentBillingPeriod($periodStart, $periodEnd);
        $parsed->periodStart = $this->toImmutable($periodStart);
        $parsed->periodEnd = $this->toImmutable($periodEnd);

        if ($reader->firstDocumentPaymentTerms()) {
            $desc = $dueDate = $mandate = null;
            $reader->getDocumentPaymentTerm($desc, $dueDate, $mandate);
            $parsed->paymentTermsText = $desc;
            $parsed->dueDate = $this->toImmutable($dueDate);
        }

        if ($reader->firstDocumentPosition()) {
            $i = 0;
            do {
                $i++;
                $lineId = $statusCode = $statusReason = null;
                $reader->getDocumentPositionGenerals($lineId, $statusCode, $statusReason);
                $name = $description = $sellerAssigned = $buyerAssigned = $globalIdType = $globalId = null;
                $reader->getDocumentPositionProductDetails($name, $description, $sellerAssigned, $buyerAssigned, $globalIdType, $globalId);
                $qty = $qtyUnit = $cfQty = $cfUnit = $pkgQty = $pkgUnit = null;
                $reader->getDocumentPositionQuantity($qty, $qtyUnit, $cfQty, $cfUnit, $pkgQty, $pkgUnit);
                $price = $priceBasisQty = $priceBasisUnit = null;
                $reader->getDocumentPositionNetPrice($price, $priceBasisQty, $priceBasisUnit);
                $lineNet = null;
                $reader->getDocumentPositionLineSummationSimple($lineNet);
                $parsed->lines[] = [
                    'lineId' => $lineId ?: (string) $i,
                    'name' => $name,
                    'description' => $description,
                    'quantity' => $this->fmt($qty, 4),
                    'unit' => $qtyUnit,
                    'unitPrice' => $this->fmt($price, 4),
                    'lineNet' => $this->fmt($lineNet),
                ];
            } while ($reader->nextDocumentPosition());
        }

        return $parsed;
    }

    private function toImmutable(?\DateTime $d): ?\DateTimeImmutable
    {
        return $d === null ? null : \DateTimeImmutable::createFromMutable($d);
    }

    private function fmt(?float $v, int $scale = 2): ?string
    {
        if ($v === null) {
            return null;
        }
        return number_format($v, $scale, '.', '');
    }

    private function formatAddress(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(static fn ($p) => $p === null ? null : trim((string) $p), $parts), static fn ($p) => $p !== null && $p !== ''));
        return $parts === [] ? null : implode("\n", $parts);
    }

    private function isLikelyLeitweg(?string $v): bool
    {
        return $v !== null && preg_match('/^\d{2,3}-[A-Z0-9]+-\d{2}$/i', $v) === 1;
    }
}
