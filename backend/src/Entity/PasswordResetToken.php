<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A pending password reset. Only the SHA-256 of the token is stored: the token itself travels once, in the
 * e-mail link, so someone who reads the database still cannot use it. Single use and short-lived.
 *
 * ES: Una recuperación de contraseña pendiente. Solo se guarda el SHA-256 del token: el token viaja una vez,
 * en el enlace del correo, así que quien lea la base de datos no puede usarlo. De un solo uso y caduca pronto.
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_token')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** SHA-256 of the token, hex. / SHA-256 del token, en hexadecimal. */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $tokenHash, \DateTimeImmutable $expiresAt, \DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /** Test helper to age a token. / Ayuda para tests: envejecer un token. */
    public function expireAt(\DateTimeImmutable $at): self
    {
        $this->expiresAt = $at;
        return $this;
    }
}
