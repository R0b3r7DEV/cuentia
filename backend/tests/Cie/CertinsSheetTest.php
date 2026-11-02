<?php

namespace App\Tests\Cie;

use App\Cie\CertinsModel;
use App\Cie\CertinsSheet;
use App\Entity\Certificate;
use App\Service\CiePdf;
use PHPUnit\Framework\TestCase;

class CertinsSheetTest extends TestCase
{
    private function certificate(): Certificate
    {
        return (new Certificate())
            ->setTitularName('María López García')->setTitularNif('12345678Z')
            ->setSupplySource('red')->setDistributor('i-DE Redes Eléctricas Inteligentes')
            ->setStreetType('Calle')->setStreetName('del Mar')->setPortal('3')->setFloor('2º')->setDoor('B')
            ->setAddress('')->setLocality('Castelló de la Plana')->setProvince('Castellón')->setPostalCode('12001')
            ->setInstalledPower('5.750')->setVoltage(230)->setInstallationType('reforma')->setCups('ES0021000012345678LB')
            ->setSpecificRisks(['humedo'])
            ->setCompanyName('José Pérez García')->setCompanyNif('11111111H')->setCompanyRegNumber('CV-12345')
            ->setInstallerName('José Pérez García')->setInstallerNif('11111111H')->setInstallerLicense('BT-4567')
            ->setUseType('vivienda')->setSecondaryUses(['alumbrado_exterior'])
            ->setDesignBasis('mtd')->setDesignDate(new \DateTimeImmutable('2026-10-30'))
            ->setEarthResistance('18')->setInsulationResistance('250')
            ->setIgaCurrent('25');
    }

    public function testSectionsFollowTheModelOrderAndLabels(): void
    {
        $sections = CertinsSheet::sections($this->certificate());
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'MTD'], array_column($sections, 'code'));

        $c = array_column($sections[2]['rows'], 'value', 'label');
        self::assertSame('Calle', $c['Tipo vía']);
        self::assertSame('del Mar', $c['Nombre vía']);
        self::assertSame('5,75', $c['Potencia instalada (kW)']);
        self::assertSame('Modificación', $c['Nueva / Modificación'], 'a "reforma" is a modification in the model');
        self::assertSame('Local húmedo', $c['Riesgos específicos de la instalación']);

        $e = array_column($sections[4]['rows'], 'value', 'label');
        self::assertSame('Vivienda', $e['Uso principal']);
        self::assertSame('Alumbrado exterior', $e['Usos secundarios']);
        self::assertSame('30/10/2026', array_column($sections[6]['rows'], 'value', 'key')['designDate']);
        self::assertSame('25', array_column($sections[7]['rows'], 'value', 'key')['igaCurrent'], 'the IGA goes to the MTD block');
    }

    public function testNothingMissingWhenComplete(): void
    {
        self::assertSame([], CertinsSheet::missing($this->certificate()));
    }

    public function testMissingNamesTheSectionAndTheModelLabel(): void
    {
        $c = $this->certificate()->setCups(null)->setInsulationResistance(null)->setSpecificRisks([]);
        self::assertSame(
            ['C · CUPS', 'C · Riesgos específicos de la instalación', 'G · Medida de la resistencia del aislamiento (Megaohmios)'],
            array_column(CertinsSheet::missing($c), 'label'),
        );
    }

    /** Without the distribution network (isolated generation), CUPS and distributor are not required (FAQ 26). */
    public function testIsolatedGenerationNeedsNoCupsNorDistributor(): void
    {
        $c = $this->certificate()->setSupplySource('generacion_aislada')->setCups(null)->setDistributor(null);
        self::assertSame([], CertinsSheet::missing($c));
    }

    public function testComposedAddress(): void
    {
        self::assertSame('Calle del Mar 3, 2º B', $this->certificate()->composedAddress());
        self::assertSame('Avenida Sol 9, esc. A, 1º', (new Certificate())->setStreetType('Avenida')->setStreetName('Sol')->setPortal('9')->setStair('A')->setFloor('1º')->composedAddress());
    }

    public function testPickKeepsOnlyModelKeysInModelOrder(): void
    {
        self::assertSame(['mojado', 'humedo'], CertinsModel::pick(['mojado', 'humedo', 'inventado', 'humedo'], CertinsModel::RISKS), 'unknown and repeated keys dropped');
        self::assertSame(['mojado', 'humedo'], CertinsModel::pick(['humedo', 'mojado'], CertinsModel::RISKS), 'model order');
        self::assertSame([], CertinsModel::pick('no-es-lista', CertinsModel::RISKS));
    }

    /** The PDF is a data sheet and says it is not the certificate (FAQ 13: the certificate is the web form). */
    public function testPdfIsADataSheetNotTheCertificate(): void
    {
        $html = (new CiePdf())->html($this->certificate()->setCups(null));
        self::assertStringContainsString('Esto NO es el certificado', $html);
        self::assertStringContainsString('código 440', $html);
        self::assertStringContainsString('Faltan datos obligatorios', $html);
        self::assertStringContainsString('C · CUPS', $html);
        self::assertStringNotContainsString('AutoFirma', $html);
        self::assertStringStartsWith('%PDF', (new CiePdf())->build($this->certificate()));
    }
}
