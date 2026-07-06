<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A customer a freelancer issues invoices to. Owned by a User.
 * ES: Un cliente al que un autónomo emite facturas. Propiedad de un User.
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 150)]
    private string $name;

    /** Tax ID (NIF/CIF). / Identificación fiscal (NIF/CIF). */
    #[ORM\Column(length: 20)]
    private string $taxId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getTaxId(): string { return $this->taxId; }
    public function setTaxId(string $taxId): self { $this->taxId = $taxId; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
}
