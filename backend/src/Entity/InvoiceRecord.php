<?php

namespace App\Entity;

use App\Repository\InvoiceRecordRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Verifactu "registro de alta": the tamper-evident record generated when an invoice is issued.
 * It stores a snapshot of the exact fields that feed the fingerprint (huella) plus the SHA-256 hash
 * of the previous record, forming a chain — altering any past invoice breaks every hash after it.
 *
 * ES: Un "registro de alta" Verifactu: el registro inalterable que se genera al emitir una factura.
 * Guarda una copia de los campos exactos que alimentan la huella más el hash SHA-256 del registro
 * anterior, formando una cadena — alterar cualquier factura pasada rompe todos los hashes posteriores.
 */
#[ORM\Entity(repositoryClass: InvoiceRecordRepository::class)]
class InvoiceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Invoice $invoice = null;

    /** The issuer — chains are per user. / El emisor — las cadenas son por usuario. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // --- fingerprint inputs (snapshot at issue time) / entradas de la huella (copia al emitir) ---

    #[ORM\Column(length: 20)]
    private string $issuerNif;

    /** e.g. "2026/7". */
    #[ORM\Column(length: 41)]
    private string $fullNumber;

    /** dd-mm-yyyy, as AEAT expects. */
    #[ORM\Column(length: 10)]
    private string $issueDate;

    /** Invoice type per AEAT (F1 = ordinary invoice). / Tipo de factura (F1 = ordinaria). */
    #[ORM\Column(length: 4)]
    private string $invoiceType = 'F1';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $vatTotal;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total;

    /** ISO-8601 with timezone — FechaHoraHusoGenRegistro. */
    #[ORM\Column(length: 25)]
    private string $generatedAt;

    // --- the chain / la cadena ---

    /** SHA-256 (hex) of the previous record; null for the first in the chain. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousHash = null;

    /** This record's SHA-256 fingerprint (hex, uppercase). */
    #[ORM\Column(length: 64)]
    private string $hash;

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): self { $this->invoice = $invoice; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getIssuerNif(): string { return $this->issuerNif; }
    public function setIssuerNif(string $issuerNif): self { $this->issuerNif = $issuerNif; return $this; }

    public function getFullNumber(): string { return $this->fullNumber; }
    public function setFullNumber(string $fullNumber): self { $this->fullNumber = $fullNumber; return $this; }

    public function getIssueDate(): string { return $this->issueDate; }
    public function setIssueDate(string $issueDate): self { $this->issueDate = $issueDate; return $this; }

    public function getInvoiceType(): string { return $this->invoiceType; }
    public function setInvoiceType(string $invoiceType): self { $this->invoiceType = $invoiceType; return $this; }

    public function getVatTotal(): string { return $this->vatTotal; }
    public function setVatTotal(string $vatTotal): self { $this->vatTotal = $vatTotal; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }

    public function getGeneratedAt(): string { return $this->generatedAt; }
    public function setGeneratedAt(string $generatedAt): self { $this->generatedAt = $generatedAt; return $this; }

    public function getPreviousHash(): ?string { return $this->previousHash; }
    public function setPreviousHash(?string $previousHash): self { $this->previousHash = $previousHash; return $this; }

    public function getHash(): string { return $this->hash; }
    public function setHash(string $hash): self { $this->hash = $hash; return $this; }
}
