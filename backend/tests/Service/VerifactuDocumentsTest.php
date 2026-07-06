<?php

namespace App\Tests\Service;

use App\Entity\InvoiceRecord;
use App\Service\VerifactuQr;
use App\Service\VerifactuXml;
use PHPUnit\Framework\TestCase;

class VerifactuDocumentsTest extends TestCase
{
    private function record(?string $previousHash = null): InvoiceRecord
    {
        return (new InvoiceRecord())
            ->setIssuerNif('B12345678')
            ->setFullNumber('2026/7')
            ->setIssueDate('06-07-2026')
            ->setInvoiceType('F1')
            ->setVatTotal('210.00')
            ->setTotal('1210.00')
            ->setGeneratedAt('2026-07-06T10:00:00+02:00')
            ->setPreviousHash($previousHash)
            ->setHash(str_repeat('A', 64));
    }

    public function testQrUrlCarriesTheInvoiceIdentity(): void
    {
        $url = (new VerifactuQr())->url($this->record());
        self::assertStringContainsString('ValidarQR', $url);
        self::assertStringContainsString('nif=B12345678', $url);
        self::assertStringContainsString('numserie=2026%2F7', $url); // "/" url-encoded
        self::assertStringContainsString('importe=1210.00', $url);
    }

    public function testQrRendersAsSvg(): void
    {
        $svg = (new VerifactuQr())->svg($this->record(), 180);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
    }

    public function testXmlIsWellFormedAndCarriesTheFingerprint(): void
    {
        $out = (new VerifactuXml())->build($this->record('PREVHASH'));

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($out), 'XML must be well-formed');
        self::assertStringContainsString('<NumSerieFactura>2026/7</NumSerieFactura>', $out);
        self::assertStringContainsString('<ImporteTotal>1210.00</ImporteTotal>', $out);
        self::assertStringContainsString('<Huella>' . str_repeat('A', 64) . '</Huella>', $out);
        // a non-first record references the previous fingerprint
        self::assertStringContainsString('<RegistroAnterior>', $out);
        self::assertStringContainsString('<Huella>PREVHASH</Huella>', $out);
    }

    public function testXmlMarksTheFirstRecordInTheChain(): void
    {
        $out = (new VerifactuXml())->build($this->record(null));
        self::assertStringContainsString('<PrimerRegistro>S</PrimerRegistro>', $out);
        self::assertStringNotContainsString('<RegistroAnterior>', $out);
    }
}
