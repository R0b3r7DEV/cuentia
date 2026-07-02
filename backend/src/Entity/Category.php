<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A category to classify a transaction (e.g. "Software", "Suppliers", "Client income").
 * ES: Una categoría para clasificar un movimiento (p.ej. "Software", "Proveedores", "Ingresos").
 *
 * Doctrine maps this PHP class to a database table via the #[ORM\...] attributes.
 * ES: Doctrine mapea esta clase PHP a una tabla de la base de datos con los atributos #[ORM\...].
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]              // auto-increment primary key / clave primaria autoincremental
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    /** 'income' or 'expense' / 'income' (ingreso) o 'expense' (gasto) */
    #[ORM\Column(length: 10)]
    private string $kind;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }
}
