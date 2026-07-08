<?php

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceRecord;
use App\Entity\User;
use App\Service\InvoicePdf;
use App\Service\VerifactuQr;
use PHPUnit\Framework\TestCase;

class InvoicePdfTest extends TestCase
{
    private function invoice(): Invoice
    {
        $user = (new User())->setEmail('me@cuentia.local')->setTaxId('B12345678');
        $customer = (new Customer())->setUser($user)->setName('ACME SL')->setTaxId('B87654321');

        $invoice = (new Invoice())
            ->setUser($user)->setCustomer($customer)
            ->setSeries('2026')->setNumber(1)
            ->setIssuedAt(new \DateTimeImmutable('2026-07-06'))
            ->setBaseTotal('1000.00')->setVatTotal('210.00')->setTotal('1210.00');
        $invoice->addLine((new InvoiceLine())
            ->setDescription('Desarrollo web')->setQuantity(1)->setUnitPrice('1000.00')->setVatRate('21.00'));

        return $invoice;
    }

    private function record(): InvoiceRecord
    {
        return (new InvoiceRecord())
            ->setIssuerNif('B12345678')->setFullNumber('2026/1')->setIssueDate('06-07-2026')
            ->setInvoiceType('F1')->setVatTotal('210.00')->setTotal('1210.00')
            ->setGeneratedAt('2026-07-06T10:00:00+02:00')->setPreviousHash(null)->setHash(str_repeat('A', 64));
    }

    public function testBuildsAPdfWithTheVerifactuRecord(): void
    {
        $pdf = (new InvoicePdf(new VerifactuQr()))->build($this->invoice(), $this->record());
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    public function testBuildsAPdfWithoutARecord(): void
    {
        $pdf = (new InvoicePdf(new VerifactuQr()))->build($this->invoice(), null);
        self::assertStringStartsWith('%PDF', $pdf);
    }
}
