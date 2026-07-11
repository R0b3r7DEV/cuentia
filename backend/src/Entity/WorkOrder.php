<?php

namespace App\Entity;

use App\Repository\WorkOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A work order (parte de trabajo): the everyday job of an electrician — a call-out or repair, filled in at
 * the site. Carries the materials used (lines from the service catalog) and labour hours; when finished it
 * can be **converted into a real invoice** (idempotently), reusing the quote→invoice pattern.
 *
 * ES: Un parte de trabajo: el día a día del electricista — un aviso o reparación, rellenado a pie de obra.
 * Lleva los materiales usados (líneas del catálogo) y las horas de mano de obra; cuando se termina puede
 * **convertirse en factura real** (de forma idempotente), reusando el patrón presupuesto→factura.
 */
#[ORM\Entity(repositoryClass: WorkOrderRepository::class)]
class WorkOrder
{
    /** The lifecycle: pendiente → en_curso → terminado → facturado. */
    public const STATUSES = ['pendiente', 'en_curso', 'terminado', 'facturado'];

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

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 12)]
    private string $status = 'pendiente';

    /** When the job is scheduled / took place. / Cuándo se programa o se hizo el trabajo. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /** Labour hours (decimal, e.g. "1.50"). / Horas de mano de obra. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $laborHours = '0.00';

    /** Labour rate in euros/hour. / Precio de la mano de obra en euros/hora. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $laborRate = '0.00';

    /** VAT rate applied to the labour line. / Tipo de IVA de la línea de mano de obra. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $laborVatRate = '21.00';

    /** The invoice this order was converted into. / La factura en que se convirtió. */
    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Invoice $convertedInvoice = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, WorkOrderLine> */
    #[ORM\OneToMany(mappedBy: 'workOrder', targetEntity: WorkOrderLine::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): self { $this->customer = $customer; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'pendiente';
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeImmutable $v): self { $this->scheduledAt = $v; return $this; }

    public function getLaborHours(): string { return $this->laborHours; }
    public function setLaborHours(string $v): self { $this->laborHours = $v; return $this; }

    public function getLaborRate(): string { return $this->laborRate; }
    public function setLaborRate(string $v): self { $this->laborRate = $v; return $this; }

    public function getLaborVatRate(): string { return $this->laborVatRate; }
    public function setLaborVatRate(string $v): self { $this->laborVatRate = $v; return $this; }

    public function getConvertedInvoice(): ?Invoice { return $this->convertedInvoice; }
    public function setConvertedInvoice(?Invoice $invoice): self { $this->convertedInvoice = $invoice; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Labour amount in integer cents (hours × rate). / Importe de mano de obra en céntimos. */
    public function laborBaseCents(): int
    {
        return (int) round((float) $this->laborHours * (float) $this->laborRate * 100);
    }

    /** @return Collection<int, WorkOrderLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(WorkOrderLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setWorkOrder($this);
        }
        return $this;
    }
}
