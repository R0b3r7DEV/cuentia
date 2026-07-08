<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceRecord;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders an invoice as a downloadable PDF (HTML → PDF via Dompdf, pure PHP, no gd/imagick).
 * When a Verifactu record exists, its fingerprint and QR are printed on the document.
 * ES: Renderiza una factura como PDF descargable (HTML → PDF con Dompdf, PHP puro). Si existe un
 * registro Verifactu, imprime su huella y su QR en el documento.
 */
class InvoicePdf
{
    public function __construct(private VerifactuQr $qr) {}

    public function build(Invoice $invoice, ?InvoiceRecord $record): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // ships with Dompdf; has € and accents
        $options->set('isRemoteEnabled', true);      // allows the embedded QR (data: URI SVG)

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($invoice, $record), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function html(Invoice $invoice, ?InvoiceRecord $record): string
    {
        $customer = $invoice->getCustomer();
        $issuer = $invoice->getUser();

        $rows = '';
        foreach ($invoice->getLines() as $line) {
            $rows .= sprintf(
                '<tr><td>%s</td><td class="r">%d</td><td class="r">%s</td><td class="r">%s%%</td><td class="r">%s</td></tr>',
                $this->e($line->getDescription()),
                $line->getQuantity(),
                $this->money($line->getUnitPrice()),
                $this->e(rtrim(rtrim($line->getVatRate(), '0'), '.')),
                $this->money((string) ($line->baseCents() / 100)),
            );
        }

        $qrBlock = '';
        if ($record !== null) {
            $svg = base64_encode($this->qr->svg($record, 130));
            $qrBlock = sprintf(
                '<div class="qr"><img src="data:image/svg+xml;base64,%s" width="120" height="120"><div class="fp">
                   <div class="fpl">Huella (SHA-256)</div><div class="hash">%s</div>
                 </div></div>',
                $svg,
                $this->e($record->getHash()),
            );
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1c2129; font-size: 12px; margin: 0; }
            .head { display: flex; justify-content: space-between; border-bottom: 2px solid #2a78d6; padding-bottom: 12px; }
            h1 { color: #2a78d6; font-size: 26px; margin: 0 0 2px; }
            .muted { color: #6b7280; }
            .parties { width: 100%; margin: 22px 0; }
            .parties td { width: 50%; vertical-align: top; padding-right: 16px; }
            .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 4px; }
            table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d3d8e2; padding: 7px 6px; }
            table.lines td { padding: 8px 6px; border-bottom: 1px solid #e4e7ee; }
            .r { text-align: right; }
            .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
            .totals td { padding: 5px 6px; }
            .totals .grand td { border-top: 2px solid #2a78d6; font-weight: bold; font-size: 15px; color: #2a78d6; }
            .foot { margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
            .qr { text-align: center; }
            .fp { margin-top: 4px; max-width: 150px; }
            .fpl { font-size: 9px; text-transform: uppercase; color: #6b7280; }
            .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 7px; word-break: break-all; color: #2a78d6; }
            .note { font-size: 9px; color: #9aa2b1; max-width: 320px; }
          </style></head><body>
            <div class="head">
              <div><h1>Factura</h1><div class="muted">Nº ' . $this->e($invoice->getFullNumber()) . '</div></div>
              <div class="r"><div class="label">Fecha</div>' . $invoice->getIssuedAt()->format('d/m/Y') . '</div>
            </div>

            <table class="parties"><tr>
              <td><div class="label">Emisor</div>' . $this->e((string) $issuer?->getEmail())
                . '<br><span class="muted">NIF: ' . $this->e($record?->getIssuerNif() ?? ($issuer?->getTaxId() ?: '—')) . '</span></td>
              <td><div class="label">Cliente</div>' . $this->e((string) $customer?->getName())
                . '<br><span class="muted">NIF: ' . $this->e((string) $customer?->getTaxId()) . '</span>'
                . ($customer?->getAddress() ? '<br><span class="muted">' . $this->e($customer->getAddress()) . '</span>' : '') . '</td>
            </tr></table>

            <table class="lines">
              <thead><tr><th>Concepto</th><th class="r">Cant.</th><th class="r">Precio</th><th class="r">IVA</th><th class="r">Base</th></tr></thead>
              <tbody>' . $rows . '</tbody>
            </table>

            <table class="totals">
              <tr><td>Base imponible</td><td class="r">' . $this->money($invoice->getBaseTotal()) . '</td></tr>
              <tr><td>IVA</td><td class="r">' . $this->money($invoice->getVatTotal()) . '</td></tr>
              <tr class="grand"><td>Total</td><td class="r">' . $this->money($invoice->getTotal()) . '</td></tr>
            </table>

            <div class="foot">
              <div class="note">' . ($record !== null
                ? 'Factura registrada según el formato Verifactu (Orden HAC/1177/2024). El QR enlaza al servicio de validación de la AEAT (entorno de pruebas — demostración).'
                : '') . '</div>
              ' . $qrBlock . '
            </div>
          </body></html>';
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' €';
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
