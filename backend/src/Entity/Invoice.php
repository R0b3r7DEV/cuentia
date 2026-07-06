<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An invoice issued by a User to a Customer. Totals are computed from its lines.
 * ES: Una factura emitida por un User a un Customer. Los totales se calculan de sus líneas.
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
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

    /** Invoice series (e.g. "2026"). / Serie de la factura (p.ej. "2026"). */
    #[ORM\Column(length: 20)]
    private string $series;

    /** Correlative number within the series. / Número correlativo dentro de la serie. */
    #[ORM\Column]
    private int $number;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issuedAt;

    /** decimal strings — money is never a float. / strings decimales — el dinero nunca es float. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $baseTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $vatTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    /** 'issued' | 'void'. / 'issued' (emitida) | 'void' (anulada). */
    #[ORM\Column(length: 10)]
    private string $status = 'issued';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceLine::class, cascade: ['persist'], orphanRemoval: true)]
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

    /** Human-readable invoice number, e.g. "2026/7". / Número legible, p.ej. "2026/7". */
    public function getFullNumber(): string { return $this->series . '/' . $this->number; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $issuedAt): self { $this->issuedAt = $issuedAt; return $this; }

    public function getBaseTotal(): string { return $this->baseTotal; }
    public function setBaseTotal(string $baseTotal): self { $this->baseTotal = $baseTotal; return $this; }

    public function getVatTotal(): string { return $this->vatTotal; }
    public function setVatTotal(string $vatTotal): self { $this->vatTotal = $vatTotal; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(InvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }
        return $this;
    }
}
