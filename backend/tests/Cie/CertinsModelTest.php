<?php

namespace App\Tests\Cie;

use App\Cie\CertinsModel;
use App\Cie\CertinsSheet;
use App\Entity\Certificate;
use PHPUnit\Framework\TestCase;

class CertinsModelTest extends TestCase
{
    public function testTheDistributorAndCupsAreRequiredOnlyFromTheGrid(): void
    {
        self::assertContains('cups', CertinsModel::required('red'));
        self::assertNotContains('cups', CertinsModel::required('generacion_aislada'));
        self::assertNotContains('distributor', CertinsModel::required('generacion_aislada'));
    }

    public function testMissingCountsTheOldOneLineAddressAsTheStreetName(): void
    {
        $form = ['supplySource' => 'generacion_aislada', 'address' => 'C/ Mayor 3', 'streetName' => '', 'specificRisks' => []];

        $missing = CertinsModel::missing($form);

        self::assertNotContains('streetName', $missing);
        self::assertContains('specificRisks', $missing, 'an empty list is missing');
        self::assertContains('streetName', CertinsModel::missing(['address' => '']));
    }

    /** The GVA sheet and the review agree on what is missing: they share one list. */
    public function testTheSheetAndTheReviewAgree(): void
    {
        $c = (new Certificate())->setTitularName('María')->setCompanyName('José')->setAddress('C/ Mayor 3');

        $fromSheet = array_column(CertinsSheet::missing($c), 'key');
        $fromForm = CertinsModel::missing([
            'titularName' => 'María', 'companyName' => 'José', 'address' => 'C/ Mayor 3', 'supplySource' => $c->getSupplySource(),
            'installationType' => $c->getInstallationType(), 'designBasis' => $c->getDesignBasis(), 'specificRisks' => [],
        ]);

        sort($fromSheet);
        sort($fromForm);
        self::assertSame($fromSheet, $fromForm);
    }
}
