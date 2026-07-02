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
