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

    /**
     * Billing mode: 'standard' issues ordinary RD 1619/2012 invoices (no QR / XML / Verifactu legend);
     * 'verifactu' shows the full anti-fraud demo artefacts. The internal hash chain runs in BOTH modes.
     * ES: Modo de facturación: 'standard' emite facturas ordinarias (sin QR/XML/leyenda Verifactu);
     * 'verifactu' muestra los artefactos de demostración. La cadena de hash interna vive en AMBOS modos.
     */
    #[ORM\Column(length: 12, options: ['default' => 'standard'])]
    private string $billingMode = 'standard';

    /** Issuer legal/business name (razón social) — RD 1619/2012 art. 6 requires it on an invoice. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $businessName = null;

    /** Issuer fiscal address (domicilio fiscal) — RD 1619/2012 art. 6. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fiscalAddress = null;

    /**
     * Default labour rate in euros/hour, pre-filled in every new work order.
     * ES: Precio por hora por defecto, en euros, que se rellena en cada parte nuevo.
     */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $defaultLaborRate = null;

    /**
     * Number in the GVA register of installation companies (for a self-employed electrician, the company is
     * themselves). Printed on every CIE. / Nº de inscripción en el registro de empresas instaladoras.
     */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $installerRegNumber = null;

    /** Contact phone printed on documents. / Teléfono de contacto en los documentos. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    /** IBAN printed on invoices as the payment method (normalised, no spaces). / IBAN para cobrar. */
    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    // Per-user API credentials (BYOK), stored ENCRYPTED (never plaintext). / Credenciales cifradas.
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anthropicKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gocardlessSecretId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gocardlessSecretKey = null;

    /**
     * When the user proved the e-mail is theirs (the link in the confirmation e-mail, or a password reset).
     * Null: not confirmed yet, so logging in is refused. Accounts from before this existed were marked confirmed.
     * ES: Cuándo demostró que el email es suyo. Null: sin confirmar, no puede entrar.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /** SHA-256 of the pending confirmation token (the token itself only travels in the e-mail). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function isEmailVerified(): bool { return $this->emailVerifiedAt !== null; }
    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }
    public function setEmailVerifiedAt(?\DateTimeImmutable $at): self { $this->emailVerifiedAt = $at; return $this; }
    public function getEmailVerificationHash(): ?string { return $this->emailVerificationHash; }
    public function getEmailVerificationExpiresAt(): ?\DateTimeImmutable { return $this->emailVerificationExpiresAt; }
    public function setEmailVerification(?string $hash, ?\DateTimeImmutable $expiresAt): self
    {
        $this->emailVerificationHash = $hash;
        $this->emailVerificationExpiresAt = $expiresAt;
        return $this;
    }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getTaxId(): ?string { return $this->taxId; }
    public function setTaxId(?string $taxId): self { $this->taxId = $taxId; return $this; }

    public function getBillingMode(): string { return $this->billingMode; }
    public function setBillingMode(string $mode): self
    {
        $this->billingMode = in_array($mode, ['standard', 'verifactu'], true) ? $mode : 'standard';
        return $this;
    }

    public function getBusinessName(): ?string { return $this->businessName; }
    public function setBusinessName(?string $v): self { $this->businessName = $v; return $this; }

    public function getFiscalAddress(): ?string { return $this->fiscalAddress; }
    public function setFiscalAddress(?string $v): self { $this->fiscalAddress = $v; return $this; }

    public function getDefaultLaborRate(): ?string { return $this->defaultLaborRate; }
    public function setDefaultLaborRate(?string $v): self { $this->defaultLaborRate = $v; return $this; }

    public function getInstallerRegNumber(): ?string { return $this->installerRegNumber; }
    public function setInstallerRegNumber(?string $v): self { $this->installerRegNumber = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }

    public function getIban(): ?string { return $this->iban; }
    public function setIban(?string $v): self { $this->iban = $v; return $this; }

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
