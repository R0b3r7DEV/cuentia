<?php

namespace App\Service;

use App\Repository\TransactionRepository;

/**
 * A simple cash-flow forecast: projects the running balance 30 / 60 / 90 days ahead,
 * based on the average daily net over the observed period.
 * ES: Una previsión de tesorería simple: proyecta el saldo a 30 / 60 / 90 días, según el
 * neto diario medio del periodo observado.
 *
 * Deliberately a transparent linear model (not ML): honest and easy to explain.
 * ES: Deliberadamente un modelo lineal transparente (no ML): honesto y fácil de explicar.
 */
class ForecastService
{
    private const HORIZONS = [0, 30, 60, 90];

    public function __construct(private TransactionRepository $transactions) {}

    public function summary(): array
    {
        $txs = $this->transactions->findAll();

        if ($txs === []) {
            return [
                'currentBalance' => '0.00',
                'avgMonthlyNet'  => '0.00',
                'points'         => array_map(fn ($h) => ['dayOffset' => $h, 'balance' => '0.00'], self::HORIZONS),
            ];
        }

        $balanceCents = 0;
        $first = null;
        $last = null;
        foreach ($txs as $tx) {
            $balanceCents += (int) round((float) $tx->getAmount() * 100);
            $date = $tx->getBookedAt();
            $first = ($first === null || $date < $first) ? $date : $first;
            $last = ($last === null || $date > $last) ? $date : $last;
        }

        $daysObserved = max(1, $first->diff($last)->days + 1);
        $avgDailyCents = $balanceCents / $daysObserved;

        $points = [];
        foreach (self::HORIZONS as $h) {
            $points[] = [
                'dayOffset' => $h,
                'balance'   => $this->euros($balanceCents + $avgDailyCents * $h),
            ];
        }

        return [
            'currentBalance' => $this->euros($balanceCents),
            'avgMonthlyNet'  => $this->euros($avgDailyCents * 30),
            'points'         => $points,
        ];
    }

    private function euros(int|float $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
