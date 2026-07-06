<?php

namespace App\Service;

use App\Entity\InvoiceRecord;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Builds the Verifactu QR: a link to the AEAT "ValidarQR" service carrying the invoice's identifying
 * fields, rendered as an SVG (scalable, and needs no GD/imagick extension).
 *
 * EN: The URL layout follows the AEAT QR specification (Orden HAC/1177/2024). We point at the AEAT
 * *pre-production* (test) host because Cuentia is not a registered issuer — the QR is a faithful
 * demonstration, not a live fiscal submission (that is Phase D, out of scope). See ADR 0003.
 * ES: El formato de la URL sigue la especificación de QR de la AEAT. Apuntamos al host de *pruebas* de
 * la AEAT porque Cuentia no es un emisor registrado — el QR es una demostración fiel, no un envío fiscal
 * real (eso es la Fase D, fuera de alcance). Ver ADR 0003.
 */
class VerifactuQr
{
    private const AEAT_VALIDATE_URL = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    /** The URL encoded inside the QR. / La URL codificada dentro del QR. */
    public function url(InvoiceRecord $r): string
    {
        return self::AEAT_VALIDATE_URL . '?' . http_build_query([
            'nif'      => $r->getIssuerNif(),
            'numserie' => $r->getFullNumber(),
            'fecha'    => $r->getIssueDate(),
            'importe'  => number_format((float) $r->getTotal(), 2, '.', ''),
        ]);
    }

    /** The QR as an SVG document (string). / El QR como documento SVG (string). */
    public function svg(InvoiceRecord $r, int $size = 200): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: $this->url($r),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 4,
        ))->build()->getString();
    }
}
