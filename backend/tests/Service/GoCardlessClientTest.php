<?php

namespace App\Tests\Service;

use App\Service\GoCardlessClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Exercises GoCardlessClient against mocked HTTP responses shaped like the documented API.
 * (Not run against the live GoCardless service — no credentials during development.)
 * ES: Ejercita GoCardlessClient contra respuestas HTTP simuladas con la forma de la API documentada.
 */
class GoCardlessClientTest extends TestCase
{
    /** @param MockResponse[] $responses */
    private function client(array $responses): GoCardlessClient
    {
        return new GoCardlessClient(new MockHttpClient($responses), 'secret-id', 'secret-key');
    }

    private function token(): MockResponse
    {
        return new MockResponse(json_encode(['access' => 'tok', 'refresh' => 'ref']), [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function testIsDisabledWithoutCredentials(): void
    {
        self::assertFalse((new GoCardlessClient(new MockHttpClient()))->isEnabled());
        self::assertTrue($this->client([])->isEnabled());
    }

    public function testListInstitutionsAuthenticatesThenReturnsThem(): void
    {
        $client = $this->client([
            $this->token(),
            new MockResponse(json_encode([
                ['id' => 'BBVA_BBVAESMM', 'name' => 'BBVA', 'logo' => 'https://x/bbva.png'],
                ['id' => 'SANTANDER_BSCHESMM', 'name' => 'Santander'],
            ]), ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $list = $client->listInstitutions('es');
        self::assertCount(2, $list);
        self::assertSame('BBVA', $list[0]['name']);
    }

    public function testCreateRequisitionReturnsIdAndLink(): void
    {
        $client = $this->client([
            $this->token(),
            new MockResponse(json_encode(['id' => 'req-123', 'link' => 'https://ob.gocardless.com/psd2/start/req-123']),
                ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $req = $client->createRequisition('BBVA_BBVAESMM', 'https://cuentia.app', 'ref-1');
        self::assertSame('req-123', $req['id']);
        self::assertStringContainsString('gocardless.com', $req['link']);
    }

    public function testGetAccountTransactionsReturnsBookedList(): void
    {
        $client = $this->client([
            $this->token(),
            new MockResponse(json_encode([
                'transactions' => ['booked' => [['transactionId' => 't1'], ['transactionId' => 't2']], 'pending' => []],
            ]), ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $data = $client->getAccountTransactions('acc-1');
        self::assertCount(2, $data['transactions']['booked']);
    }
}
