<?php

namespace App\Entity;

use App\Repository\InstallationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A saved electrical-installation design (the input to the ITC-BT-25 calculator). The computed result
 * is derived on demand from this input, so it never goes stale.
 * ES: Un diseño de instalación eléctrica guardado (la entrada de la calculadora ITC-BT-25). El resultado
 * se calcula bajo demanda desde esta entrada, así que nunca queda obsoleto.
 */
#[ORM\Entity(repositoryClass: InstallationRepository::class)]
class Installation
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

    /** 'auto' | 'basico' | 'elevado'. */
    #[ORM\Column(length: 10)]
    private string $grade = 'auto';

    /** 'monofasico' | 'trifasico'. */
    #[ORM\Column(length: 12)]
    private string $supplyType = 'monofasico';

    /** @var array<string,bool> */
    #[ORM\Column(type: 'json')]
    private array $loads = [];

    /** @var array<int,array{type:string,area:float}> */
    #[ORM\Column(type: 'json')]
    private array $rooms = [];

    /** 2D floor-plan layout (rooms rectangles + placed devices + panel), in metres. / Planta 2D. */
    #[ORM\Column(type: 'json')]
    private array $layout = [];

    /**
     * Optional scanned floor plan drawn under the canvas: { src (data URI), x, y, w, h, opacity }.
     * x/y/w/h are in METRES, so once calibrated the image shares the canvas' metric space.
     * ES: Plano escaneado opcional bajo el lienzo; x/y/w/h en METROS, así que una vez calibrado comparte
     * el espacio métrico del lienzo. Nullable a propósito: añadir una columna NOT NULL a una tabla con
     * filas rompe la migración en producción.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $background = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getGrade(): string { return $this->grade; }
    public function setGrade(string $grade): self { $this->grade = $grade; return $this; }

    public function getSupplyType(): string { return $this->supplyType; }
    public function setSupplyType(string $supplyType): self { $this->supplyType = $supplyType; return $this; }

    /** @return array<string,bool> */
    public function getLoads(): array { return $this->loads; }
    /** @param array<string,bool> $loads */
    public function setLoads(array $loads): self { $this->loads = $loads; return $this; }

    /** @return array<int,array{type:string,area:float}> */
    public function getRooms(): array { return $this->rooms; }
    /** @param array<int,array{type:string,area:float}> $rooms */
    public function setRooms(array $rooms): self { $this->rooms = $rooms; return $this; }

    public function getLayout(): array { return $this->layout; }
    public function setLayout(array $layout): self { $this->layout = $layout; return $this; }

    public function getBackground(): ?array { return $this->background; }
    public function setBackground(?array $background): self { $this->background = $background; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** The input payload for the calculator. / La entrada para la calculadora. */
    public function toInput(): array
    {
        return [
            'grade' => $this->grade,
            'supplyType' => $this->supplyType,
            'loads' => $this->loads,
            'rooms' => $this->rooms,
        ];
    }
}
