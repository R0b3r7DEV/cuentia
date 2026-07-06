<?php

namespace App\Service;

use App\Entity\InvoiceRecord;

/**
 * Serializes an invoice record as a Verifactu "RegistroAlta" XML document.
 *
 * EN: This mirrors the field structure of the AEAT "registro de alta" (IDFactura, totals, chaining and
 * fingerprint) as a clean, well-formed XML built with DOMDocument. It is a faithful representation of the
 * record, not the full SOAP `SuministroLR` envelope the AEAT web service expects — real submission is
 * Phase D (out of scope). See ADR 0003.
 * ES: Refleja la estructura de campos del "registro de alta" de la AEAT (IDFactura, totales, encadenamiento
 * y huella) como un XML limpio y bien formado construido con DOMDocument. Es una representación fiel del
 * registro, no el sobre SOAP `SuministroLR` completo que espera el servicio web de la AEAT — el envío real
 * es la Fase D (fuera de alcance).
 */
class VerifactuXml
{
    private const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd';

    public function build(InvoiceRecord $r): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS, 'RegistroAlta');
        $dom->appendChild($root);

        $id = $dom->createElement('IDFactura');
        $id->appendChild($dom->createElement('IDEmisorFactura', $r->getIssuerNif()));
        $id->appendChild($dom->createElement('NumSerieFactura', $r->getFullNumber()));
        $id->appendChild($dom->createElement('FechaExpedicionFactura', $r->getIssueDate()));
        $root->appendChild($id);

        $root->appendChild($dom->createElement('TipoFactura', $r->getInvoiceType()));
        $root->appendChild($dom->createElement('CuotaTotal', $this->money($r->getVatTotal())));
        $root->appendChild($dom->createElement('ImporteTotal', $this->money($r->getTotal())));

        // Chaining: the first record declares it; the rest reference the previous fingerprint.
        $chain = $dom->createElement('Encadenamiento');
        if ($r->getPreviousHash() === null) {
            $chain->appendChild($dom->createElement('PrimerRegistro', 'S'));
        } else {
            $prev = $dom->createElement('RegistroAnterior');
            $prev->appendChild($dom->createElement('Huella', $r->getPreviousHash()));
            $chain->appendChild($prev);
        }
        $root->appendChild($chain);

        $root->appendChild($dom->createElement('TipoHuella', '01')); // 01 = SHA-256
        $root->appendChild($dom->createElement('Huella', $r->getHash()));
        $root->appendChild($dom->createElement('FechaHoraHusoGenRegistro', $r->getGeneratedAt()));

        return $dom->saveXML();
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
