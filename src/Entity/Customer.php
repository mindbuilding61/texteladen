<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customer')]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $street = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private string $postalCode = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $city = '';

    #[ORM\Column(length: 2)]
    private string $country = 'DE';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(mode: 'html5', message: 'Keine gültige E-Mail-Adresse.')]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatId = null;

    #[ORM\Column(length: 20, nullable: true, options: ['comment' => 'Leitweg-ID für B2G'])]
    private ?string $leitwegId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $buyerReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

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

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): self { $this->email = $v; return $this; }

    public function getVatId(): ?string { return $this->vatId; }
    public function setVatId(?string $v): self { $this->vatId = $v; return $this; }

    public function getLeitwegId(): ?string { return $this->leitwegId; }
    public function setLeitwegId(?string $v): self { $this->leitwegId = $v; return $this; }

    public function getBuyerReference(): ?string { return $this->buyerReference; }
    public function setBuyerReference(?string $v): self { $this->buyerReference = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : (string) $this->id;
    }
}
