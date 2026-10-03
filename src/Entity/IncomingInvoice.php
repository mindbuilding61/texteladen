<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IncomingInvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncomingInvoiceRepository::class)]
#[ORM\Table(name: 'incoming_invoice')]
class IncomingInvoice
{
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_EMAIL = 'email';
    public const SOURCE_API = 'api';

    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_PAID = 'paid';
    public const STATUS_DISPUTED = 'disputed';

    public const FORMAT_XRECHNUNG_UBL = 'xrechnung_ubl';
    public const FORMAT_XRECHNUNG_CII = 'xrechnung_cii';
    public const FORMAT_ZUGFERD = 'zugferd';
    public const FORMAT_FACTUR_X = 'factur_x';
    public const FORMAT_UNKNOWN = 'unknown';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(length: 20)]
    private string $source = self::SOURCE_UPLOAD;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(length: 30)]
    private string $format = self::FORMAT_UNKNOWN;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profile = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(length: 128)]
    private string $storageKey = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sha256 = null;

    #[ORM\Column(type: 'integer')]
    private int $sizeBytes = 0;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $xmlStorageKey = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $pdfStorageKey = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $issueDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sellerName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sellerVatId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sellerIban = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerName = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $totalNet = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $totalTax = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $totalGross = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amountDue = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $validationErrors = null;

    #[ORM\Column(type: 'boolean')]
    private bool $validationPassed = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parsedData = null;

    public function __construct()
    {
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function setReceivedAt(\DateTimeImmutable $v): self { $this->receivedAt = $v; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $v): self { $this->source = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }

    public function getFormat(): string { return $this->format; }
    public function setFormat(string $v): self { $this->format = $v; return $this; }

    public function getProfile(): ?string { return $this->profile; }
    public function setProfile(?string $v): self { $this->profile = $v; return $this; }

    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $v): self { $this->originalFilename = $v; return $this; }

    public function getStorageKey(): string { return $this->storageKey; }
    public function setStorageKey(string $v): self { $this->storageKey = $v; return $this; }

    public function getSha256(): ?string { return $this->sha256; }
    public function setSha256(?string $v): self { $this->sha256 = $v; return $this; }

    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $v): self { $this->sizeBytes = $v; return $this; }

    public function getXmlStorageKey(): ?string { return $this->xmlStorageKey; }
    public function setXmlStorageKey(?string $v): self { $this->xmlStorageKey = $v; return $this; }

    public function getPdfStorageKey(): ?string { return $this->pdfStorageKey; }
    public function setPdfStorageKey(?string $v): self { $this->pdfStorageKey = $v; return $this; }

    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function setInvoiceNumber(?string $v): self { $this->invoiceNumber = $v; return $this; }

    public function getIssueDate(): ?\DateTimeImmutable { return $this->issueDate; }
    public function setIssueDate(?\DateTimeImmutable $v): self { $this->issueDate = $v; return $this; }

    public function getDueDate(): ?\DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(?\DateTimeImmutable $v): self { $this->dueDate = $v; return $this; }

    public function getSellerName(): ?string { return $this->sellerName; }
    public function setSellerName(?string $v): self { $this->sellerName = $v; return $this; }

    public function getSellerVatId(): ?string { return $this->sellerVatId; }
    public function setSellerVatId(?string $v): self { $this->sellerVatId = $v; return $this; }

    public function getSellerIban(): ?string { return $this->sellerIban; }
    public function setSellerIban(?string $v): self { $this->sellerIban = $v; return $this; }

    public function getBuyerName(): ?string { return $this->buyerName; }
    public function setBuyerName(?string $v): self { $this->buyerName = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): self { $this->currency = $v; return $this; }

    public function getTotalNet(): ?string { return $this->totalNet; }
    public function setTotalNet(?string $v): self { $this->totalNet = $v; return $this; }

    public function getTotalTax(): ?string { return $this->totalTax; }
    public function setTotalTax(?string $v): self { $this->totalTax = $v; return $this; }

    public function getTotalGross(): ?string { return $this->totalGross; }
    public function setTotalGross(?string $v): self { $this->totalGross = $v; return $this; }

    public function getAmountDue(): ?string { return $this->amountDue; }
    public function setAmountDue(?string $v): self { $this->amountDue = $v; return $this; }

    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $v): self { $this->paidAt = $v; return $this; }

    public function getValidationErrors(): ?array { return $this->validationErrors; }
    public function setValidationErrors(?array $v): self { $this->validationErrors = $v; return $this; }

    public function isValidationPassed(): bool { return $this->validationPassed; }
    public function setValidationPassed(bool $v): self { $this->validationPassed = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }

    public function getParsedData(): ?array { return $this->parsedData; }
    public function setParsedData(?array $v): self { $this->parsedData = $v; return $this; }

    public function getFormatLabel(): string
    {
        return match ($this->format) {
            self::FORMAT_XRECHNUNG_UBL => 'XRechnung (UBL)',
            self::FORMAT_XRECHNUNG_CII => 'XRechnung (CII)',
            self::FORMAT_ZUGFERD => 'ZUGFeRD',
            self::FORMAT_FACTUR_X => 'Factur-X',
            default => 'Unbekannt',
        };
    }
}
