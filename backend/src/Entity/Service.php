<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A reusable catalog item (a service or product) the freelancer bills for — used to prefill invoice
 * and quote lines. Owned by a User.
 * ES: Un elemento reutilizable del catálogo (un servicio o producto) que el autónomo factura — sirve para
 * rellenar líneas de facturas y presupuestos. Propiedad de un User.
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
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

    /** Default unit price (decimal string — never a float). / Precio unitario por defecto. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice = '0.00';

    /** Default VAT rate, e.g. "21.00". / Tipo de IVA por defecto, p.ej. "21.00". */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $vatRate = '21.00';

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getVatRate(): string { return $this->vatRate; }
    public function setVatRate(string $vatRate): self { $this->vatRate = $vatRate; return $this; }
}
