<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A photo taken at the site and attached to a work order. Stored as a re-encoded JPEG in the database
 * (bytea), never as the original upload: see ADR 0005.
 *
 * ES: Una foto hecha a pie de obra y adjunta a un parte. Se guarda como JPEG re-codificado en la base de datos
 * (bytea), nunca el fichero original subido: ver ADR 0005.
 */
#[ORM\Entity]
class WorkOrderPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkOrder::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkOrder $workOrder = null;

    #[ORM\Column(type: 'blob')]
    private mixed $data = null;

    #[ORM\Column]
    private int $width = 0;

    #[ORM\Column]
    private int $height = 0;

    /** Size in bytes of the stored JPEG. / Tamaño en bytes del JPEG guardado. */
    #[ORM\Column]
    private int $size = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getWorkOrder(): ?WorkOrder { return $this->workOrder; }
    public function setWorkOrder(?WorkOrder $workOrder): self { $this->workOrder = $workOrder; return $this; }

    public function getData(): string
    {
        if (is_resource($this->data)) {
            rewind($this->data);
            $this->data = stream_get_contents($this->data);
        }

        return (string) $this->data;
    }

    public function setImage(string $jpeg, int $width, int $height): self
    {
        $this->data = $jpeg;
        $this->width = $width;
        $this->height = $height;
        $this->size = strlen($jpeg);
        return $this;
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getSize(): int { return $this->size; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
