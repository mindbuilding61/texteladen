<?php

declare(strict_types=1);

namespace App\Service\Receive;

/**
 * Normalized view of an incoming invoice, built from either CII or UBL.
 */
final class ParsedInvoice
{
    public ?string $invoiceNumber = null;
    public ?string $invoiceTypeCode = null;
    public ?\DateTimeImmutable $issueDate = null;
    public ?\DateTimeImmutable $dueDate = null;
    public ?\DateTimeImmutable $periodStart = null;
    public ?\DateTimeImmutable $periodEnd = null;
    public ?string $currency = null;
    public ?string $buyerReference = null;
    public ?string $leitwegId = null;

    public ?string $sellerName = null;
    public ?string $sellerVatId = null;
    public ?string $sellerIban = null;
    public ?string $sellerBic = null;
    public ?string $sellerEmail = null;
    public ?string $sellerAddress = null;

    public ?string $buyerName = null;
    public ?string $buyerVatId = null;
    public ?string $buyerAddress = null;

    public ?string $totalNet = null;
    public ?string $totalTax = null;
    public ?string $totalGross = null;
    public ?string $amountDue = null;

    public ?string $paymentReference = null;
    public ?string $paymentTermsText = null;

    /** @var array<int, array<string, string|null>> */
    public array $lines = [];

    /** @var array<int, array<string, string|null>> */
    public array $taxBreakdown = [];

    /** @var array<int, string> */
    public array $notes = [];

    public function toArray(): array
    {
        return [
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceTypeCode' => $this->invoiceTypeCode,
            'issueDate' => $this->issueDate?->format('Y-m-d'),
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'periodStart' => $this->periodStart?->format('Y-m-d'),
            'periodEnd' => $this->periodEnd?->format('Y-m-d'),
            'currency' => $this->currency,
            'buyerReference' => $this->buyerReference,
            'leitwegId' => $this->leitwegId,
            'seller' => [
                'name' => $this->sellerName,
                'vatId' => $this->sellerVatId,
                'iban' => $this->sellerIban,
                'bic' => $this->sellerBic,
                'email' => $this->sellerEmail,
                'address' => $this->sellerAddress,
            ],
            'buyer' => [
                'name' => $this->buyerName,
                'vatId' => $this->buyerVatId,
                'address' => $this->buyerAddress,
            ],
            'totals' => [
                'net' => $this->totalNet,
                'tax' => $this->totalTax,
                'gross' => $this->totalGross,
                'due' => $this->amountDue,
            ],
            'payment' => [
                'reference' => $this->paymentReference,
                'terms' => $this->paymentTermsText,
            ],
            'lines' => $this->lines,
            'taxBreakdown' => $this->taxBreakdown,
            'notes' => $this->notes,
        ];
    }
}
