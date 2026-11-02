<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The user's logo for document headers, a sanitised PNG. In its own table on purpose: the User is
 * serialised into the session on every request, and an image there would bloat it (and a blob stream
 * cannot be serialised at all).
 *
 * ES: El logo del usuario para las cabeceras de los documentos, un PNG saneado. En su propia tabla a
 * propósito: el User se serializa en la sesión en cada petición y una imagen ahí la engordaría (y un stream
 * de blob ni siquiera se puede serializar).
 */
#[ORM\Entity]
class UserLogo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'blob')]
    private mixed $data = null;

    #[ORM\Column]
    private int $width = 0;

    #[ORM\Column]
    private int $height = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUser(): User { return $this->user; }

    public function getPng(): string
    {
        if (is_resource($this->data)) {
            rewind($this->data);
            $this->data = stream_get_contents($this->data);
        }

        return (string) $this->data;
    }

    public function setPng(string $png, int $width, int $height): self
    {
        $this->data = $png;
        $this->width = $width;
        $this->height = $height;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
