<?php

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests: boot the real app (kernel + firewall + database) and exercise the
 * HTTP endpoints end to end, including authentication and per-user data isolation.
 * ES: Tests de integración: arrancan la app real (kernel + firewall + base de datos) y
 * ejercitan los endpoints HTTP de punta a punta, incluyendo auth y aislamiento por usuario.
 */
class ApiIntegrationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep the same kernel (and its in-memory DB connection) across requests in a test.
        // ES: Mantener el mismo kernel (y su conexión SQLite en memoria) entre peticiones del test.
        $this->client->disableReboot();

        // Fresh in-memory SQLite schema for each test (created from the entity metadata).
        // ES: Esquema SQLite en memoria nuevo en cada test (creado desde los metadatos de las entidades).
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
    }

    private function json(string $method, string $url, ?array $body = null): array
    {
        $this->client->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json'], $body !== null ? json_encode($body) : null);
        $res = $this->client->getResponse();
        return [$res->getStatusCode(), json_decode($res->getContent() ?: 'null', true)];
    }

    private function registerAndLogin(string $email): void
    {
        $this->json('POST', '/api/register', ['email' => $email, 'password' => 'secret123']);
        $this->json('POST', '/api/login', ['email' => $email, 'password' => 'secret123']);
    }

    public function testUnauthenticatedRequestsAreBlocked(): void
    {
        [$status] = $this->json('GET', '/api/transactions');
        self::assertSame(401, $status);
    }

    public function testRegisterLoginMeLogout(): void
    {
        [$status] = $this->json('POST', '/api/register', ['email' => 'ana@test.local', 'password' => 'secret123']);
        self::assertSame(201, $status);

        [$status, $body] = $this->json('POST', '/api/login', ['email' => 'ana@test.local', 'password' => 'secret123']);
        self::assertSame(200, $status);
        self::assertSame('ana@test.local', $body['email']);

        [$status, $body] = $this->json('GET', '/api/me');
        self::assertSame(200, $status);
        self::assertSame('ana@test.local', $body['email']);

        $this->json('POST', '/api/logout');
        [$status] = $this->json('GET', '/api/me');
        self::assertSame(401, $status);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->json('POST', '/api/register', ['email' => 'dup@test.local', 'password' => 'secret123']);
        [$status] = $this->json('POST', '/api/register', ['email' => 'dup@test.local', 'password' => 'secret123']);
        self::assertSame(409, $status);
    }

    public function testImportIsScopedToTheUser(): void
    {
        // User A imports two movements.
        $this->registerAndLogin('a@test.local');
        $csv = "fecha;concepto;importe\n2026-01-05;Compra Mercadona;-52,30\n2026-01-10;Cliente ACME;1210,00\n";
        $this->client->request('POST', '/api/import/csv', [], [], ['CONTENT_TYPE' => 'text/csv'], $csv);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        [$status, $body] = $this->json('GET', '/api/transactions');
        self::assertSame(200, $status);
        self::assertCount(2, $body);

        // User B sees none of A's data.
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('b@test.local');
        [, $body] = $this->json('GET', '/api/transactions');
        self::assertCount(0, $body);
    }

    public function testInvoicingChainIsVerifiableEndToEnd(): void
    {
        $this->registerAndLogin('inv@test.local');

        [$s1, $inv1] = $this->json('POST', '/api/invoices', [
            'series' => '2026',
            'customer' => ['name' => 'ACME', 'taxId' => 'B12345678'],
            'lines' => [['description' => 'Web', 'unitPrice' => '1000.00', 'vatRate' => '21.00']],
        ]);
        self::assertSame(201, $s1);
        self::assertSame('2026/1', $inv1['number']);
        self::assertSame('1210.00', $inv1['total']);
        self::assertNull($inv1['verifactu']['previousHash'], 'first record has no previous hash');
        self::assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $inv1['verifactu']['hash']);

        [, $inv2] = $this->json('POST', '/api/invoices', [
            'series' => '2026',
            'customer' => ['name' => 'Beta', 'taxId' => 'B87654321'],
            'lines' => [['description' => 'Hosting', 'quantity' => 2, 'unitPrice' => '50.00', 'vatRate' => '10.00']],
        ]);
        self::assertSame('2026/2', $inv2['number']);
        // the second record chains to the first (tamper-evidence).
        self::assertSame($inv1['verifactu']['hash'], $inv2['verifactu']['previousHash']);

        // the whole chain verifies intact.
        [$vs, $vr] = $this->json('GET', '/api/invoices/verify');
        self::assertSame(200, $vs);
        self::assertTrue($vr['ok']);
        self::assertSame(2, $vr['count']);

        [, $list] = $this->json('GET', '/api/invoices');
        self::assertCount(2, $list);

        // the QR (SVG) and the RegistroAlta XML render for an owned invoice.
        $id = $inv1['id'];
        $this->client->request('GET', "/api/invoices/$id/qr");
        $qrRes = $this->client->getResponse();
        self::assertSame(200, $qrRes->getStatusCode());
        self::assertStringContainsString('image/svg+xml', (string) $qrRes->headers->get('Content-Type'));
        self::assertStringContainsString('<svg', $qrRes->getContent());

        $this->client->request('GET', "/api/invoices/$id/xml");
        $xmlRes = $this->client->getResponse();
        self::assertSame(200, $xmlRes->getStatusCode());
        self::assertStringContainsString('application/xml', (string) $xmlRes->headers->get('Content-Type'));
        self::assertStringContainsString('<RegistroAlta', $xmlRes->getContent());

        // a second user shares no invoices and has an empty (valid) chain.
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('other@test.local');
        [, $list] = $this->json('GET', '/api/invoices');
        self::assertCount(0, $list);
        [, $vr] = $this->json('GET', '/api/invoices/verify');
        self::assertTrue($vr['ok']);
        self::assertSame(0, $vr['count']);
    }

    public function testOpenBankingReportsDisabledWithoutCredentials(): void
    {
        $this->registerAndLogin('bank@test.local');

        [$s, $body] = $this->json('GET', '/api/bank/status');
        self::assertSame(200, $s);
        self::assertFalse($body['enabled']);

        // enabled-only endpoints return 503 so the frontend can show a disabled state.
        [$s2] = $this->json('GET', '/api/bank/institutions');
        self::assertSame(503, $s2);
    }

    public function testClearAndDeleteAccount(): void
    {
        $this->registerAndLogin('c@test.local');
        $csv = "fecha;concepto;importe\n2026-01-05;Compra;-10,00\n";
        $this->client->request('POST', '/api/import/csv', [], [], ['CONTENT_TYPE' => 'text/csv'], $csv);

        [, $body] = $this->json('POST', '/api/account/clear');
        self::assertSame(1, $body['cleared']);
        [, $body] = $this->json('GET', '/api/transactions');
        self::assertCount(0, $body);

        [$status] = $this->json('DELETE', '/api/account');
        self::assertSame(200, $status);
    }
}
