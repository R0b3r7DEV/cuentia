<?php

namespace App\Service;

use App\Entity\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a quote (presupuesto) as a downloadable PDF via Dompdf. No QR/fingerprint — a quote is a
 * non-fiscal offer, not an invoice.
 * ES: Renderiza un presupuesto como PDF descargable con Dompdf. Sin QR/huella — un presupuesto es una
 * oferta no fiscal, no una factura.
 */
class QuotePdf
{
    public function build(Quote $quote): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($quote), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function html(Quote $quote): string
    {
        $customer = $quote->getCustomer();
        $issuer = $quote->getUser();

        $rows = '';
        foreach ($quote->getLines() as $line) {
            $rows .= sprintf(
                '<tr><td>%s</td><td class="r">%d</td><td class="r">%s</td><td class="r">%s%%</td><td class="r">%s</td></tr>',
                $this->e($line->getDescription()),
                $line->getQuantity(),
                $this->money($line->getUnitPrice()),
                $this->e(rtrim(rtrim($line->getVatRate(), '0'), '.')),
                $this->money((string) ($line->baseCents() / 100)),
            );
        }

        $validUntil = $quote->getValidUntil() !== null
            ? '<div class="r"><div class="label">Válido hasta</div>' . $quote->getValidUntil()->format('d/m/Y') . '</div>'
            : '';

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
            .note { margin-top: 30px; font-size: 9px; color: #9aa2b1; }
          </style></head><body>
            <div class="head">
              <div><h1>Presupuesto</h1><div class="muted">Nº ' . $this->e($quote->getFullNumber()) . '</div></div>
              <div class="r"><div class="label">Fecha</div>' . $quote->getIssuedAt()->format('d/m/Y') . $validUntil . '</div>
            </div>

            <table class="parties"><tr>
              <td><div class="label">Emisor</div>' . $this->e((string) $issuer?->getEmail())
                . '<br><span class="muted">NIF: ' . $this->e($issuer?->getTaxId() ?: '—') . '</span></td>
              <td><div class="label">Cliente</div>' . $this->e((string) $customer?->getName())
                . '<br><span class="muted">NIF: ' . $this->e((string) $customer?->getTaxId()) . '</span>'
                . ($customer?->getAddress() ? '<br><span class="muted">' . $this->e($customer->getAddress()) . '</span>' : '') . '</td>
            </tr></table>

            <table class="lines">
              <thead><tr><th>Concepto</th><th class="r">Cant.</th><th class="r">Precio</th><th class="r">IVA</th><th class="r">Base</th></tr></thead>
              <tbody>' . $rows . '</tbody>
            </table>

            <table class="totals">
              <tr><td>Base imponible</td><td class="r">' . $this->money($quote->getBaseTotal()) . '</td></tr>
              <tr><td>IVA</td><td class="r">' . $this->money($quote->getVatTotal()) . '</td></tr>
              <tr class="grand"><td>Total</td><td class="r">' . $this->money($quote->getTotal()) . '</td></tr>
            </table>

            <div class="note">Presupuesto sin validez fiscal. Al aceptarse se emitirá la factura correspondiente.</div>
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
