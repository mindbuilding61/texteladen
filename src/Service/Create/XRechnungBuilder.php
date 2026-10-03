<?php

declare(strict_types=1);

namespace App\Service\Create;

use App\Entity\OutgoingInvoice;
use App\Entity\Settings;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

/**
 * Builds UN/CEFACT Cross-Industry-Invoice XML conforming to XRechnung 3.x.
 * Kleinunternehmer (§ 19 UStG) mode: all lines use tax category "E" (Exempt from Tax)
 * at 0% and an exemption reason referencing § 19 UStG.
 */
final class XRechnungBuilder
{
    public function build(OutgoingInvoice $invoice, Settings $settings): string
    {
        return $this->buildBuilder($invoice, $settings)->getContent();
    }

    public function buildBuilder(OutgoingInvoice $invoice, Settings $settings): ZugferdDocumentBuilder
    {
        $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);

        $invoice->recalculateTotals();
        $invoiceNumber = $invoice->getInvoiceNumber() ?? '(Entwurf)';

        $builder->setDocumentInformation(
            $invoiceNumber,
            '380',
            \DateTime::createFromImmutable($invoice->getIssueDate()),
            $invoice->getCurrency()
        );

        $buyerRef = $invoice->getBuyerReference() ?: ($invoice->getCustomer()?->getBuyerReference() ?: 'N/A');
        $builder->setDocumentBuyerReference($buyerRef);

        if ($invoice->getLeitwegId()) {
            $builder->setDocumentRoutingId($invoice->getLeitwegId());
        } elseif ($invoice->getCustomer() && $invoice->getCustomer()->getLeitwegId()) {
            $builder->setDocumentRoutingId($invoice->getCustomer()->getLeitwegId());
        }

        if ($invoice->getServicePeriodStart() && $invoice->getServicePeriodEnd()) {
            $builder->setDocumentBillingPeriod(
                \DateTime::createFromImmutable($invoice->getServicePeriodStart()),
                \DateTime::createFromImmutable($invoice->getServicePeriodEnd()),
            );
        }

        foreach ($this->splitNotes($invoice->getIntroText()) as $note) {
            $builder->addDocumentNote($note);
        }
        if ($settings->isKleinunternehmer()) {
            $builder->addDocumentNote($settings->getKleinunternehmerNote(), null, 'AAI');
        }
        foreach ($this->splitNotes($invoice->getOutroText()) as $note) {
            $builder->addDocumentNote($note);
        }

        $builder->setDocumentSeller($settings->getCompanyName());
        if ($settings->getVatId()) {
            $builder->addDocumentSellerVATRegistrationNumber($settings->getVatId());
        }
        if ($settings->getTaxNumber()) {
            $builder->addDocumentSellerTaxNumber($settings->getTaxNumber());
        }
        $builder->setDocumentSellerAddress(
            $settings->getStreet(),
            null,
            null,
            $settings->getPostalCode(),
            $settings->getCity(),
            $settings->getCountry() ?: 'DE'
        );
        if ($settings->getContactName() || $settings->getEmail() || $settings->getPhone()) {
            $builder->setDocumentSellerContact(
                $settings->getContactName(),
                null,
                $settings->getPhone(),
                null,
                $settings->getEmail()
            );
        }
        if ($settings->getEmail()) {
            $builder->setDocumentSellerCommunication('EM', $settings->getEmail());
        }

        $customer = $invoice->getCustomer();
        if ($customer === null) {
            throw new \RuntimeException('Rechnung ohne Kunden kann nicht exportiert werden.');
        }
        $builder->setDocumentBuyer($customer->getName());
        if ($customer->getVatId()) {
            $builder->addDocumentBuyerVATRegistrationNumber($customer->getVatId());
        }
        $builder->setDocumentBuyerAddress(
            $customer->getStreet(),
            null,
            null,
            $customer->getPostalCode(),
            $customer->getCity(),
            $customer->getCountry() ?: 'DE'
        );
        if ($customer->getEmail()) {
            $builder->setDocumentBuyerCommunication('EM', $customer->getEmail());
        }

        if ($settings->getIban()) {
            $builder->addDocumentPaymentMeanToCreditTransfer(
                $settings->getIban(),
                $settings->getCompanyName(),
                null,
                $settings->getBic()
            );
        }

        $dueDate = \DateTime::createFromImmutable($invoice->getDueDate());
        $builder->addDocumentPaymentTerm(
            sprintf('Zahlbar bis %s.', $invoice->getDueDate()->format('d.m.Y')),
            $dueDate
        );

        $isExempt = $settings->isKleinunternehmer();
        $taxCategory = $isExempt ? 'E' : 'S';
        $taxRate = 0.0;
        $exemptionReason = $isExempt ? $settings->getKleinunternehmerNote() : null;
        $exemptionReasonCode = $isExempt ? 'VATEX-EU-O' : null;

        $lineTotal = 0.0;
        $pos = 1;
        foreach ($invoice->getLines() as $line) {
            $builder->addNewPosition((string) $pos);
            $builder->setDocumentPositionProductDetails($line->getName(), $line->getDescription());
            $qty = (float) $line->getQuantity();
            $unitPrice = (float) $line->getUnitPrice();
            $builder->setDocumentPositionNetPrice($unitPrice);
            $builder->setDocumentPositionQuantity($qty, $line->getUnit() ?: 'C62');
            $builder->addDocumentPositionTax(
                $taxCategory, 'VAT', $taxRate,
                null, $exemptionReason, $exemptionReasonCode
            );
            $lineNet = (float) $line->getLineNet();
            $builder->setDocumentPositionLineSummation($lineNet);
            $lineTotal += $lineNet;
            $pos++;
        }

        $builder->addDocumentTax(
            $taxCategory, 'VAT',
            round($lineTotal, 2),
            0.0,
            $taxRate,
            $exemptionReason,
            $exemptionReasonCode
        );

        $builder->setDocumentSummation(
            round($lineTotal, 2),
            round($lineTotal, 2),
            round($lineTotal, 2),
            0.0,
            0.0,
            round($lineTotal, 2),
            0.0
        );

        return $builder;
    }

    /** @return string[] */
    private function splitNotes(?string $text): array
    {
        if ($text === null) {
            return [];
        }
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/\R{2,}/', $text) ?: [$text];
        return array_values(array_filter(array_map('trim', $parts)));
    }
}
