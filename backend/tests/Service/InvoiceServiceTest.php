<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    private function service(int $nextNumber): InvoiceService
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $invoices = $this->createStub(InvoiceRepository::class);
        $invoices->method('nextNumber')->willReturn($nextNumber);

        $customers = $this->createStub(CustomerRepository::class);
        $customers->method('findOneBy')->willReturn(null); // always create a new customer

        return new InvoiceService($em, $invoices, $customers);
    }

    public function testComputesTotalsAndAssignsNumber(): void
    {
        $svc = $this->service(5);

        $invoice = $svc->create(new User(), [
            'series' => 'TEST',
            'customer' => ['name' => 'ACME', 'taxId' => 'B12345678'],
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unitPrice' => '100.00', 'vatRate' => '21.00'],
                ['description' => 'Hosting', 'quantity' => 2, 'unitPrice' => '50.00', 'vatRate' => '10.00'],
            ],
        ]);

        // base = 100 + (50×2) = 200 ; VAT = 21 + 10 = 31 ; total = 231
        self::assertSame('200.00', $invoice->getBaseTotal());
        self::assertSame('31.00', $invoice->getVatTotal());
        self::assertSame('231.00', $invoice->getTotal());
        self::assertSame('TEST/5', $invoice->getFullNumber());
        self::assertSame('ACME', $invoice->getCustomer()->getName());
        self::assertCount(2, $invoice->getLines());
    }

    public function testRejectsInvoiceWithNoLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service(1)->create(new User(), ['customer' => ['name' => 'X', 'taxId' => 'Y'], 'lines' => []]);
    }
}
