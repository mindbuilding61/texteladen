<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id = 1;

    #[ORM\Column(length: 255)]
    private string $companyName = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255)]
    private string $street = '';

    #[ORM\Column(length: 20)]
    private string $postalCode = '';

    #[ORM\Column(length: 255)]
    private string $city = '';

    #[ORM\Column(length: 2)]
    private string $country = 'DE';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(type: 'boolean')]
    private bool $kleinunternehmer = true;

    #[ORM\Column(type: 'text')]
    private string $kleinunternehmerNote = 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.';

    #[ORM\Column(length: 10)]
    private string $invoiceNumberPrefix = 'RE-';

    #[ORM\Column(type: 'integer')]
    private int $nextInvoiceNumber = 1;

    #[ORM\Column(type: 'integer')]
    private int $defaultPaymentTermDays = 14;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $logoFilename = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $v): self { $this->companyName = $v; return $this; }

    public function getContactName(): ?string { return $this->contactName; }
    public function setContactName(?string $v): self { $this->contactName = $v; return $this; }

    public function getStreet(): string { return $this->street; }
    public function setStreet(string $v): self { $this->street = $v; return $this; }

    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $v): self { $this->postalCode = $v; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $v): self { $this->city = $v; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $v): self { $this->country = $v; return $this; }

    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function setTaxNumber(?string $v): self { $this->taxNumber = $v; return $this; }

    public function getVatId(): ?string { return $this->vatId; }
    public function setVatId(?string $v): self { $this->vatId = $v; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): self { $this->email = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }

    public function getIban(): ?string { return $this->iban; }
    public function setIban(?string $v): self { $this->iban = $v; return $this; }

    public function getBic(): ?string { return $this->bic; }
    public function setBic(?string $v): self { $this->bic = $v; return $this; }

    public function getBankName(): ?string { return $this->bankName; }
    public function setBankName(?string $v): self { $this->bankName = $v; return $this; }

    public function isKleinunternehmer(): bool { return $this->kleinunternehmer; }
    public function setKleinunternehmer(bool $v): self { $this->kleinunternehmer = $v; return $this; }

    public function getKleinunternehmerNote(): string { return $this->kleinunternehmerNote; }
    public function setKleinunternehmerNote(string $v): self { $this->kleinunternehmerNote = $v; return $this; }

    public function getInvoiceNumberPrefix(): string { return $this->invoiceNumberPrefix; }
    public function setInvoiceNumberPrefix(string $v): self { $this->invoiceNumberPrefix = $v; return $this; }

    public function getNextInvoiceNumber(): int { return $this->nextInvoiceNumber; }
    public function setNextInvoiceNumber(int $v): self { $this->nextInvoiceNumber = $v; return $this; }

    public function getDefaultPaymentTermDays(): int { return $this->defaultPaymentTermDays; }
    public function setDefaultPaymentTermDays(int $v): self { $this->defaultPaymentTermDays = $v; return $this; }

    public function getLogoFilename(): ?string { return $this->logoFilename; }
    public function setLogoFilename(?string $v): self { $this->logoFilename = $v; return $this; }

    public function getAddressLines(): array
    {
        return array_values(array_filter([
            $this->companyName,
            $this->contactName,
            $this->street,
            trim($this->postalCode.' '.$this->city),
        ]));
    }
}
