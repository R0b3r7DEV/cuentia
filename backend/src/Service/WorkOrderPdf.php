<?php

namespace App\Service;

use App\Entity\WorkOrder;
use App\Time\BusinessClock;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a work order (parte de trabajo) as a PDF via Dompdf: the job, materials, labour, totals, photo
 * thumbnails and the client's signature. Same pattern as QuotePdf. Not a fiscal document: the invoice is
 * issued separately when the order is converted.
 *
 * Remote loading stays OFF (unlike InvoicePdf): photos and signature are embedded as data: URIs, so Dompdf
 * never needs to fetch anything, and user text can never make it request a URL (SSRF).
 *
 * ES: Renderiza un parte de trabajo como PDF con Dompdf: el trabajo, materiales, mano de obra, totales,
 * miniaturas de las fotos y la firma del cliente. Mismo patrón que QuotePdf. No es un documento fiscal: la
 * factura se emite aparte al convertir el parte.
 * La carga remota sigue DESACTIVADA (a diferencia de InvoicePdf): fotos y firma van incrustadas como data:,
 * así Dompdf nunca tiene que descargar nada y ningún texto del usuario puede hacerle pedir una URL (SSRF).
 */
class WorkOrderPdf
{
    private const STATUS_LABELS = [
        'pendiente' => 'Pendiente',
        'en_curso'  => 'En curso',
        'terminado' => 'Terminado',
        'facturado' => 'Facturado',
    ];

    public function __construct(private ?BusinessClock $clock = null, private ?IssuerProfile $profile = null)
    {
        $this->clock ??= new BusinessClock();
    }

    public function build(WorkOrder $wo): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($wo), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(WorkOrder $wo): string
    {
        return sprintf('parte-%d.pdf', $wo->getId());
    }

