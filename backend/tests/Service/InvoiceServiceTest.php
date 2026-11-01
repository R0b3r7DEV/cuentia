<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRecordRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use App\Service\VerifactuHasher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    private function service(int $nextNumber): InvoiceService
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $invoices = $this->createStub(InvoiceRepository::class);
        $invoices->method('nextNumber')->willReturn($nextNumber);

        $customers = $this->createStub(CustomerRepository::class);
        $customers->method('findOneBy')->willReturn(null); // always create a new customer

        $records = $this->createStub(InvoiceRecordRepository::class);
        $records->method('lastForUser')->willReturn(null); // first in the chain

        return new InvoiceService($em, $invoices, $customers, $records, new VerifactuHasher());
    }

    public function testComputesTotalsAndAssignsNumber(): void
    {
        $svc = $this->service(5);

        $invoice = $svc->create(new User(), [
            'series' => 'TEST',
            'customer' => ['name' => 'ACME', 'taxId' => 'B12345678'],
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unitPrice' => '100.00', 'vatRate' => '21.00'],
                ['description' => 'Hosting', 'quantity' => 2, 'unitPrice' => '50.00', 'vatRate' => '10.00'],
            ],
        ]);

        // base = 100 + (50×2) = 200 ; VAT = 21 + 10 = 31 ; total = 231
        self::assertSame('200.00', $invoice->getBaseTotal());
        self::assertSame('31.00', $invoice->getVatTotal());
        self::assertSame('231.00', $invoice->getTotal());
        self::assertSame('TEST/5', $invoice->getFullNumber());
        self::assertSame('ACME', $invoice->getCustomer()->getName());
        self::assertCount(2, $invoice->getLines());
    }

    public function testRejectsInvoiceWithNoLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service(1)->create(new User(), ['customer' => ['name' => 'X', 'taxId' => 'Y'], 'lines' => []]);
    }

    /**
     * An invoice issued at an exact UTC instant, with numbering per series as the real repository does
     * it: $lastNumbers maps series => the highest number already issued in it.
     *
     * @param array<string,int> $lastNumbers
     */
    /** @return array{0:\App\Entity\Invoice, 1:\App\Entity\InvoiceRecord} */
    private function issueAt(string $utc, array $lastNumbers = []): array
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $o) use (&$persisted): void { $persisted[] = $o; });

        $invoices = $this->createStub(InvoiceRepository::class);
        $invoices->method('nextNumber')->willReturnCallback(static fn ($u, string $series) => ($lastNumbers[$series] ?? 0) + 1);
        $customers = $this->createStub(CustomerRepository::class);
        $customers->method('findOneBy')->willReturn(null);
        $records = $this->createStub(InvoiceRecordRepository::class);
        $records->method('lastForUser')->willReturn(null);

        $clock = new \App\Time\BusinessClock(new \Symfony\Component\Clock\MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))));
        $svc = new InvoiceService($em, $invoices, $customers, $records, new VerifactuHasher(), $clock);

        $invoice = $svc->create((new User())->setTaxId('B12345678'), [
            'customer' => ['name' => 'ACME', 'taxId' => 'B87654321'],
            'lines' => [['description' => 'Reparación', 'quantity' => 1, 'unitPrice' => '100.00', 'vatRate' => '21.00']],
        ]);
        $records = array_values(array_filter($persisted, static fn ($o) => $o instanceof \App\Entity\InvoiceRecord));

        return [$invoice, $records[0]];
    }

    /**
     * 23:30 UTC on 31 December is already 1 January in Madrid: the invoice is dated 1 January, goes into
     * the NEW year's series and numbering starts again at 1 — even with 2026 invoices already issued.
     */
    public function testInvoiceAt2330UtcOnNewYearsEveIsTheFirstOfTheNewYear(): void
    {
        [$invoice, $record] = $this->issueAt('2026-12-31 23:30:00', ['2026' => 57]);

        self::assertSame('2027-01-01', $invoice->getIssuedAt()->format('Y-m-d'));
        self::assertSame('2027/1', $invoice->getFullNumber(), 'new series, numbering restarted');

        self::assertSame('01-01-2027', $record->getIssueDate(), 'the fingerprinted issue date is the Spanish one');
        self::assertSame('2027-01-01T00:30:00+01:00', $record->getGeneratedAt());
        self::assertSame((new VerifactuHasher())->fingerprint($record), $record->getHash());
    }

    public function testInvoiceEarlierOnNewYearsEveStaysInTheOldYear(): void
    {
        // 22:30 UTC = 23:30 in Madrid: still 31 December
        [$invoice] = $this->issueAt('2026-12-31 22:30:00', ['2026' => 57]);

        self::assertSame('2026-12-31', $invoice->getIssuedAt()->format('Y-m-d'));
        self::assertSame('2026/58', $invoice->getFullNumber());
    }

    /** Around both daylight-saving changes the date is Madrid's and the offset changes with the season. */
    public function testIssueDateAcrossDaylightSavingChanges(): void
    {
        // spring: 23:30 UTC on 28 March = 00:30 CET on the 29th
        [, $spring] = $this->issueAt('2026-03-28 23:30:00');
        self::assertSame('29-03-2026', $spring->getIssueDate());
        self::assertStringEndsWith('+01:00', $spring->getGeneratedAt());

        // summer: 22:30 UTC on 29 March = 00:30 CEST on the 30th
        [, $summer] = $this->issueAt('2026-03-29 22:30:00');
        self::assertSame('30-03-2026', $summer->getIssueDate());
        self::assertStringEndsWith('+02:00', $summer->getGeneratedAt());

        // autumn: 22:30 UTC on 25 October = 23:30 CET, still the 25th
        [, $autumn] = $this->issueAt('2026-10-25 22:30:00');
        self::assertSame('25-10-2026', $autumn->getIssueDate());
        self::assertStringEndsWith('+01:00', $autumn->getGeneratedAt());
    }
}
