<?php

namespace App\Tests\Service;

use App\Entity\InvoiceRecord;
use App\Service\VerifactuChain;
use App\Service\VerifactuHasher;
use PHPUnit\Framework\TestCase;

class VerifactuChainTest extends TestCase
{
    private VerifactuHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new VerifactuHasher();
    }

    /** Build a valid record linked to $previousHash and seal it with its real fingerprint. */
    private function record(int $n, string $total, ?string $previousHash): InvoiceRecord
    {
        $r = (new InvoiceRecord())
            ->setIssuerNif('B12345678')
            ->setFullNumber('TEST/' . $n)
            ->setIssueDate('06-07-2026')
            ->setVatTotal('21.00')
            ->setTotal($total)
            ->setGeneratedAt('2026-07-06T10:0' . $n . ':00+02:00')
            ->setPreviousHash($previousHash);

        return $r->setHash($this->hasher->fingerprint($r));
    }

    /** @return InvoiceRecord[] a valid 3-record chain */
    private function chain(): array
    {
        $r1 = $this->record(1, '100.00', null);
        $r2 = $this->record(2, '200.00', $r1->getHash());
        $r3 = $this->record(3, '300.00', $r2->getHash());
        return [$r1, $r2, $r3];
    }

    public function testFingerprintIsDeterministicAndWellFormed(): void
    {
        $r = $this->record(1, '100.00', null);
        self::assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $r->getHash());
        self::assertSame($r->getHash(), $this->hasher->fingerprint($r), 'same input → same hash');
    }

    public function testEachRecordChainsToThePreviousHash(): void
    {
        [$r1, $r2, $r3] = $this->chain();
        self::assertNull($r1->getPreviousHash());
        self::assertSame($r1->getHash(), $r2->getPreviousHash());
        self::assertSame($r2->getHash(), $r3->getPreviousHash());
    }

    public function testIntactChainVerifies(): void
    {
        $result = (new VerifactuChain($this->hasher))->verify($this->chain());
        self::assertTrue($result['ok']);
        self::assertSame(3, $result['count']);
    }

    public function testTamperedFieldIsDetected(): void
    {
        $chain = $this->chain();
        // change a past invoice's total WITHOUT recomputing its sealed hash
        $chain[1]->setTotal('999.99');

        $result = (new VerifactuChain($this->hasher))->verify($chain);
        self::assertFalse($result['ok']);
        self::assertSame('TEST/2', $result['brokenAt']);
        self::assertSame('record_tampered', $result['reason']);
    }

    public function testResealingBreaksTheLink(): void
    {
        $chain = $this->chain();
        // a forger changes the total AND reseals the hash — but the next record still points at the old hash
        $chain[1]->setTotal('999.99');
        $chain[1]->setHash($this->hasher->fingerprint($chain[1]));

        $result = (new VerifactuChain($this->hasher))->verify($chain);
        self::assertFalse($result['ok']);
        self::assertSame('TEST/3', $result['brokenAt']);
        self::assertSame('previous_hash_mismatch', $result['reason']);
    }
}
