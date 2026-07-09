<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An application user. Owns their transactions; can only see their own data.
 * ES: Un usuario de la aplicación. Es dueño de sus movimientos; solo ve sus propios datos.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /** @var string[] */
    #[ORM\Column]
    private array $roles = [];

    /** Hashed password. / Contraseña cifrada (hash). */
    #[ORM\Column]
    private string $password;

    /** Issuer tax id (NIF) — needed as the IDEmisor in the Verifactu fingerprint. / NIF emisor. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $taxId = null;

    // Per-user API credentials (BYOK), stored ENCRYPTED (never plaintext). / Credenciales cifradas.
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anthropicKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gocardlessSecretId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gocardlessSecretKey = null;

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getTaxId(): ?string { return $this->taxId; }
    public function setTaxId(?string $taxId): self { $this->taxId = $taxId; return $this; }

    public function getAnthropicKey(): ?string { return $this->anthropicKey; }
    public function setAnthropicKey(?string $v): self { $this->anthropicKey = $v; return $this; }

    public function getGocardlessSecretId(): ?string { return $this->gocardlessSecretId; }
    public function setGocardlessSecretId(?string $v): self { $this->gocardlessSecretId = $v; return $this; }

    public function getGocardlessSecretKey(): ?string { return $this->gocardlessSecretKey; }
    public function setGocardlessSecretKey(?string $v): self { $this->gocardlessSecretKey = $v; return $this; }

    /** The unique identifier for the security system (we use the email). */
    public function getUserIdentifier(): string { return $this->email; }

    /** @return string[] */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER'; // every user has at least ROLE_USER
        return array_unique($roles);
    }

    /** @param string[] $roles */
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    /** No sensitive temporary data to erase. */
    public function eraseCredentials(): void {}
}
