<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;

/**
 * Computes a VAT (IVA) summary from the transactions.
 * ES: Calcula un resumen de IVA a partir de los movimientos.
 *
 * This is the "finance moat": a generic developer can list transactions, but knowing
 * WHICH VAT rate applies to each kind of movement — and that salaries and Social
 * Security contributions carry no VAT — is domain knowledge.
 * ES: Este es el "foso financiero": un dev genérico sabe listar movimientos, pero saber
 * QUÉ tipo de IVA aplica a cada tipo de movimiento — y que nóminas y cuotas de la
 * Seguridad Social no llevan IVA — es conocimiento del dominio.
 *
 * Money is handled in integer cents to stay exact (no floats for money).
 * ES: El dinero se maneja en céntimos enteros para ser exacto (nada de floats).
 */
class VatService
{
    /**
     * Default Spanish VAT rate (%) per category. A pragmatic simplification:
     * real rates can vary per item (e.g. food 4/10%), but these sensible defaults
     * make the panel useful without per-line VAT data.
     * ES: Tipo de IVA español (%) por categoría por defecto. Simplificación pragmática:
     * los tipos reales pueden variar por producto (p.ej. alimentación 4/10%), pero estos
     * valores razonables hacen útil el panel sin datos de IVA por línea.
     */
    private const RATES = [
        // income / ingresos
        'Ingresos de cliente'      => 21,
        'Nómina'                   => 0,   // salary: not subject to VAT / nómina: no sujeta a IVA
        'Otros ingresos'           => 0,
        // expenses / gastos
        'Software y suscripciones' => 21,
        'Combustible'              => 21,
        'Suministros'              => 21,
        'Alquiler'                 => 21,  // commercial rent / alquiler de local
        'Restauración'             => 10,
        'Supermercado'             => 10,
        'Cuota autónomo'           => 0,   // Social Security: no VAT / Seguridad Social: sin IVA
        'Otros gastos'             => 21,
    ];

    public function __construct(private TransactionRepository $transactions) {}

    /**
     * @return array{
     *   outputVat:string, inputVat:string, net:string,
     *   incomeBase:string, expenseBase:string,
     *   byRate: array<int, array{rate:int, baseIncome:string, vatIncome:string, baseExpense:string, vatExpense:string}>
     * }
     */
    public function summary(): array
    {
        $outputVatC = 0;   // IVA repercutido (from income)
        $inputVatC  = 0;   // IVA soportado (from expenses)
        $incomeBaseC = 0;
        $expenseBaseC = 0;
        $byRate = [];      // rate => [baseIncomeC, vatIncomeC, baseExpenseC, vatExpenseC]

        foreach ($this->transactions->findAll() as $tx) {
            $rate = $this->rateFor($tx);
            $isIncome = !str_starts_with(trim($tx->getAmount()), '-');
            $grossC = (int) round(abs((float) $tx->getAmount()) * 100);

            [$baseC, $vatC] = $this->splitVat($grossC, $rate);

            $byRate[$rate] ??= ['baseIncomeC' => 0, 'vatIncomeC' => 0, 'baseExpenseC' => 0, 'vatExpenseC' => 0];
            if ($isIncome) {
                $outputVatC += $vatC;
                $incomeBaseC += $baseC;
                $byRate[$rate]['baseIncomeC'] += $baseC;
                $byRate[$rate]['vatIncomeC'] += $vatC;
            } else {
                $inputVatC += $vatC;
                $expenseBaseC += $baseC;
                $byRate[$rate]['baseExpenseC'] += $baseC;
                $byRate[$rate]['vatExpenseC'] += $vatC;
            }
        }

        ksort($byRate);
        $byRateOut = [];
        foreach ($byRate as $rate => $v) {
            $byRateOut[] = [
                'rate'        => $rate,
                'baseIncome'  => $this->euros($v['baseIncomeC']),
                'vatIncome'   => $this->euros($v['vatIncomeC']),
                'baseExpense' => $this->euros($v['baseExpenseC']),
                'vatExpense'  => $this->euros($v['vatExpenseC']),
            ];
        }

        return [
            'outputVat'   => $this->euros($outputVatC),
            'inputVat'    => $this->euros($inputVatC),
            'net'         => $this->euros($outputVatC - $inputVatC), // >0 = pay to tax office
            'incomeBase'  => $this->euros($incomeBaseC),
            'expenseBase' => $this->euros($expenseBaseC),
            'byRate'      => $byRateOut,
        ];
    }

    /** The VAT rate (%) that applies to a transaction, from its category. */
    public function rateFor(Transaction $tx): int
    {
        $name = $tx->getCategory()?->getName() ?? '';
        return self::RATES[$name] ?? 0; // unknown category → assume no VAT (don't invent)
    }

    /** The taxable base (amount without VAT) of a transaction, in cents. Reused by IRPF. */
    public function baseCents(Transaction $tx): int
    {
        $grossC = (int) round(abs((float) $tx->getAmount()) * 100);
        [$baseC] = $this->splitVat($grossC, $this->rateFor($tx));
        return $baseC;
    }

    /**
     * Split a gross amount (in cents) into base + VAT (in cents), given a rate %.
     * base = gross / (1 + rate/100); vat = gross - base.
     * @return array{0:int,1:int} [baseCents, vatCents]
     */
    private function splitVat(int $grossC, int $rate): array
    {
        if ($rate === 0) {
            return [$grossC, 0];
        }
        $baseC = (int) round($grossC * 100 / (100 + $rate));
        return [$baseC, $grossC - $baseC];
    }

    private function euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
