<?php

namespace App\Service;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Parses a bank CSV and stores the movements as Transaction rows.
 * ES: Parsea un CSV bancario y guarda los movimientos como filas Transaction.
 *
 * All the real work lives here (in a service), not in the controller, so it can be
 * unit-tested in isolation. / Todo el trabajo real vive aquí (en un servicio), no en
 * el controlador, para poder testearlo de forma aislada.
 *
 * Expected CSV: a header row with columns named (EN or ES):
 *   date/fecha ; description/concepto/descripcion ; amount/importe
 * Separator may be ',' or ';' (auto-detected).
 */
class ImportService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Detect the format and dispatch. Supports CSV and the Spanish Norma 43 (Cuaderno 43).
     * ES: Detecta el formato y despacha. Soporta CSV y la Norma 43 española (Cuaderno 43).
     *
     * @return array{imported:int, errors:string[]}
     */
    public function import(string $content): array
    {
        return $this->looksLikeNorma43($content)
            ? $this->importNorma43($content)
            : $this->importCsv($content);
    }

    /**
     * @return array{imported:int, errors:string[]}
     */
    public function importCsv(string $csv): array
    {
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if (count($lines) < 2 || $lines[0] === '') {
            return ['imported' => 0, 'errors' => ['CSV is empty or has no data rows']];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header = array_map(
            static fn ($h) => strtolower(trim($h)),
            str_getcsv($lines[0], $delimiter, '"', '\\')
        );

        $dateCol = $this->findColumn($header, ['date', 'fecha']);
        $descCol = $this->findColumn($header, ['description', 'concepto', 'descripcion', 'descripción']);
        $amtCol  = $this->findColumn($header, ['amount', 'importe']);

        if ($dateCol === null || $descCol === null || $amtCol === null) {
            return ['imported' => 0, 'errors' => [
                'Missing required columns. Expected: date/fecha, description/concepto, amount/importe',
            ]];
        }

        $imported = 0;
        for ($i = 1, $n = count($lines); $i < $n; $i++) {
            $raw = trim($lines[$i]);
            if ($raw === '') {
                continue; // skip blank lines / saltar líneas en blanco
            }
            $cells = str_getcsv($raw, $delimiter, '"', '\\');

            try {
                $date = $this->parseDate(trim($cells[$dateCol] ?? ''));
                $amount = $this->parseAmount(trim($cells[$amtCol] ?? ''));
                $description = trim($cells[$descCol] ?? '');

                $tx = (new Transaction())
                    ->setBookedAt($date)
                    ->setDescription($description)
                    ->setAmount($amount)
                    ->setImportedFrom('csv');

                $this->em->persist($tx); // stage it for saving / lo prepara para guardar
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = sprintf('Row %d: %s', $i + 1, $e->getMessage());
            }
        }

        $this->em->flush(); // one round-trip to the DB / un solo viaje a la BD

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * Parse a Norma 43 (AEB Cuaderno 43) bank statement — a fixed-width text format.
     * ES: Parsea un extracto Norma 43 (Cuaderno 43 de la AEB) — formato de ancho fijo.
     *
     * Records we use / Registros que usamos:
     *  - "22" movement: op date (pos 7-12, YYMMDD), debit/credit flag (pos 24: 1=debit, 2=credit),
     *    amount (pos 25-38, integer with 2 implied decimals).
     *  - "23" concept: two 38-char free-text fields (pos 5-42, 43-80) → the description.
     *
     * @return array{imported:int, errors:string[]}
     */
    public function importNorma43(string $content): array
    {
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $imported = 0;
        $current = null; // the movement being assembled / el movimiento en construcción

        $flush = function () use (&$current, &$imported) {
            if ($current !== null) {
                $this->em->persist($current);
                $imported++;
                $current = null;
            }
        };

        foreach ($lines as $i => $raw) {
            $code = substr($raw, 0, 2);

            if ($code === '22') {
                $flush(); // a new movement closes the previous one / un nuevo movimiento cierra el anterior
                try {
                    $date = $this->parseNorma43Date(substr($raw, 6, 6));
                    $isDebit = substr($raw, 23, 1) === '1';
                    $cents = (int) ltrim(substr($raw, 24, 14), '0') ?: 0;
                    $amount = number_format($cents / 100, 2, '.', '');
                    if ($isDebit) {
                        $amount = '-' . $amount;
                    }

                    $current = (new Transaction())
                        ->setBookedAt($date)
                        ->setDescription('')
                        ->setAmount($amount)
                        ->setImportedFrom('norma43');
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Line %d: %s', $i + 1, $e->getMessage());
                    $current = null;
                }
            } elseif ($code === '23' && $current !== null) {
                // Append the two concept fields to the description.
                $part = trim(substr($raw, 4, 38) . ' ' . substr($raw, 42, 38));
                $desc = trim($current->getDescription() . ' ' . $part);
                $current->setDescription($desc);
            } elseif (in_array($code, ['33', '88'], true)) {
                $flush(); // account footer / end of file
            }
        }
        $flush();

        $this->em->flush();

        return ['imported' => $imported, 'errors' => $errors];
    }

    /** Heuristic: Norma 43 lines are fixed-width records that start with a 2-digit code. */
    private function looksLikeNorma43(string $content): bool
    {
        $first = strtok(ltrim($content), "\r\n") ?: '';
        // Norma 43 files start with an "11" (account header) or "22" (movement) record,
        // and records are fixed-width (>= 80 chars). CSV starts with a text header.
        return (bool) preg_match('/^(11|22)/', $first) && strlen($first) >= 80;
    }

    private function parseNorma43Date(string $yymmdd): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('ymd', $yymmdd);
        if ($d === false) {
            throw new \InvalidArgumentException("invalid Norma 43 date '$yymmdd'");
        }
        return $d->setTime(0, 0);
    }

    /** Pick ',' or ';' by whichever appears more in the header. */
    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    }

    /** @param string[] $header @param string[] $names */
    private function findColumn(array $header, array $names): ?int
    {
        foreach ($names as $name) {
            $idx = array_search($name, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }
        return null;
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $d = \DateTimeImmutable::createFromFormat($format, $value);
            if ($d !== false) {
                return $d->setTime(0, 0);
            }
        }
        throw new \InvalidArgumentException("invalid date '$value' (expected YYYY-MM-DD or DD/MM/YYYY)");
    }

    /**
     * Normalizes money written in international ("1234.56") or Spanish ("1.234,56")
     * format into a plain decimal string. Never uses float, to keep exact precision.
     * ES: Normaliza dinero en formato internacional ("1234.56") o español ("1.234,56")
     * a un string decimal simple. Nunca usa float, para mantener la precisión exacta.
     */
    private function parseAmount(string $value): string
    {
        $value = str_replace(' ', '', $value);
        if ($value === '') {
            throw new \InvalidArgumentException('empty amount');
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            // "1.234,56" → dot = thousands, comma = decimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma) {
            // "1234,56" → comma is the decimal separator
            $value = str_replace(',', '.', $value);
        }
        // else "1234.56" or "1234" → already fine

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("invalid amount");
        }

        return $value;
    }
}
