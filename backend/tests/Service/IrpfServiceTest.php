<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
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
        $repo->method('findForUser')->willReturn($transactions);

        return new IrpfService($repo, $vat);
    }

    /** IrpfService whose "today" comes from a clock frozen at an exact UTC instant. */
    private function irpfAt(string $utc, array $transactions = []): IrpfService
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('findForUser')->willReturn($transactions);
        $clock = new \App\Time\BusinessClock(new \Symfony\Component\Clock\MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))));

        return new IrpfService($repo, new VatService($this->createStub(TransactionRepository::class)), $clock);
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

        $s = $svc->summary(new User(), new \DateTimeImmutable('2026-07-02'));

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
        $s = $svc->summary(new User(), new \DateTimeImmutable('2026-07-02'));

        self::assertSame('0.00', $s['totalPayment']);
    }

    public function testNextDeadlineCountdown(): void
    {
        $svc = $this->irpf([$this->tx('1000.00', 'Ingresos de cliente')]);
        $s = $svc->summary(new User(), new \DateTimeImmutable('2026-07-02'));

        // Q1 (Apr 20) already passed → next is Q2 (Jul 20), 18 days away.
        self::assertSame(2, $s['nextDeadline']['quarter']);
        self::assertSame(18, $s['nextDeadline']['daysLeft']);
    }

    /**
     * End of quarter: 22:30 UTC on 31 March is already 1 April in Spain (UTC+2 since 29 March). The first
     * quarter has ended and its deadline (20 April) is 19 days away — in UTC it would still say 20.
     */
    public function testEndOfQuarterIsDecidedInSpain(): void
    {
        $s = $this->irpfAt('2026-03-31 22:30:00', [$this->tx('1000.00', 'Ingresos de cliente')])->summary(new User());
        self::assertSame(1, $s['nextDeadline']['quarter']);
        self::assertSame(19, $s['nextDeadline']['daysLeft']);

        // the deadline day itself still counts as "next" in Spain until midnight Madrid time
        $s = $this->irpfAt('2026-04-20 21:30:00', [$this->tx('1000.00', 'Ingresos de cliente')])->summary(new User());
        self::assertSame(1, $s['nextDeadline']['quarter']);
        self::assertSame(0, $s['nextDeadline']['daysLeft']);

        // 22:30 UTC on 20 April is 00:30 on the 21st in Spain: Q1 has passed, next is Q2 (20 July)
        $s = $this->irpfAt('2026-04-20 22:30:00', [$this->tx('1000.00', 'Ingresos de cliente')])->summary(new User());
        self::assertSame(2, $s['nextDeadline']['quarter']);
    }

    /** With no movements yet, the year is the Spanish one: 23:30 UTC on 31 December is already the new year. */
    public function testNewYearForTaxesIsDecidedInSpain(): void
    {
        $s = $this->irpfAt('2026-12-31 23:30:00')->summary(new User());
        self::assertSame(2027, $s['year']);
        self::assertSame('2027-04-20', $s['quarters'][0]['deadline']);
    }
}
