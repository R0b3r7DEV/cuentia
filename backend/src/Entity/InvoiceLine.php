<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A line of an invoice: description, quantity, unit price and VAT rate.
 * ES: Una línea de factura: descripción, cantidad, precio unitario y tipo de IVA.
 */
#[ORM\Entity]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255)]
    private string $description;

    /** Whole units (kept integer to avoid fractional-cent rounding). / Unidades enteras. */
    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    /** VAT rate %, e.g. "21.00". / Tipo de IVA %, p.ej. "21.00". */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $vatRate;

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): self { $this->invoice = $invoice; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getVatRate(): string { return $this->vatRate; }
    public function setVatRate(string $vatRate): self { $this->vatRate = $vatRate; return $this; }

    /** Line base in integer cents: unitPrice(cents) × quantity. */
    public function baseCents(): int
    {
        return (int) round((float) $this->unitPrice * 100) * $this->quantity;
    }

    /** Line VAT in integer cents: base × rate. */
    public function vatCents(): int
    {
        return (int) round($this->baseCents() * (float) $this->vatRate / 100);
    }
}
