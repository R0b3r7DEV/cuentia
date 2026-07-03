<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the importers. We capture what would be persisted, without a database.
 * ES: Tests unitarios de los importadores. Capturamos lo que se persistiría, sin base de datos.
 */
class ImportServiceTest extends TestCase
{
    /** @var Transaction[] */
    private array $persisted = [];

    private function service(): ImportService
    {
        $this->persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) {
            if ($entity instanceof Transaction) {
                $this->persisted[] = $entity;
            }
        });

        return new ImportService($em);
    }

    // --- CSV -----------------------------------------------------------------

    public function testCsvParsesSpanishNumbersAndSemicolons(): void
    {
        $csv = "fecha;concepto;importe\n2026-01-05;Compra Mercadona;-52,30\n2026-01-10;Cliente ACME;1.210,00\n";
        $result = $this->service()->import($csv, new User());

        self::assertSame(2, $result['imported']);
        self::assertSame('-52.30', $this->persisted[0]->getAmount());
        self::assertSame('1210.00', $this->persisted[1]->getAmount());
        self::assertSame('csv', $this->persisted[0]->getImportedFrom());
    }

    // --- Norma 43 ------------------------------------------------------------

    /** Build an 80-char "22" movement record. */
    private function rec22(string $yymmdd, string $flag, int $cents): string
    {
        $line = '22'
            . str_pad('', 4)                              // office
            . $yymmdd                                     // op date (6)
            . $yymmdd                                     // value date (6)
            . '00' . '000'                                // concept codes
            . $flag                                       // 1=debit, 2=credit
            . str_pad((string) $cents, 14, '0', STR_PAD_LEFT)
            . str_pad('', 10) . str_pad('', 12) . str_pad('', 16);
        return str_pad($line, 80);
    }

    /** Build an 80-char "23" concept record. */
    private function rec23(string $c1, string $c2 = ''): string
    {
        return '23' . '01' . str_pad($c1, 38) . str_pad($c2, 38);
    }

    public function testNorma43IsDetectedAndParsed(): void
    {
        $content = implode("\n", [
            $this->rec22('260115', '2', 123456),   // credit 1.234,56
            $this->rec23('TRANSFERENCIA CLIENTE'),
            $this->rec22('260120', '1', 6000),     // debit 60,00
            $this->rec23('GASOLINA REPSOL'),
            str_pad('88', 80),                     // end of file
        ]) . "\n";

        $result = $this->service()->import($content, new User());

        self::assertSame(2, $result['imported']);
        self::assertSame('1234.56', $this->persisted[0]->getAmount());
        self::assertSame('TRANSFERENCIA CLIENTE', $this->persisted[0]->getDescription());
        self::assertSame('norma43', $this->persisted[0]->getImportedFrom());
        self::assertSame('-60.00', $this->persisted[1]->getAmount());
        self::assertSame('GASOLINA REPSOL', $this->persisted[1]->getDescription());
        self::assertSame('2026-01-15', $this->persisted[0]->getBookedAt()->format('Y-m-d'));
    }
}
