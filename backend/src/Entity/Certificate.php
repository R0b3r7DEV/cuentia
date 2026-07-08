<?php

namespace App\Entity;

use App\Repository\CertificateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A low-voltage Electrical Installation Certificate (Certificado de Instalación Eléctrica, CIE),
 * following the structure of the Comunitat Valenciana official model CERTINS E and the REBT
 * (RD 842/2002). Owned by a User (the electrician / empresa instaladora).
 *
 * NOTE: this stores the data and produces a fill-in draft PDF. The official issuance is telematic,
 * with a digital signature, through the GVA sede electrónica — this is an aid, not that submission.
 * ES: Un Certificado de Instalación Eléctrica de baja tensión (CIE), siguiendo la estructura del modelo
 * oficial CERTINS E de la Comunitat Valenciana y el REBT. Es una ayuda de cumplimentación (borrador PDF);
 * la emisión oficial es telemática con firma digital en la sede de la GVA.
 */
#[ORM\Entity(repositoryClass: CertificateRepository::class)]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // --- Installation / Instalación ---
    /** 'nueva' | 'ampliacion' | 'reforma'. */
    #[ORM\Column(length: 12)]
    private string $installationType = 'nueva';

    /** Use/destination: vivienda, local, industrial, garaje, comunes, agricola, otros. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $useType = null;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $locality = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    // --- Titular ---
    #[ORM\Column(length: 150)]
    private string $titularName;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $titularNif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titularAddress = null;

    // --- Empresa instaladora / Instalador ---
    #[ORM\Column(length: 150)]
    private string $companyName;

    /** Registration number + category (IBTB básica / IBTE especialista). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $companyRegNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $companyNif = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $installerName = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $installerLicense = null;

    // --- Technical characteristics / Características técnicas ---
    /** Max admissible power (kW). */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?string $maxPower = null;

    /** Installed / planned power (kW). */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?string $installedPower = null;

    /** Supply voltage (V), e.g. 230 / 400. */
    #[ORM\Column(nullable: true)]
    private ?int $voltage = null;

    /** 'monofasico' | 'trifasico'. */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $supplyType = null;

    /** Earthing scheme (esquema de conexión a tierra), e.g. TT. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $earthingScheme = null;

    #[ORM\Column(nullable: true)]
    private ?int $circuits = null;

    /** Individual derivation section (mm²), material/type as text. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $derivationSection = null;

    /** IGA rated current (A). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $igaCurrent = null;

    /** Differential sensitivity (mA). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $differentialSensitivity = null;

    /** Earth resistance (Ω). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $earthResistance = null;

    /** Earth conductor section (mm²). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $earthConductorSection = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $issuedAt): self { $this->issuedAt = $issuedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getInstallationType(): string { return $this->installationType; }
    public function setInstallationType(string $v): self { $this->installationType = $v; return $this; }

    public function getUseType(): ?string { return $this->useType; }
    public function setUseType(?string $v): self { $this->useType = $v; return $this; }

    public function getAddress(): string { return $this->address; }
    public function setAddress(string $v): self { $this->address = $v; return $this; }

    public function getLocality(): ?string { return $this->locality; }
    public function setLocality(?string $v): self { $this->locality = $v; return $this; }

    public function getProvince(): ?string { return $this->province; }
    public function setProvince(?string $v): self { $this->province = $v; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $v): self { $this->postalCode = $v; return $this; }

    public function getTitularName(): string { return $this->titularName; }
    public function setTitularName(string $v): self { $this->titularName = $v; return $this; }

    public function getTitularNif(): ?string { return $this->titularNif; }
    public function setTitularNif(?string $v): self { $this->titularNif = $v; return $this; }

    public function getTitularAddress(): ?string { return $this->titularAddress; }
    public function setTitularAddress(?string $v): self { $this->titularAddress = $v; return $this; }

    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $v): self { $this->companyName = $v; return $this; }

    public function getCompanyRegNumber(): ?string { return $this->companyRegNumber; }
    public function setCompanyRegNumber(?string $v): self { $this->companyRegNumber = $v; return $this; }

    public function getCompanyNif(): ?string { return $this->companyNif; }
    public function setCompanyNif(?string $v): self { $this->companyNif = $v; return $this; }

    public function getInstallerName(): ?string { return $this->installerName; }
    public function setInstallerName(?string $v): self { $this->installerName = $v; return $this; }

    public function getInstallerLicense(): ?string { return $this->installerLicense; }
    public function setInstallerLicense(?string $v): self { $this->installerLicense = $v; return $this; }

    public function getMaxPower(): ?string { return $this->maxPower; }
    public function setMaxPower(?string $v): self { $this->maxPower = $v; return $this; }

    public function getInstalledPower(): ?string { return $this->installedPower; }
    public function setInstalledPower(?string $v): self { $this->installedPower = $v; return $this; }

    public function getVoltage(): ?int { return $this->voltage; }
    public function setVoltage(?int $v): self { $this->voltage = $v; return $this; }

    public function getSupplyType(): ?string { return $this->supplyType; }
    public function setSupplyType(?string $v): self { $this->supplyType = $v; return $this; }

    public function getEarthingScheme(): ?string { return $this->earthingScheme; }
    public function setEarthingScheme(?string $v): self { $this->earthingScheme = $v; return $this; }

    public function getCircuits(): ?int { return $this->circuits; }
    public function setCircuits(?int $v): self { $this->circuits = $v; return $this; }

    public function getDerivationSection(): ?string { return $this->derivationSection; }
    public function setDerivationSection(?string $v): self { $this->derivationSection = $v; return $this; }

    public function getIgaCurrent(): ?string { return $this->igaCurrent; }
    public function setIgaCurrent(?string $v): self { $this->igaCurrent = $v; return $this; }

    public function getDifferentialSensitivity(): ?string { return $this->differentialSensitivity; }
    public function setDifferentialSensitivity(?string $v): self { $this->differentialSensitivity = $v; return $this; }

    public function getEarthResistance(): ?string { return $this->earthResistance; }
    public function setEarthResistance(?string $v): self { $this->earthResistance = $v; return $this; }

    public function getEarthConductorSection(): ?string { return $this->earthConductorSection; }
    public function setEarthConductorSection(?string $v): self { $this->earthConductorSection = $v; return $this; }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): self { $this->observations = $v; return $this; }
}
