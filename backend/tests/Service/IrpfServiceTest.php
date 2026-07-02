<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\IrpfService;
use App\Service\VatService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the IRPF (modelo 130) estimate: cumulative 20% of net, salary excluded.
 * ES: Tests unitarios de la estimación de IRPF (modelo 130): 20% acumulado del neto, nómina excluida.
 */
class IrpfServiceTest extends TestCase
{
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

    private function irpf(array $transactions): IrpfService
    {
        // VatService only needs baseCents() here (does not touch its repository).
        $vat = new VatService($this->createStub(TransactionRepository::class));

        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('findAll')->willReturn($transactions);

        return new IrpfService($repo, $vat);
    }

    public function testQuarterlyPaymentAndDeadline(): void
    {
        $svc = $this->irpf([
            $this->tx('1210.00', 'Ingresos de cliente'),
            $this->tx('726.00', 'Ingresos de cliente'),
            $this->tx('1850.00', 'Nómina'),               // excluded from modelo 130
            $this->tx('-60.00', 'Combustible'),
            $this->tx('-52.30', 'Supermercado'),
            $this->tx('-24.99', 'Software y suscripciones'),
            $this->tx('-294.00', 'Cuota autónomo'),
            $this->tx('-38.50', 'Restauración'),
        ]);

        $s = $svc->summary(new \DateTimeImmutable('2026-07-02'));

        self::assertSame(2026, $s['year']);
        // Q1 net = 1600 (client income base) - 446.79 (expense bases) = 1153.21; 20% = 230.64
        self::assertSame('1153.21', $s['quarters'][0]['net']);
        self::assertSame('230.64', $s['quarters'][0]['payment']);
        self::assertSame('230.64', $s['totalPayment']);
    }

    public function testSalaryDoesNotCountAsSelfEmploymentIncome(): void
    {
        // Only a salary → no self-employment income → nothing to prepay.
        $svc = $this->irpf([$this->tx('3000.00', 'Nómina')]);
        $s = $svc->summary(new \DateTimeImmutable('2026-07-02'));

        self::assertSame('0.00', $s['totalPayment']);
    }

    public function testNextDeadlineCountdown(): void
    {
        $svc = $this->irpf([$this->tx('1000.00', 'Ingresos de cliente')]);
        $s = $svc->summary(new \DateTimeImmutable('2026-07-02'));

        // Q1 (Apr 20) already passed → next is Q2 (Jul 20), 18 days away.
        self::assertSame(2, $s['nextDeadline']['quarter']);
        self::assertSame(18, $s['nextDeadline']['daysLeft']);
    }
}
