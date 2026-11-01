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

    /**
     * Allowed manual moves. 'facturado' is NOT here on purpose: it is only reached through convert(), so an
     * order can never be marked invoiced without a real invoice behind it.
     * ES: Cambios manuales permitidos. 'facturado' NO está a propósito: solo se llega con convert(), así un
     * parte nunca queda como facturado sin una factura real detrás.
     */
    public const TRANSITIONS = [
        'pendiente' => ['en_curso'],
        'en_curso'  => ['pendiente', 'terminado'],
        'terminado' => ['en_curso'],
        'facturado' => [],
    ];

    /** States in which the order can still be edited. / Estados en los que el parte aún se puede editar. */
    public const EDITABLE = ['pendiente', 'en_curso'];

    /** Most photos one order can hold. / Máximo de fotos por parte. */
    public const MAX_PHOTOS = 6;

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

    /** The client's signature, a sanitised PNG. / La firma del cliente, un PNG saneado. */
    #[ORM\Column(type: 'blob', nullable: true)]
    private mixed $signaturePng = null;

    /** Who signed. / Quién firmó. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $signedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    /**
     * Finished without a signature because the client was not there.
     * ES: Terminado sin firma porque el cliente no estaba.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $signatureWaived = false;

    /** @var Collection<int, WorkOrderLine> */
    #[ORM\OneToMany(mappedBy: 'workOrder', targetEntity: WorkOrderLine::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    /** @var Collection<int, WorkOrderPhoto> */
    #[ORM\OneToMany(mappedBy: 'workOrder', targetEntity: WorkOrderPhoto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->photos = new ArrayCollection();
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

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /**
     * Labour amount in integer cents (hours × rate), with no float in between: both values are 2-decimal
     * strings, so they are read as hundredths and multiplied as integers, rounding half up once.
     * ES: Importe de mano de obra en céntimos (horas × precio), sin float por medio: los dos son strings con
     * 2 decimales, se leen como centésimas, se multiplican como enteros y se redondea una sola vez.
     */
    public function laborBaseCents(): int
    {
        $product = self::hundredths($this->laborHours) * self::hundredths($this->laborRate);

        return intdiv($product + 50, 100);
    }

    /** Labour VAT in integer cents, rounded per line like InvoiceLine. / IVA de la mano de obra en céntimos. */
    public function laborVatCents(): int
    {
        return intdiv($this->laborBaseCents() * self::hundredths($this->laborVatRate) + 5000, 10000);
    }

    /**
     * Totals in integer cents: materials + labour, VAT rounded per line (same rule the invoice applies, so
     * the order and the invoice it becomes always agree).
     * ES: Totales en céntimos: materiales + mano de obra, IVA redondeado por línea (la misma regla que la
     * factura, así el parte y la factura en que se convierte siempre coinciden).
     *
     * @return array{base:int, vat:int, total:int}
     */
    public function totalsCents(): array
    {
        $base = $this->laborBaseCents();
        $vat = $this->laborVatCents();
        foreach ($this->lines as $line) {
            $base += $line->baseCents();
            $vat += $line->vatCents();
        }

        return ['base' => $base, 'vat' => $vat, 'total' => $base + $vat];
    }

    /** "1.50" → 150. Non-negative decimal strings with up to 2 decimals, the format setters store. */
    private static function hundredths(string $decimal): int
    {
        [$int, $frac] = array_pad(explode('.', ltrim($decimal, '+')), 2, '');

        return (int) $int * 100 + (int) str_pad(substr($frac, 0, 2), 2, '0');
    }

    public function getSignaturePng(): ?string
    {
        if (is_resource($this->signaturePng)) {
            rewind($this->signaturePng);
            $this->signaturePng = stream_get_contents($this->signaturePng);
        }

        return $this->signaturePng;
    }

    public function getSignedBy(): ?string { return $this->signedBy; }
    public function getSignedAt(): ?\DateTimeImmutable { return $this->signedAt; }
    public function isSignatureWaived(): bool { return $this->signatureWaived; }

    public function hasSignature(): bool { return $this->signedAt !== null; }

    public function sign(string $png, string $signedBy): self
    {
        $this->signaturePng = $png;
        $this->signedBy = $signedBy;
        $this->signedAt = new \DateTimeImmutable();
        $this->signatureWaived = false;
        return $this;
    }

    /** Drop the signature (and any waiver): the work it covered changed. / Quita la firma y la dispensa. */
    public function clearSignature(): self
    {
        $this->signaturePng = null;
        $this->signedBy = null;
        $this->signedAt = null;
        $this->signatureWaived = false;
        return $this;
    }

    public function waiveSignature(): self { $this->signatureWaived = true; return $this; }

    /** @return Collection<int, WorkOrderPhoto> */
    public function getPhotos(): Collection { return $this->photos; }

    public function addPhoto(WorkOrderPhoto $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setWorkOrder($this);
        }
        return $this;
    }

    public function removePhoto(WorkOrderPhoto $photo): self
    {
        $this->photos->removeElement($photo);
        return $this;
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
