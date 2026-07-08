<?php

namespace App\Service;

use App\Entity\Certificate;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders an Electrical Installation Certificate (CIE) as a PDF laid out like the Comunitat Valenciana
 * CERTINS E model (REBT, RD 842/2002).
 *
 * NOTE: this is a fill-in draft to help the electrician; the official certificate is issued telematically
 * with a digital signature through the GVA sede electrónica. The PDF states this explicitly.
 * ES: Renderiza un CIE como PDF con la estructura del modelo CERTINS E. Es un borrador de ayuda; el
 * certificado oficial se emite telemáticamente con firma digital en la sede de la GVA.
 */
class CiePdf
{
    private const INSTALLATION_TYPES = ['nueva' => 'Nueva', 'ampliacion' => 'Ampliación', 'reforma' => 'Reforma'];
    private const USES = [
        'vivienda' => 'Vivienda', 'local' => 'Local comercial', 'industrial' => 'Industrial',
        'garaje' => 'Garaje/Aparcamiento', 'comunes' => 'Servicios comunes', 'agricola' => 'Agrícola', 'otros' => 'Otros',
    ];
    private const SUPPLY = ['monofasico' => 'Monofásico', 'trifasico' => 'Trifásico'];

    public function build(Certificate $c): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($c), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function html(Certificate $c): string
    {
        $installation = self::INSTALLATION_TYPES[$c->getInstallationType()] ?? $c->getInstallationType();
        $use = $c->getUseType() !== null ? (self::USES[$c->getUseType()] ?? $c->getUseType()) : '—';
        $supply = $c->getSupplyType() !== null ? (self::SUPPLY[$c->getSupplyType()] ?? $c->getSupplyType()) : '—';
        $emplazamiento = trim($c->getAddress() . ', ' . ($c->getPostalCode() ?: '') . ' ' . ($c->getLocality() ?: '') . ($c->getProvince() ? ' (' . $c->getProvince() . ')' : ''), ', ');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1c2129; font-size: 11px; margin: 0; }
            h1 { color: #2a78d6; font-size: 18px; margin: 0 0 2px; }
            .sub { color: #6b7280; font-size: 10px; margin-bottom: 14px; }
            .sec { background: #eef2f8; color: #2a78d6; font-weight: bold; font-size: 11px; text-transform: uppercase;
                   letter-spacing: .4px; padding: 5px 8px; border-radius: 4px; margin: 14px 0 6px; }
            table.kv { width: 100%; border-collapse: collapse; }
            table.kv td { padding: 3px 8px 3px 0; vertical-align: top; width: 50%; }
            .k { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: .3px; }
            .v { font-size: 12px; border-bottom: 1px solid #e4e7ee; padding-bottom: 2px; min-height: 14px; }
            .decl { margin-top: 18px; font-size: 10px; color: #3a4250; border: 1px solid #d3d8e2; border-radius: 6px; padding: 10px 12px; }
            .sign { margin-top: 26px; width: 100%; }
            .sign td { width: 50%; font-size: 10px; color: #6b7280; padding-top: 34px; border-top: 1px solid #1c2129; }
            .foot { margin-top: 22px; font-size: 8px; color: #9aa2b1; border-top: 1px dashed #d3d8e2; padding-top: 8px; }
          </style></head><body>
            <h1>Certificado de Instalación Eléctrica de Baja Tensión</h1>
            <div class="sub">Modelo CERTINS E · REBT (RD 842/2002) · Comunitat Valenciana &nbsp;·&nbsp; Emitido: ' . $c->getIssuedAt()->format('d/m/Y') . '</div>

            <div class="sec">1 · Datos de la instalación</div>
            <table class="kv">
              <tr><td>' . $this->kv('Tipo', $installation) . '</td><td>' . $this->kv('Uso o destino', $use) . '</td></tr>
              <tr><td colspan="2">' . $this->kv('Emplazamiento', $emplazamiento) . '</td></tr>
            </table>

            <div class="sec">2 · Titular de la instalación</div>
            <table class="kv">
              <tr><td>' . $this->kv('Nombre / Razón social', $c->getTitularName()) . '</td><td>' . $this->kv('NIF / DNI', $c->getTitularNif()) . '</td></tr>
              <tr><td colspan="2">' . $this->kv('Domicilio', $c->getTitularAddress()) . '</td></tr>
            </table>

            <div class="sec">3 · Empresa instaladora e instalador habilitado</div>
            <table class="kv">
              <tr><td>' . $this->kv('Empresa instaladora', $c->getCompanyName()) . '</td><td>' . $this->kv('Nº registro / categoría', $c->getCompanyRegNumber()) . '</td></tr>
              <tr><td>' . $this->kv('NIF empresa', $c->getCompanyNif()) . '</td><td>' . $this->kv('Instalador habilitado', $c->getInstallerName()) . '</td></tr>
              <tr><td>' . $this->kv('Nº carné / habilitación', $c->getInstallerLicense()) . '</td><td></td></tr>
            </table>

            <div class="sec">4 · Características técnicas</div>
            <table class="kv">
              <tr><td>' . $this->kv('Potencia máxima admisible (kW)', $c->getMaxPower()) . '</td><td>' . $this->kv('Potencia instalada / prevista (kW)', $c->getInstalledPower()) . '</td></tr>
              <tr><td>' . $this->kv('Tensión (V)', $c->getVoltage() !== null ? (string) $c->getVoltage() : null) . '</td><td>' . $this->kv('Tipo de suministro', $supply) . '</td></tr>
              <tr><td>' . $this->kv('Esquema de conexión a tierra', $c->getEarthingScheme()) . '</td><td>' . $this->kv('Nº de circuitos', $c->getCircuits() !== null ? (string) $c->getCircuits() : null) . '</td></tr>
              <tr><td>' . $this->kv('Derivación individual (sección)', $c->getDerivationSection()) . '</td><td>' . $this->kv('IGA (A)', $c->getIgaCurrent()) . '</td></tr>
              <tr><td>' . $this->kv('Diferencial (mA)', $c->getDifferentialSensitivity()) . '</td><td>' . $this->kv('Resistencia de tierra (Ω)', $c->getEarthResistance()) . '</td></tr>
              <tr><td>' . $this->kv('Sección conductor de tierra (mm²)', $c->getEarthConductorSection()) . '</td><td></td></tr>
            </table>
            ' . ($c->getObservations() ? '<div class="sec">Observaciones</div><div class="v">' . nl2br($this->e($c->getObservations())) . '</div>' : '') . '

            <div class="decl">El instalador habilitado que suscribe certifica que la instalación descrita se ha
            ejecutado conforme al Reglamento Electrotécnico para Baja Tensión (RD 842/2002) y sus instrucciones
            técnicas complementarias, y que se han realizado las verificaciones y medidas reglamentarias con
            resultado favorable.</div>

            <table class="sign">
              <tr><td>Firma y sello de la empresa instaladora</td><td>Firma del instalador habilitado</td></tr>
            </table>

            <div class="foot">Documento generado por Cuentia como <strong>borrador de ayuda</strong> a la cumplimentación.
            El certificado oficial debe presentarse <strong>telemáticamente y con firma digital</strong> por instalador
            habilitado a través de la sede electrónica de la Generalitat Valenciana (modelo CERTINS E / CERTINS V).</div>
          </body></html>';
    }

    private function kv(string $label, ?string $value): string
    {
        return '<div class="k">' . $this->e($label) . '</div><div class="v">' . ($value !== null && $value !== '' ? $this->e($value) : '&nbsp;') . '</div>';
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
