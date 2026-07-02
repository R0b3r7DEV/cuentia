<?php

namespace App\Service;

use App\Repository\TransactionRepository;

/**
 * Estimates the Spanish IRPF quarterly prepayment (modelo 130) for a freelancer in
 * "estimación directa".
 * ES: Estima el pago fraccionado trimestral del IRPF (modelo 130) para un autónomo en
 * estimación directa.
 *
 * Domain rules encoded here / Reglas del dominio codificadas aquí:
 *  - Base = income (without VAT) − deductible expenses (without VAT).
 *    ES: Base = ingresos (sin IVA) − gastos deducibles (sin IVA).
 *  - Only SELF-EMPLOYMENT income counts (a salary/"Nómina" is employee income, not modelo 130).
 *    ES: Solo cuentan los ingresos de actividad (una "Nómina" es renta del trabajo, no modelo 130).
 *  - Payment = 20% of the year-to-date net, minus what was already paid in previous quarters
 *    (modelo 130 is cumulative). / El pago = 20% del neto acumulado del año menos lo ya pagado
 *    en trimestres anteriores (el modelo 130 es acumulativo).
 *  - Deadlines: Q1→Apr 20, Q2→Jul 20, Q3→Oct 20, Q4→Jan 30 (next year).
 */
class IrpfService
{
    private const SELF_EMPLOYMENT_INCOME = ['Ingresos de cliente', 'Otros ingresos'];
    private const FRACCIONADO_RATE = 20; // % (modelo 130)

    public function __construct(
        private TransactionRepository $transactions,
        private VatService $vat,
    ) {}

    public function summary(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        $incomeC  = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $expenseC = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $year = null;

        foreach ($this->transactions->findAll() as $tx) {
            $month = (int) $tx->getBookedAt()->format('n');
            $q = intdiv($month - 1, 3) + 1;
            $year ??= (int) $tx->getBookedAt()->format('Y');

            $category = $tx->getCategory()?->getName() ?? '';
            $isIncome = !str_starts_with(trim($tx->getAmount()), '-');
            $baseC = $this->vat->baseCents($tx);

            if ($isIncome) {
                if (in_array($category, self::SELF_EMPLOYMENT_INCOME, true)) {
                    $incomeC[$q] += $baseC;
                }
            } else {
                $expenseC[$q] += $baseC; // all expenses treated as deductible (simplification)
            }
        }

        $year ??= (int) $today->format('Y');

        $deadlines = [
            1 => new \DateTimeImmutable("$year-04-20"),
            2 => new \DateTimeImmutable("$year-07-20"),
            3 => new \DateTimeImmutable("$year-10-20"),
            4 => new \DateTimeImmutable(($year + 1) . '-01-30'),
        ];

        $quarters = [];
        $cumNetC = 0;
        $paidC = 0;
        foreach ([1, 2, 3, 4] as $q) {
            $netC = $incomeC[$q] - $expenseC[$q];
            $cumNetC += $netC;
            $dueCumC = (int) round(max(0, $cumNetC) * self::FRACCIONADO_RATE / 100);
            $paymentC = max(0, $dueCumC - $paidC);
            $paidC += $paymentC;

            $quarters[] = [
                'quarter'     => $q,
                'label'       => "Q$q $year",
                'incomeBase'  => $this->euros($incomeC[$q]),
                'expenseBase' => $this->euros($expenseC[$q]),
                'net'         => $this->euros($netC),
                'payment'     => $this->euros($paymentC),
                'deadline'    => $deadlines[$q]->format('Y-m-d'),
            ];
        }

        return [
            'year'         => $year,
            'rate'         => self::FRACCIONADO_RATE,
            'quarters'     => $quarters,
            'totalPayment' => $this->euros($paidC),
            'nextDeadline' => $this->nextDeadline($deadlines, $today),
        ];
    }

    /** @param array<int, \DateTimeImmutable> $deadlines */
    private function nextDeadline(array $deadlines, \DateTimeImmutable $today): ?array
    {
        foreach ($deadlines as $q => $date) {
            if ($date >= $today) {
                return [
                    'quarter'  => $q,
                    'date'     => $date->format('Y-m-d'),
                    'daysLeft' => (int) $today->diff($date)->days,
                ];
            }
        }
        return null;
    }

    private function euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
