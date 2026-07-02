<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\ForecastService;
use PHPUnit\Framework\TestCase;

class ForecastServiceTest extends TestCase
{
    private function tx(string $amount, string $date): Transaction
    {
        return (new Transaction())
            ->setBookedAt(new \DateTimeImmutable($date))
            ->setDescription('test')
            ->setAmount($amount);
    }

    private function service(array $transactions): ForecastService
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('findAll')->willReturn($transactions);
        return new ForecastService($repo);
    }

    public function testLinearProjection(): void
    {
        // +1000 on Jan 1, -400 on Jan 11 → balance 600 over 11 observed days.
        $svc = $this->service([
            $this->tx('1000.00', '2026-01-01'),
            $this->tx('-400.00', '2026-01-11'),
        ]);

        $s = $svc->summary();

        self::assertSame('600.00', $s['currentBalance']);
        // avg daily = 60000/11 cents; * 30 → 1636.36
        self::assertSame('1636.36', $s['avgMonthlyNet']);
        self::assertCount(4, $s['points']);
        self::assertSame(0, $s['points'][0]['dayOffset']);
        self::assertSame('600.00', $s['points'][0]['balance']);
        // +30 days → 600 + 1636.36 ≈ 2236.36
        self::assertSame('2236.36', $s['points'][1]['balance']);
    }

    public function testEmptyIsZero(): void
    {
        $s = $this->service([])->summary();
        self::assertSame('0.00', $s['currentBalance']);
        self::assertSame('0.00', $s['points'][3]['balance']);
    }
}
