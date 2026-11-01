<?php

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Entity\WorkOrderLine;
use App\Entity\WorkOrderPhoto;
use App\Service\ImageSanitizer;
use App\Service\WorkOrderPdf;
use PHPUnit\Framework\TestCase;

class WorkOrderPdfTest extends TestCase
{
    private function order(): WorkOrder
    {
        $user = (new User())->setEmail('me@cuentia.local')->setTaxId('B12345678')
            ->setBusinessName('Instalaciones Pérez SL')->setFiscalAddress('C/ Major 1, València');
        $customer = (new Customer())->setUser($user)->setName('María López')->setTaxId('12345678Z');

        $wo = (new WorkOrder())->setUser($user)->setCustomer($customer)
            ->setTitle('Enchufe quemado en la cocina')->setDescription('Cambiar base y revisar línea')
            ->setStatus('terminado')->setLaborHours('2.00')->setLaborRate('35.00')->setLaborVatRate('21.00');
        $wo->addLine((new WorkOrderLine())->setDescription('Base schuko')->setQuantity(2)->setUnitPrice('12.00')->setVatRate('21.00'));

        return $wo;
    }

    private function withPhotoAndSignature(WorkOrder $wo): WorkOrder
    {
        $photo = (new ImageSanitizer())->photo(ImageSanitizerTest::jpeg(80, 60));
        $wo->addPhoto((new WorkOrderPhoto())->setImage($photo['jpeg'], $photo['width'], $photo['height']));

        return $wo->sign((new ImageSanitizer())->signature(base64_encode(ImageSanitizerTest::png(300, 120, true))), 'María López');
    }

    public function testShowsTheJobTheIssuerAndTotalsInCents(): void
    {
        $html = (new WorkOrderPdf())->html($this->order());

        self::assertStringContainsString('Enchufe quemado en la cocina', $html);
        self::assertStringContainsString('Instalaciones Pérez SL', $html);
        self::assertStringContainsString('María López', $html);
        // materials 24,00 + labour 70,00 = 94,00 base; VAT 5,04 + 14,70 = 19,74; total 113,74
        self::assertStringContainsString('94,00 €', $html);
        self::assertStringContainsString('19,74 €', $html);
        self::assertStringContainsString('113,74 €', $html);
    }

    public function testEmbedsPhotosAndSignatureAsDataUris(): void
    {
        $html = (new WorkOrderPdf())->html($this->withPhotoAndSignature($this->order()));

        self::assertStringContainsString('data:image/jpeg;base64,', $html);
        self::assertStringContainsString('data:image/png;base64,', $html);
        self::assertStringContainsString('Firmado por María López', $html);
    }

    public function testSaysWhenTheClientWasNotThereToSign(): void
    {
        $html = (new WorkOrderPdf())->html($this->order()->waiveSignature());
        self::assertStringContainsString('el cliente no estaba presente', $html);
    }

    /** User text is escaped: it can neither inject markup nor make Dompdf fetch a remote URL. */
    public function testUserTextIsEscaped(): void
    {
        $wo = $this->order()->setTitle('<script>alert(1)</script>')
            ->setDescription('<img src="http://169.254.169.254/latest/meta-data/">');
        $html = (new WorkOrderPdf())->html($wo);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img src="http://', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRendersARealPdfWithTheImagesEvenWithRemoteLoadingOff(): void
    {
        $pdf = (new WorkOrderPdf())->build($this->withPhotoAndSignature($this->order()));

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThanOrEqual(2, substr_count($pdf, '/Subtype /Image'), 'photo and signature are drawn');
    }
}
