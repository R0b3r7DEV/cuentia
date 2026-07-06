<?php

namespace App\Service;

use App\Entity\InvoiceRecord;

/**
 * Computes the Verifactu fingerprint (huella) of an invoice record.
 *
 * EN: The fingerprint is a SHA-256 over a fixed, ordered concatenation of the record's fields plus the
 * previous record's fingerprint — exactly the layout the AEAT specifies (Orden HAC/1177/2024, "registro
 * de alta"). Because each hash includes the previous one, the records form a chain: recomputing any
 * record's hash from its stored snapshot reveals whether that record was altered, and comparing a
 * record's `previousHash` to the actual previous hash reveals reordering or deletion.
 *
 * ES: La huella es un SHA-256 sobre una concatenación fija y ordenada de los campos del registro más la
 * huella del registro anterior — el formato que especifica la AEAT (Orden HAC/1177/2024, "registro de
 * alta"). Como cada hash incluye el anterior, los registros forman una cadena: recalcular el hash de un
 * registro desde su copia revela si fue alterado, y comparar su `previousHash` con la huella real del
 * anterior revela reordenaciones o borrados.
 */
class VerifactuHasher
{
    /**
     * Build the canonical string that gets hashed. Amounts are normalized to two decimals so the
     * fingerprint is stable regardless of how a database round-trips a decimal (PostgreSQL returns
     * "1210.00", SQLite may return "1210") — a hash must never depend on storage formatting.
     * ES: Los importes se normalizan a dos decimales para que la huella sea estable sea cual sea el
     * formato con que la BD devuelva un decimal — un hash nunca debe depender del formato de guardado.
     */
    public function canonicalString(InvoiceRecord $r): string
    {
        return implode('&', [
            'IDEmisorFactura=' . $r->getIssuerNif(),
            'NumSerieFactura=' . $r->getFullNumber(),
            'FechaExpedicionFactura=' . $r->getIssueDate(),
            'TipoFactura=' . $r->getInvoiceType(),
            'CuotaTotal=' . $this->money($r->getVatTotal()),
            'ImporteTotal=' . $this->money($r->getTotal()),
            'Huella=' . ($r->getPreviousHash() ?? ''),
            'FechaHoraHusoGenRegistro=' . $r->getGeneratedAt(),
        ]);
    }

    /** Canonical 2-decimal representation of a money amount. / Representación canónica a 2 decimales. */
    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** SHA-256 fingerprint, hex uppercase (as AEAT stores it). / Huella SHA-256, hex mayúsculas. */
    public function fingerprint(InvoiceRecord $r): string
    {
        return strtoupper(hash('sha256', $this->canonicalString($r)));
    }
}
