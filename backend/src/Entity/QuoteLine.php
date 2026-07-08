<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A single line of a Quote. Amounts are computed in exact integer cents.
 * ES: Una línea de un presupuesto. Los importes se calculan en céntimos enteros exactos.
 */
#[ORM\Entity]
class QuoteLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quote $quote = null;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $vatRate;

    public function getId(): ?int { return $this->id; }

    public function getQuote(): ?Quote { return $this->quote; }
    public function setQuote(?Quote $quote): self { $this->quote = $quote; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getVatRate(): string { return $this->vatRate; }
    public function setVatRate(string $vatRate): self { $this->vatRate = $vatRate; return $this; }

    /** Line base in integer cents. / Base de línea en céntimos enteros. */
    public function baseCents(): int
    {
        return (int) round((float) $this->unitPrice * 100) * $this->quantity;
    }

    /** Line VAT in integer cents. / IVA de línea en céntimos enteros. */
    public function vatCents(): int
    {
        return (int) round($this->baseCents() * (float) $this->vatRate / 100);
    }
}
