<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\QuoteRepository;
use App\Service\InvoiceService;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class QuoteServiceTest extends TestCase
{
    private function service(int $nextNumber): QuoteService
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $quotes = $this->createStub(QuoteRepository::class);
        $quotes->method('nextNumber')->willReturn($nextNumber);

        $customers = $this->createStub(CustomerRepository::class);
        $customers->method('findOneBy')->willReturn(null);

        return new QuoteService($em, $quotes, $customers, $this->createStub(InvoiceService::class));
    }

    public function testComputesTotalsAndNumberAndStartsAsDraft(): void
    {
        $quote = $this->service(3)->create(new User(), [
            'series' => 'PRE',
            'customer' => ['name' => 'ACME', 'taxId' => 'B12345678'],
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unitPrice' => '100.00', 'vatRate' => '21.00'],
                ['description' => 'Hosting', 'quantity' => 2, 'unitPrice' => '50.00', 'vatRate' => '10.00'],
            ],
        ]);

        self::assertSame('200.00', $quote->getBaseTotal());
        self::assertSame('31.00', $quote->getVatTotal());
        self::assertSame('231.00', $quote->getTotal());
        self::assertSame('PRE/3', $quote->getFullNumber());
        self::assertSame('draft', $quote->getStatus());
    }

    public function testDefaultSeriesIsPPlusYear(): void
    {
        $quote = $this->service(1)->create(new User(), [
            'customer' => ['name' => 'X', 'taxId' => 'Y'],
            'lines' => [['description' => 'x', 'unitPrice' => '10.00', 'vatRate' => '21.00']],
        ]);

        self::assertSame('P' . date('Y') . '/1', $quote->getFullNumber());
    }

    public function testRejectsQuoteWithNoLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service(1)->create(new User(), ['customer' => ['name' => 'X', 'taxId' => 'Y'], 'lines' => []]);
    }
}
