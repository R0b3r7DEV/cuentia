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

    // --- Emplazamiento desglosado, como en el CERTINS (sección C) ---
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $streetType = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $streetName = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $portal = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $bis = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $stair = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $floor = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $door = null;

    /** Población (the CERTINS asks municipio and población separately). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $population = null;

    // --- Suministro (secciones B y C) ---
    /** 'red' | 'generacion_aislada' — see CertinsModel::SOURCES. */
    #[ORM\Column(length: 20, options: ['default' => 'red'])]
    private string $supplySource = 'red';

    /** B · Empresa distribuidora (a la que se conecta la instalación). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $distributor = null;

    /** @var list<string> C · Riesgos específicos (CertinsModel::RISKS keys). */
    #[ORM\Column(type: 'json')]
    private array $specificRisks = [];

    /** @var list<string> E · Usos secundarios (CertinsModel::USES keys). */
    #[ORM\Column(type: 'json')]
    private array $secondaryUses = [];

    /** F · Especificaciones del uso (actividad, superficie, aforo, capacidad…). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $useSpecifications = null;

    // --- Certificación (sección G) ---
    /** 'mtd' | 'proyecto'. */
    #[ORM\Column(length: 10, options: ['default' => 'mtd'])]
    private string $designBasis = 'mtd';

    /** Fecha de firma de la memoria o del proyecto (FAQ 9). */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $designDate = null;

    /** Medida de la resistencia del aislamiento, MΩ. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $insulationResistance = null;

    /** NIF del titular del certificado de cualificación individual (el instalador). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $installerNif = null;

    // --- Titular ---
    #[ORM\Column(length: 150)]
    private string $titularName;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $titularNif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titularAddress = null;

    /**
     * CUPS — the supply point's unique code (on every electricity bill). / Código Universal del Punto de
     * Suministro, el que viene en la factura de la luz.
     */
    #[ORM\Column(length: 22, nullable: true)]
    private ?string $cups = null;

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
        $this->issuedAt = \App\Time\BusinessClock::localToday(); // the date in Spain, not in UTC
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

    public function getCups(): ?string { return $this->cups; }
    public function setCups(?string $v): self { $this->cups = $v; return $this; }

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

    public function getStreetType(): ?string { return $this->streetType; }
    public function setStreetType(?string $v): self { $this->streetType = $v; return $this; }
    public function getStreetName(): ?string { return $this->streetName; }
    public function setStreetName(?string $v): self { $this->streetName = $v; return $this; }
    public function getPortal(): ?string { return $this->portal; }
    public function setPortal(?string $v): self { $this->portal = $v; return $this; }
    public function getBis(): ?string { return $this->bis; }
    public function setBis(?string $v): self { $this->bis = $v; return $this; }
    public function getStair(): ?string { return $this->stair; }
    public function setStair(?string $v): self { $this->stair = $v; return $this; }
    public function getFloor(): ?string { return $this->floor; }
    public function setFloor(?string $v): self { $this->floor = $v; return $this; }
    public function getDoor(): ?string { return $this->door; }
    public function setDoor(?string $v): self { $this->door = $v; return $this; }
    public function getPopulation(): ?string { return $this->population; }
    public function setPopulation(?string $v): self { $this->population = $v; return $this; }
    public function getSupplySource(): string { return $this->supplySource; }
    public function setSupplySource(string $v): self { $this->supplySource = $v; return $this; }
    public function getDistributor(): ?string { return $this->distributor; }
    public function setDistributor(?string $v): self { $this->distributor = $v; return $this; }
    /** @return list<string> */
    public function getSpecificRisks(): array { return $this->specificRisks; }
    /** @param list<string> $v */
    public function setSpecificRisks(array $v): self { $this->specificRisks = $v; return $this; }
    /** @return list<string> */
    public function getSecondaryUses(): array { return $this->secondaryUses; }
    /** @param list<string> $v */
    public function setSecondaryUses(array $v): self { $this->secondaryUses = $v; return $this; }
    public function getUseSpecifications(): ?string { return $this->useSpecifications; }
    public function setUseSpecifications(?string $v): self { $this->useSpecifications = $v; return $this; }
    public function getDesignBasis(): string { return $this->designBasis; }
    public function setDesignBasis(string $v): self { $this->designBasis = $v; return $this; }
    public function getDesignDate(): ?\DateTimeImmutable { return $this->designDate; }
    public function setDesignDate(?\DateTimeImmutable $v): self { $this->designDate = $v; return $this; }
    public function getInsulationResistance(): ?string { return $this->insulationResistance; }
    public function setInsulationResistance(?string $v): self { $this->insulationResistance = $v; return $this; }
    public function getInstallerNif(): ?string { return $this->installerNif; }
    public function setInstallerNif(?string $v): self { $this->installerNif = $v; return $this; }

    /** "Calle Mayor 3, esc. A, 2º B" from the parts, for lists and as the one-line address. */
    public function composedAddress(): string
    {
        $street = trim(implode(' ', array_filter([$this->streetType, $this->streetName, $this->portal, $this->bis])));
        $rest = array_filter([
            $this->stair ? 'esc. ' . $this->stair : null,
            trim(($this->floor ?? '') . ($this->door ? ' ' . $this->door : '')) ?: null,
        ]);

        return trim(implode(', ', array_filter([$street, ...$rest])));
    }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): self { $this->observations = $v; return $this; }
}
