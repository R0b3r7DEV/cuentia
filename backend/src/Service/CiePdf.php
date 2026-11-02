<?php

namespace App\Service;

use App\Cie\CertinsModel;
use App\Cie\CertinsSheet;
use App\Entity\Certificate;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The CIE as a DATA SHEET in the order of the CERTINS E model (sections A–G), plus the technical data for the
 * Technical Design Report. It is not the certificate: since 2023 the certificate is filled in "online" in the
 * web form of GVA procedure 440 and no other document may be filed as the certificate (GVA low-voltage FAQ,
 * question 13). The sheet says so, and is laid out to copy the data field by field.
 *
 * ES: El CIE como HOJA DE DATOS en el orden del modelo CERTINS E (secciones A–G), más los datos técnicos para
 * la MTD. No es el certificado: desde 2023 se rellena "online" en el formulario web del trámite 440 y no se
 * debe presentar otro documento como certificado. La hoja lo dice y está pensada para copiar campo a campo.
 */
class CiePdf
{
    public function build(Certificate $c): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($c), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** The HTML before rendering; public so tests can assert on it. */
    public function html(Certificate $c): string
    {
        $sections = '';
        foreach (CertinsSheet::sections($c) as $s) {
            $rows = '';
            foreach ($s['rows'] as $r) {
                $value = $r['value'] !== '' ? $this->e($r['value']) : ($r['required'] ? '<span class="missing">FALTA</span>' : '—');
                $rows .= '<tr><td class="k">' . $this->e($r['label']) . '</td><td class="v">' . $value . '</td></tr>';
            }
            $sections .= '<table class="sec' . ($s['code'] === 'MTD' ? ' mtd' : '') . '"><tr><th colspan="2"><span class="code">'
                . $this->e($s['code']) . '</span>' . $this->e($s['title']) . '</th></tr>' . $rows . '</table>';
        }

        $missing = CertinsSheet::missing($c);
        $missingBox = $missing === [] ? '' : '<div class="warn"><strong>Faltan datos obligatorios:</strong> '
            . $this->e(implode(' · ', array_column($missing, 'label'))) . '</div>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1c2129; font-size: 10.5px; margin: 0; }
            h1 { color: #2a78d6; font-size: 16px; margin: 0 0 2px; }
            .sub { color: #6b7280; font-size: 9.5px; margin-bottom: 8px; }
            .notice { border: 1px solid #2a78d6; background: #eef4fc; border-radius: 5px; padding: 7px 10px; font-size: 9.5px; margin-bottom: 8px; }
            .warn { border: 1px solid #d09853; background: #fdf3e3; border-radius: 5px; padding: 6px 10px; font-size: 9.5px; margin-bottom: 8px; }
            table.sec { width: 100%; border-collapse: collapse; margin-bottom: 7px; page-break-inside: avoid; }
            table.sec th { text-align: left; background: #1c2129; color: #fff; font-size: 10px; padding: 4px 7px; }
            table.sec.mtd th { background: #6b7280; }
            .code { display: inline-block; background: #fff; color: #1c2129; font-weight: bold; padding: 0 5px; margin-right: 7px; border-radius: 2px; }
            td.k { width: 46%; color: #3a4250; padding: 3px 7px; border-bottom: 1px solid #e4e7ee; vertical-align: top; }
            td.v { font-weight: bold; padding: 3px 7px; border-bottom: 1px solid #e4e7ee; }
            .missing { color: #b3261e; }
            .foot { margin-top: 6px; font-size: 8.5px; color: #6b7280; }
          </style></head><body>
            <h1>Hoja de datos del certificado de instalación eléctrica (CERTINS E)</h1>
            <div class="sub">Instalación receptora específica de baja tensión · Comunitat Valenciana · REBT (RD 842/2002) · Generada con Cuentia el '
                . $c->getIssuedAt()->format('d/m/Y') . '</div>
            <div class="notice"><strong>Esto NO es el certificado.</strong> El certificado se rellena en el formulario web del trámite
            «Instalaciones eléctricas de BAJA TENSIÓN que requieran MEMORIA TÉCNICA DE DISEÑO» (sede.gva.es, código 440) y lo firma el
            instalador habilitado desde la Carpeta Ciudadana. Esta hoja recoge los datos en el mismo orden (secciones A a G) para copiarlos.</div>
            ' . $missingBox . $sections . '
            <div class="foot">Estructura y etiquetas según el modelo ' . $this->e(CertinsModel::VERSION)
                . '. Si el formulario web pide algún dato distinto, manda lo que diga el formulario.</div>
          </body></html>';
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