    /** The HTML before rendering; public so tests can assert on it, like InvoicePdf::html(). */
    public function html(WorkOrder $wo): string
    {
        $customer = $wo->getCustomer();
        $issuer = $wo->getUser();
        $totals = $wo->totalsCents();

        $rows = '';
        foreach ($wo->getLines() as $line) {
            $rows .= sprintf(
                '<tr><td>%s</td><td class="r">%d</td><td class="r">%s</td><td class="r">%s%%</td><td class="r">%s</td></tr>',
                $this->e($line->getDescription()),
                $line->getQuantity(),
                $this->euros((int) round((float) $line->getUnitPrice() * 100)),
                $this->e($this->rate($line->getVatRate())),
                $this->euros($line->baseCents()),
            );
        }
        if ($wo->laborBaseCents() > 0) {
            $rows .= sprintf(
                '<tr><td>Mano de obra</td><td class="r">%s h</td><td class="r">%s/h</td><td class="r">%s%%</td><td class="r">%s</td></tr>',
                $this->e($this->rate($wo->getLaborHours())),
                $this->euros((int) round((float) $wo->getLaborRate() * 100)),
                $this->e($this->rate($wo->getLaborVatRate())),
                $this->euros($wo->laborBaseCents()),
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted">Sin materiales ni mano de obra.</td></tr>';
        }

        $photos = '';
        foreach ($wo->getPhotos() as $photo) {
            $photos .= '<img class="photo" src="data:image/jpeg;base64,' . base64_encode($photo->getData()) . '">';
        }
        $photos = $photos !== '' ? '<div class="label section">Fotos</div><div>' . $photos . '</div>' : '';

        if ($wo->hasSignature()) {
            $signature = '<img class="signature" src="data:image/png;base64,' . base64_encode((string) $wo->getSignaturePng()) . '">'
                . '<div class="muted">Firmado por ' . $this->e((string) $wo->getSignedBy())
                . ' el ' . $this->clock->format($wo->getSignedAt(), 'd/m/Y \a \l\a\s H:i') . '</div>';
        } elseif ($wo->isSignatureWaived()) {
            $signature = '<div class="muted">Terminado sin firma: el cliente no estaba presente.</div>';
        } else {
            $signature = '<div class="muted">Pendiente de firma.</div>';
        }

        $issuerName = IssuerProfile::name($issuer);
        // The date in Spain: a job done at 00:30 on the 2nd is not dated the 1st. / La fecha en España.
        $date = $this->clock->format($wo->getScheduledAt() ?? $wo->getCreatedAt(), 'd/m/Y');
        $invoice = $wo->getConvertedInvoice() !== null
            ? '<div class="muted">Facturado en la factura ' . $this->e($wo->getConvertedInvoice()->getFullNumber()) . '</div>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1c2129; font-size: 12px; margin: 0; }
            .head { border-bottom: 2px solid #2a78d6; padding-bottom: 12px; }
            .head td { vertical-align: top; }
            h1 { color: #2a78d6; font-size: 26px; margin: 0 0 2px; }
            h2 { font-size: 15px; margin: 18px 0 4px; }
            .muted { color: #6b7280; }
            .parties { width: 100%; margin: 22px 0 6px; }
            .parties td { width: 50%; vertical-align: top; padding-right: 16px; }
            .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 4px; }
            .section { margin-top: 22px; }
            table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d3d8e2; padding: 7px 6px; }
            table.lines td { padding: 8px 6px; border-bottom: 1px solid #e4e7ee; }
            .r { text-align: right; }
            .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
            .totals td { padding: 5px 6px; }
            .totals .grand td { border-top: 2px solid #2a78d6; font-weight: bold; font-size: 15px; color: #2a78d6; }
            .photo { width: 160px; height: 120px; margin: 0 8px 8px 0; border: 1px solid #d3d8e2; }
            .signature { width: 240px; border-bottom: 1px solid #1c2129; margin-top: 6px; }
            .note { margin-top: 30px; font-size: 9px; color: #9aa2b1; }
            .logo { max-height: 60px; max-width: 220px; margin-bottom: 8px; }
          </style></head><body>
            <table class="head" width="100%"><tr>
              <td>' . ($this->profile?->logoTag($issuer) ?? '') . '<h1>Parte de trabajo</h1><div class="muted">Nº ' . (int) $wo->getId() . ' · '
                . self::STATUS_LABELS[$wo->getStatus()] . '</div>' . $invoice . '</td>
              <td class="r"><div class="label">Fecha</div>' . $date . '</td>
            </tr></table>

            <table class="parties"><tr>
              <td><div class="label">Electricista</div>' . $this->e($issuerName)
                . '<br><span class="muted">NIF: ' . $this->e($issuer?->getTaxId() ?: '—') . '</span>'
                . ($issuer?->getFiscalAddress() ? '<br><span class="muted">' . $this->e($issuer->getFiscalAddress()) . '</span>' : '')
                . (IssuerProfile::phoneLine($issuer) ? '<br><span class="muted">' . IssuerProfile::phoneLine($issuer) . '</span>' : '') . '</td>
              <td><div class="label">Cliente</div>' . $this->e((string) $customer?->getName())
                . '<br><span class="muted">NIF: ' . $this->e((string) $customer?->getTaxId()) . '</span>'
                . ($customer?->getAddress() ? '<br><span class="muted">' . $this->e($customer->getAddress()) . '</span>' : '') . '</td>
            </tr></table>

            <h2>' . $this->e($wo->getTitle()) . '</h2>'
            . ($wo->getDescription() ? '<div>' . nl2br($this->e($wo->getDescription())) . '</div>' : '') . '

            <table class="lines">
              <thead><tr><th>Concepto</th><th class="r">Cant.</th><th class="r">Precio</th><th class="r">IVA</th><th class="r">Base</th></tr></thead>
              <tbody>' . $rows . '</tbody>
            </table>

            <table class="totals">
              <tr><td>Base imponible</td><td class="r">' . $this->euros($totals['base']) . '</td></tr>
              <tr><td>IVA</td><td class="r">' . $this->euros($totals['vat']) . '</td></tr>
              <tr class="grand"><td>Total</td><td class="r">' . $this->euros($totals['total']) . '</td></tr>
            </table>

            ' . $photos . '

            <div class="label section">Conformidad del cliente</div>
            ' . $signature . '

            <div class="note">Parte de trabajo sin validez fiscal. La factura se emite por separado.</div>
          </body></html>';
    }

    /** Integer cents → "1.234,56 €", no float involved. */
    private function euros(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . number_format(intdiv($cents, 100), 0, ',', '.') . ',' . sprintf('%02d', $cents % 100) . ' €';
    }

    /** "21.00" → "21", "1.50" → "1,5". */
    private function rate(string $v): string
    {
        return str_replace('.', ',', rtrim(rtrim($v, '0'), '.')) ?: '0';
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
