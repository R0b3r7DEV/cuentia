<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\VatService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the VAT math — the finance logic is pure and verifiable without a database.
 * ES: Tests unitarios del cálculo de IVA — la lógica financiera es pura y verificable sin BD.
 */
class VatServiceTest extends TestCase
{
    /** Build a Transaction with a category (no database involved). */
    private function tx(string $amount, string $categoryName): Transaction
    {
        $kind = str_starts_with($amount, '-') ? 'expense' : 'income';
        $category = (new Category())->setName($categoryName)->setKind($kind);

        return (new Transaction())
            ->setBookedAt(new \DateTimeImmutable('2026-01-15'))
            ->setDescription('test')
            ->setAmount($amount)
            ->setCategory($category);
    }

    private function service(array $transactions): VatService
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('findAll')->willReturn($transactions);

        return new VatService($repo);
    }

    public function testRateAndBaseForStandardRate(): void
    {
        $svc = $this->service([]);
        $t = $this->tx('-121.00', 'Software y suscripciones'); // 21%

        self::assertSame(21, $svc->rateFor($t));
        // 121 gross at 21% → base 100.00 (10000 cents), VAT 21.00
        self::assertSame(10000, $svc->baseCents($t));
    }

    public function testExemptCategoryHasNoVat(): void
    {
        $svc = $this->service([]);
        $salary = $this->tx('1850.00', 'Nómina');

        self::assertSame(0, $svc->rateFor($salary));
        self::assertSame(185000, $svc->baseCents($salary)); // base == gross when rate is 0
    }

    public function testSummaryOutputInputAndNet(): void
    {
        $svc = $this->service([
            $this->tx('1210.00', 'Ingresos de cliente'),  // 21%
            $this->tx('726.00', 'Ingresos de cliente'),   // 21%
            $this->tx('1850.00', 'Nómina'),               // 0% (exempt)
            $this->tx('-60.00', 'Combustible'),           // 21%
            $this->tx('-52.30', 'Supermercado'),          // 10%
            $this->tx('-24.99', 'Software y suscripciones'), // 21%
            $this->tx('-294.00', 'Cuota autónomo'),       // 0% (exempt)
            $this->tx('-38.50', 'Restauración'),          // 10%
        ]);

        $s = $svc->summary();

        self::assertSame('336.00', $s['outputVat']); // VAT within 1.600 base of client income
        self::assertSame('23.00', $s['inputVat']);
        self::assertSame('313.00', $s['net']);       // 336 - 23 → to pay
    }
}
