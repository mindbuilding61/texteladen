<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OutgoingInvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OutgoingInvoiceRepository::class)]
#[ORM\Table(name: 'outgoing_invoice')]
class OutgoingInvoice
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Customer $customer = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $servicePeriodStart = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $servicePeriodEnd = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $introText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $outroText = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $buyerReference = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $leitwegId = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $totalNet = '0.00';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: OutgoingInvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->issueDate = new \DateTimeImmutable('today');
        $this->dueDate = $this->issueDate->modify('+14 days');
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function setInvoiceNumber(?string $v): self { $this->invoiceNumber = $v; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $v): self { $this->customer = $v; return $this; }

    public function getIssueDate(): \DateTimeImmutable { return $this->issueDate; }
    public function setIssueDate(\DateTimeImmutable $v): self { $this->issueDate = $v; return $this; }

    public function getDueDate(): \DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(\DateTimeImmutable $v): self { $this->dueDate = $v; return $this; }

    public function getServicePeriodStart(): ?\DateTimeImmutable { return $this->servicePeriodStart; }
    public function setServicePeriodStart(?\DateTimeImmutable $v): self { $this->servicePeriodStart = $v; return $this; }

    public function getServicePeriodEnd(): ?\DateTimeImmutable { return $this->servicePeriodEnd; }
    public function setServicePeriodEnd(?\DateTimeImmutable $v): self { $this->servicePeriodEnd = $v; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): self { $this->currency = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }

    public function getIntroText(): ?string { return $this->introText; }
    public function setIntroText(?string $v): self { $this->introText = $v; return $this; }

    public function getOutroText(): ?string { return $this->outroText; }
    public function setOutroText(?string $v): self { $this->outroText = $v; return $this; }

    public function getBuyerReference(): ?string { return $this->buyerReference; }
    public function setBuyerReference(?string $v): self { $this->buyerReference = $v; return $this; }

    public function getLeitwegId(): ?string { return $this->leitwegId; }
    public function setLeitwegId(?string $v): self { $this->leitwegId = $v; return $this; }

    public function getTotalNet(): string { return $this->totalNet; }
    public function setTotalNet(string $v): self { $this->totalNet = $v; return $this; }

    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $v): self { $this->paidAt = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, OutgoingInvoiceLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(OutgoingInvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }
        return $this;
    }

    public function removeLine(OutgoingInvoiceLine $line): self
    {
        if ($this->lines->removeElement($line) && $line->getInvoice() === $this) {
            $line->setInvoice(null);
        }
        return $this;
    }

    public function recalculateTotals(): void
    {
        $sum = '0.00';
        $pos = 1;
        foreach ($this->lines as $line) {
            $line->setPosition($pos++);
            $line->recalculateLineTotal();
            $sum = bcadd($sum, $line->getLineNet(), 2);
        }
        $this->totalNet = $sum;
        $this->touch();
    }
}
