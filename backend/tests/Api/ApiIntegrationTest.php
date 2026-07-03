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
