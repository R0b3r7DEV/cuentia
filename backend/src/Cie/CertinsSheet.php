<?php

namespace App\Cie;

use App\Entity\Certificate;

/**
 * A certificate laid out as the CERTINS E model: sections A–G with the model's own labels, in its order, plus
 * the technical data that belongs to the Technical Design Report (MTD) rather than to the certificate. One
 * source for both the PDF data sheet and the "Para la GVA" screen, so they never disagree.
 *
 * ES: Un certificado ordenado como el modelo CERTINS E: secciones A–G con las etiquetas del modelo y en su
 * orden, más los datos técnicos que van en la Memoria Técnica de Diseño (MTD) y no en el certificado. Una sola
 * fuente para la hoja de datos en PDF y la pantalla «Para la GVA».
 */
final class CertinsSheet
{
    /**
     * @return list<array{code: string, title: string, rows: list<array{key: string, label: string, value: string, required: bool}>}>
     */
    public static function sections(Certificate $c): array
    {
        $required = CertinsModel::required($c->getSupplySource());
        $row = static fn (string $key, string $label, ?string $value): array => [
            'key' => $key, 'label' => $label, 'value' => trim((string) $value), 'required' => in_array($key, $required, true),
        ];
        $labels = static fn (array $keys, array $catalog): string => implode('; ', array_map(static fn (string $k): string => $catalog[$k] ?? $k, $keys));

        return [
            ['code' => 'A', 'title' => 'TITULAR', 'rows' => [
                $row('titularName', 'Apellidos y nombre o razón social', $c->getTitularName()),
                $row('titularNif', 'NIF', $c->getTitularNif()),
            ]],
            ['code' => 'B', 'title' => 'EMPRESA DISTRIBUIDORA (a la que se conecta la instalación)', 'rows' => [
                $row('supplySource', 'Fuente de suministro', CertinsModel::SOURCES[$c->getSupplySource()] ?? $c->getSupplySource()),
                $row('distributor', 'Empresa distribuidora', $c->getDistributor()),
            ]],
            ['code' => 'C', 'title' => 'CARACTERÍSTICAS DE LA INSTALACIÓN', 'rows' => [
                $row('streetType', 'Tipo vía', $c->getStreetType()),
                $row('streetName', 'Nombre vía', $c->getStreetName() ?: ($c->getAddress() ?? '')),
                $row('portal', 'Portal', $c->getPortal()),
                $row('bis', 'Bis', $c->getBis()),
                $row('stair', 'Esc', $c->getStair()),
                $row('floor', 'Piso', $c->getFloor()),
                $row('door', 'Puerta', $c->getDoor()),
                $row('province', 'Provincia', $c->getProvince()),
                $row('locality', 'Municipio', $c->getLocality()),
                $row('population', 'Población', $c->getPopulation()),
                $row('postalCode', 'CP', $c->getPostalCode()),
                $row('installedPower', 'Potencia instalada (kW)', self::num($c->getInstalledPower())),
                $row('voltage', 'Tensión (V)', $c->getVoltage() !== null ? (string) $c->getVoltage() : null),
                $row('installationType', 'Nueva / Modificación', CertinsModel::actuation($c->getInstallationType())),
                $row('cups', 'CUPS', $c->getCups()),
                $row('specificRisks', 'Riesgos específicos de la instalación', $labels($c->getSpecificRisks(), CertinsModel::RISKS) ?: null),
            ]],
            ['code' => 'D', 'title' => 'EMPRESA INSTALADORA', 'rows' => [
                $row('companyName', 'Apellidos y nombre o razón social', $c->getCompanyName()),
                $row('companyNif', 'NIF', $c->getCompanyNif()),
                $row('companyRegNumber', 'Nº de inscripción en el registro de empresas instaladoras', $c->getCompanyRegNumber()),
                $row('installerName', 'Nombre del instalador (titular del CCI)', $c->getInstallerName()),
                $row('installerNif', 'NIF del instalador', $c->getInstallerNif()),
                $row('installerLicense', 'Nº del certificado de cualificación individual (CCI)', $c->getInstallerLicense()),
            ]],
            ['code' => 'E', 'title' => 'USO DE LA INSTALACIÓN', 'rows' => [
                $row('useType', 'Uso principal', $c->getUseType() !== null ? (CertinsModel::USES[$c->getUseType()] ?? $c->getUseType()) : null),
                $row('secondaryUses', 'Usos secundarios', $labels($c->getSecondaryUses(), CertinsModel::USES) ?: null),
            ]],
            ['code' => 'F', 'title' => 'ESPECIFICACIONES DEL USO', 'rows' => [
                $row('useSpecifications', 'Especificaciones del uso', $c->getUseSpecifications()),
            ]],
            ['code' => 'G', 'title' => 'CERTIFICACIÓN DE LA EMPRESA INSTALADORA', 'rows' => [
                $row('designBasis', 'Instalación ejecutada de acuerdo con', CertinsModel::DESIGN_BASIS[$c->getDesignBasis()] ?? $c->getDesignBasis()),
                $row('designDate', 'Fecha de firma de la memoria / del proyecto', $c->getDesignDate()?->format('d/m/Y')),
                $row('earthResistance', 'Resistencia de la tierra de protección (ohmios)', $c->getEarthResistance()),
                $row('insulationResistance', 'Medida de la resistencia del aislamiento (Megaohmios)', $c->getInsulationResistance()),
            ]],
            ['code' => 'MTD', 'title' => 'DATOS TÉCNICOS PARA LA MEMORIA TÉCNICA DE DISEÑO (no van en el certificado)', 'rows' => [
                $row('maxPower', 'Potencia máxima admisible (kW)', self::num($c->getMaxPower())),
                $row('supplyType', 'Suministro', ['monofasico' => 'Monofásico', 'trifasico' => 'Trifásico'][$c->getSupplyType() ?? ''] ?? null),
                $row('earthingScheme', 'Esquema de conexión a tierra', $c->getEarthingScheme()),
                $row('circuits', 'Nº de circuitos', $c->getCircuits() !== null ? (string) $c->getCircuits() : null),
                $row('derivationSection', 'Sección de la derivación individual', $c->getDerivationSection()),
                $row('igaCurrent', 'Interruptor general automático (A)', $c->getIgaCurrent()),
                $row('differentialSensitivity', 'Sensibilidad del diferencial (mA)', $c->getDifferentialSensitivity()),
                $row('earthConductorSection', 'Sección del conductor de protección (mm²)', $c->getEarthConductorSection()),
                $row('observations', 'Observaciones', $c->getObservations()),
            ]],
        ];
    }

    /**
     * Required fields still empty, as "A · NIF" — what would stop the web form.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function missing(Certificate $c): array
    {
        $missing = [];
        foreach (self::sections($c) as $section) {
            foreach ($section['rows'] as $r) {
                if ($r['required'] && $r['value'] === '') {
                    $missing[] = ['key' => $r['key'], 'label' => $section['code'] . ' · ' . $r['label']];
                }
            }
        }

        return $missing;
    }

    /** "5.750" → "5,75". */
    private static function num(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        if (str_contains($v, '.')) {
            $v = rtrim(rtrim($v, '0'), '.');
        }

        return str_replace('.', ',', $v);
    }
}
