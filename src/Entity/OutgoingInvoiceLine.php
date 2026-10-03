<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OutgoingInvoiceLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OutgoingInvoiceLineRepository::class)]
#[ORM\Table(name: 'outgoing_invoice_line')]
class OutgoingInvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OutgoingInvoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?OutgoingInvoice $invoice = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 1;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    #[Assert\Positive]
    private string $quantity = '1.0000';

    #[ORM\Column(length: 10)]
    private string $unit = 'C62';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    #[Assert\PositiveOrZero]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $lineNet = '0.00';

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?OutgoingInvoice { return $this->invoice; }
    public function setInvoice(?OutgoingInvoice $v): self { $this->invoice = $v; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $v): self { $this->position = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }

    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(string $v): self { $this->quantity = $v; return $this; }

    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $v): self { $this->unit = $v; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $v): self { $this->unitPrice = $v; return $this; }

    public function getLineNet(): string { return $this->lineNet; }
    public function setLineNet(string $v): self { $this->lineNet = $v; return $this; }

    public function recalculateLineTotal(): void
    {
        $this->lineNet = bcadd(bcmul($this->quantity, $this->unitPrice, 6), '0', 2);
    }
}
