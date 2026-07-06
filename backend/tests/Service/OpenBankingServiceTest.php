<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\GoCardlessClient;
use App\Service\OpenBankingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OpenBankingServiceTest extends TestCase
{
    private function service(GoCardlessClient $client, array $existing = []): OpenBankingService
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('existingExternalIds')->willReturn($existing);

        return new OpenBankingService($client, $repo, $this->createStub(EntityManagerInterface::class));
    }

    public function testMapsAGoCardlessTransaction(): void
    {
        $tx = $this->service($this->createStub(GoCardlessClient::class))->toTransaction([
            'transactionId' => 't1',
            'bookingDate' => '2026-06-01',
            'transactionAmount' => ['amount' => '-52.30', 'currency' => 'EUR'],
            'remittanceInformationUnstructured' => 'Compra Mercadona',
        ], new User(), 'acc-1');

        self::assertNotNull($tx);
        self::assertSame('-52.30', $tx->getAmount());
        self::assertSame('Compra Mercadona', $tx->getDescription());
        self::assertSame('EUR', $tx->getCurrency());
        self::assertSame('openbanking', $tx->getImportedFrom());
        self::assertSame('acc-1:t1', $tx->getExternalId());
        self::assertSame('2026-06-01', $tx->getBookedAt()->format('Y-m-d'));
    }

    public function testFallsBackToCreditorNameAndNullsUnmappableRows(): void
    {
        $svc = $this->service($this->createStub(GoCardlessClient::class));

        $named = $svc->toTransaction([
            'transactionId' => 't2', 'bookingDate' => '2026-06-02',
            'transactionAmount' => ['amount' => '1200.00'], 'creditorName' => 'ACME SL',
        ], new User(), 'acc-1');
        self::assertSame('ACME SL', $named->getDescription());
        self::assertSame('EUR', $named->getCurrency()); // defaulted

        // no amount → cannot be mapped
        self::assertNull($svc->toTransaction(['bookingDate' => '2026-06-02'], new User(), 'acc-1'));
    }

    public function testImportPersistsNewAndSkipsDuplicates(): void
    {
        $client = $this->createStub(GoCardlessClient::class);
        $client->method('getRequisition')->willReturn(['status' => 'LN', 'accounts' => ['acc-1']]);
        $client->method('getAccountTransactions')->willReturn(['transactions' => ['booked' => [
            ['transactionId' => 't1', 'bookingDate' => '2026-06-01', 'transactionAmount' => ['amount' => '-52.30']],
            ['transactionId' => 'dup', 'bookingDate' => '2026-06-02', 'transactionAmount' => ['amount' => '10.00']],
            ['transactionId' => 't2', 'bookingDate' => '2026-06-03', 'transactionAmount' => ['amount' => '1200.00']],
            ['bookingDate' => '2026-06-04'], // no amount → dropped, counted in neither total
        ]]]);

        $result = $this->service($client, ['acc-1:dup' => true])->import(new User(), 'req-1');

        self::assertSame(2, $result['imported']);
        self::assertSame(1, $result['skipped']);
    }
}
