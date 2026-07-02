<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single bank movement (an income or an expense).
 * ES: Un movimiento bancario (un ingreso o un gasto).
 */
#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Value date of the movement / Fecha valor del movimiento */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $bookedAt;

    /** Raw text from the bank / Texto en bruto del banco */
    #[ORM\Column(type: 'text')]
    private string $description;

    /**
     * Signed amount: negative = expense, positive = income.
     * IMPORTANT: money uses `decimal`, and Doctrine returns it as a PHP *string*
     * to preserve exact precision — never use float for money.
     * ES: Importe con signo: negativo = gasto, positivo = ingreso. El dinero usa
     * `decimal` y Doctrine lo devuelve como *string* de PHP para no perder precisión;
     * nunca usar float para dinero.
     */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    /** The assigned category (null until categorized). / La categoría asignada (nula hasta categorizar). */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    /** How the category was set: 'ai' | 'rule' | 'manual'. / Cómo se asignó la categoría. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $categorySource = null;

    /** VAT rate, e.g. "21.00" (feeds the VAT panel). / Tipo de IVA, p.ej. "21.00". */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $vatRate = null;

    /** Origin of the import: 'csv' | 'norma43' | 'openbanking'. / Origen de la importación. */
    #[ORM\Column(length: 20)]
    private string $importedFrom = 'csv';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBookedAt(): \DateTimeImmutable { return $this->bookedAt; }
    public function setBookedAt(\DateTimeImmutable $bookedAt): self { $this->bookedAt = $bookedAt; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self { $this->category = $category; return $this; }

    public function getCategorySource(): ?string { return $this->categorySource; }
    public function setCategorySource(?string $categorySource): self { $this->categorySource = $categorySource; return $this; }

    public function getVatRate(): ?string { return $this->vatRate; }
    public function setVatRate(?string $vatRate): self { $this->vatRate = $vatRate; return $this; }

    public function getImportedFrom(): string { return $this->importedFrom; }
    public function setImportedFrom(string $importedFrom): self { $this->importedFrom = $importedFrom; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
