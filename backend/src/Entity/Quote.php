<?php

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A quote (presupuesto): a non-fiscal offer to a customer. Unlike an Invoice it carries no Verifactu
 * hash chain; when accepted it can be **converted into a real invoice**.
 * ES: Un presupuesto: una oferta no fiscal a un cliente. A diferencia de una Invoice no lleva cadena de
 * hash Verifactu; cuando se acepta puede **convertirse en una factura real**.
 */
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\Column(length: 20)]
    private string $series;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $baseTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $vatTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    /** 'draft' | 'sent' | 'accepted' | 'rejected' | 'converted'. */
    #[ORM\Column(length: 12)]
    private string $status = 'draft';

    /** The invoice this quote was converted into (once accepted). / La factura en que se convirtió. */
    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Invoice $convertedInvoice = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, QuoteLine> */
    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteLine::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->issuedAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): self { $this->customer = $customer; return $this; }

    public function getSeries(): string { return $this->series; }
    public function setSeries(string $series): self { $this->series = $series; return $this; }

    public function getNumber(): int { return $this->number; }
    public function setNumber(int $number): self { $this->number = $number; return $this; }

    public function getFullNumber(): string { return $this->series . '/' . $this->number; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $issuedAt): self { $this->issuedAt = $issuedAt; return $this; }

    public function getValidUntil(): ?\DateTimeImmutable { return $this->validUntil; }
    public function setValidUntil(?\DateTimeImmutable $validUntil): self { $this->validUntil = $validUntil; return $this; }

    public function getBaseTotal(): string { return $this->baseTotal; }
    public function setBaseTotal(string $baseTotal): self { $this->baseTotal = $baseTotal; return $this; }

    public function getVatTotal(): string { return $this->vatTotal; }
    public function setVatTotal(string $vatTotal): self { $this->vatTotal = $vatTotal; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getConvertedInvoice(): ?Invoice { return $this->convertedInvoice; }
    public function setConvertedInvoice(?Invoice $invoice): self { $this->convertedInvoice = $invoice; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, QuoteLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(QuoteLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuote($this);
        }
        return $this;
    }
}
