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

    public function testAuthErrorsCarryStableCodesAndDoNotLeakWhetherAUserExists(): void
    {
        [$s, $b] = $this->json('POST', '/api/register', ['email' => 'not-an-email', 'password' => 'secret123']);
        self::assertSame(400, $s);
        self::assertSame('invalid_email', $b['code']);

        [$s, $b] = $this->json('POST', '/api/register', ['email' => 'weak@test.local', 'password' => 'short']);
        self::assertSame(400, $s);
        self::assertSame('weak_password', $b['code']);

        $this->json('POST', '/api/register', ['email' => 'taken@test.local', 'password' => 'secret123']);
        [$s, $b] = $this->json('POST', '/api/register', ['email' => 'taken@test.local', 'password' => 'secret123']);
        self::assertSame(409, $s);
        self::assertSame('email_taken', $b['code']);

        // Existing user + wrong password …
        [$s1, $b1] = $this->json('POST', '/api/login', ['email' => 'taken@test.local', 'password' => 'wrong-password']);
        // … and a user that doesn't exist at all.
        [$s2, $b2] = $this->json('POST', '/api/login', ['email' => 'ghost@test.local', 'password' => 'wrong-password']);

        self::assertSame(401, $s1);
        self::assertSame(401, $s2);
        self::assertSame('bad_credentials', $b1['code']);
        // Identical response ⇒ an attacker can't tell which emails are registered (no user enumeration).
        self::assertSame($b1, $b2);
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

        $this->client->request('GET', "/api/invoices/$id/pdf");
        $pdfRes = $this->client->getResponse();
        self::assertSame(200, $pdfRes->getStatusCode());
        self::assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', $pdfRes->getContent());

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

    public function testCustomersCrudDeleteGuardAndIsolation(): void
    {
        $this->registerAndLogin('cust@test.local');

        [$s, $c] = $this->json('POST', '/api/customers', ['name' => 'ACME', 'taxId' => 'B1', 'email' => 'a@acme.com']);
        self::assertSame(201, $s);
        self::assertSame('ACME', $c['name']);

        [, $list] = $this->json('GET', '/api/customers');
        self::assertCount(1, $list);

        [$su, $cu] = $this->json('PUT', "/api/customers/{$c['id']}", ['name' => 'ACME SL', 'taxId' => 'B1']);
        self::assertSame(200, $su);
        self::assertSame('ACME SL', $cu['name']);

        // validation: name & taxId required.
        [$sb] = $this->json('POST', '/api/customers', ['name' => '', 'taxId' => '']);
        self::assertSame(400, $sb);

        // an invoice can reference an existing customer by id, and that customer then can't be deleted.
        [$si, $inv] = $this->json('POST', '/api/invoices', [
            'customerId' => $c['id'],
            'lines' => [['description' => 'x', 'unitPrice' => '10.00', 'vatRate' => '21.00']],
        ]);
        self::assertSame(201, $si);
        self::assertSame('ACME SL', $inv['customer']['name']);
        [$sd] = $this->json('DELETE', "/api/customers/{$c['id']}");
        self::assertSame(409, $sd);

        // a customer with no invoices deletes cleanly.
        [, $c2] = $this->json('POST', '/api/customers', ['name' => 'Beta', 'taxId' => 'B2']);
        [$sd2] = $this->json('DELETE', "/api/customers/{$c2['id']}");
        self::assertSame(200, $sd2);
        [, $list2] = $this->json('GET', '/api/customers');
        self::assertCount(1, $list2);

        // another user shares no customers.
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('other2@test.local');
        [, $list3] = $this->json('GET', '/api/customers');
        self::assertCount(0, $list3);
    }

    public function testServicesCatalogCrudAndIsolation(): void
    {
        $this->registerAndLogin('svc@test.local');

        [$s, $svc] = $this->json('POST', '/api/services', ['name' => 'Hora de consultoría', 'unitPrice' => '60', 'vatRate' => '21']);
        self::assertSame(201, $s);
        self::assertSame('60.00', $svc['unitPrice']);
        self::assertSame('21.00', $svc['vatRate']);

        [$sb] = $this->json('POST', '/api/services', ['name' => '']);
        self::assertSame(400, $sb);

        [$su, $svcU] = $this->json('PUT', "/api/services/{$svc['id']}", ['name' => 'Consultoría', 'unitPrice' => '75', 'vatRate' => '21']);
        self::assertSame(200, $su);
        self::assertSame('75.00', $svcU['unitPrice']);

        [, $list] = $this->json('GET', '/api/services');
        self::assertCount(1, $list);

        [$sd] = $this->json('DELETE', "/api/services/{$svc['id']}");
        self::assertSame(200, $sd);
        [, $list2] = $this->json('GET', '/api/services');
        self::assertCount(0, $list2);

        // isolation: another user sees no services.
        [, $svc2] = $this->json('POST', '/api/services', ['name' => 'X', 'unitPrice' => '10']);
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('other3@test.local');
        [, $l3] = $this->json('GET', '/api/services');
        self::assertCount(0, $l3);
        // and can't touch someone else's service.
        [$sf] = $this->json('DELETE', "/api/services/{$svc2['id']}");
        self::assertSame(404, $sf);
    }

    public function testQuotesCreateConvertToInvoiceAndPdf(): void
    {
        $this->registerAndLogin('quote@test.local');

        [, $c] = $this->json('POST', '/api/customers', ['name' => 'ACME', 'taxId' => 'B1']);

        [$s, $q] = $this->json('POST', '/api/quotes', [
            'customerId' => $c['id'],
            'validUntil' => '2026-12-31',
            'lines' => [['description' => 'Web', 'unitPrice' => '1000.00', 'vatRate' => '21.00']],
        ]);
        self::assertSame(201, $s);
        self::assertSame('draft', $q['status']);
        self::assertSame('1210.00', $q['total']);

        [, $list] = $this->json('GET', '/api/quotes');
        self::assertCount(1, $list);

        // status transition.
        [$ss, $qs] = $this->json('POST', "/api/quotes/{$q['id']}/status", ['status' => 'accepted']);
        self::assertSame(200, $ss);
        self::assertSame('accepted', $qs['status']);

        // convert → a real Verifactu invoice.
        [$scv, $conv] = $this->json('POST', "/api/quotes/{$q['id']}/convert", null);
        self::assertSame(201, $scv);
        self::assertNotEmpty($conv['invoiceNumber']);

        [, $qd] = $this->json('GET', "/api/quotes/{$q['id']}");
        self::assertSame('converted', $qd['status']);
        self::assertSame($conv['invoiceNumber'], $qd['convertedInvoice']['number']);

        [, $invoices] = $this->json('GET', '/api/invoices');
        self::assertCount(1, $invoices);

        // converting again is idempotent (no duplicate invoice).
        [, $conv2] = $this->json('POST', "/api/quotes/{$q['id']}/convert", null);
        self::assertSame($conv['invoiceNumber'], $conv2['invoiceNumber']);
        [, $invoices2] = $this->json('GET', '/api/invoices');
        self::assertCount(1, $invoices2);

        // the quote PDF renders.
        $this->client->request('GET', "/api/quotes/{$q['id']}/pdf");
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('%PDF', $this->client->getResponse()->getContent());
    }

    public function testElectricalCertificatesCrudPdfAndIsolation(): void
    {
        $this->registerAndLogin('elec@test.local');

        [$s, $cert] = $this->json('POST', '/api/certificates', [
            'address' => 'C/ Major 1', 'titularName' => 'Ana Pérez', 'companyName' => 'Electro SL',
            'useType' => 'vivienda', 'installationType' => 'nueva',
            'maxPower' => '5.75', 'voltage' => 230, 'supplyType' => 'monofasico', 'earthingScheme' => 'TT',
        ]);
        self::assertSame(201, $s);
        self::assertSame('vivienda', $cert['useType']);
        self::assertSame('5.750', $cert['maxPower']);
        self::assertSame(230, $cert['voltage']);

        // required-field validation.
        [$sb] = $this->json('POST', '/api/certificates', ['address' => '', 'titularName' => '', 'companyName' => '']);
        self::assertSame(400, $sb);

        [$su, $cu] = $this->json('PUT', "/api/certificates/{$cert['id']}", [
            'address' => 'C/ Major 2', 'titularName' => 'Ana Pérez', 'companyName' => 'Electro SL', 'installationType' => 'reforma',
        ]);
        self::assertSame(200, $su);
        self::assertSame('reforma', $cu['installationType']);

        [, $list] = $this->json('GET', '/api/certificates');
        self::assertCount(1, $list);

        // the CIE PDF renders.
        $this->client->request('GET', "/api/certificates/{$cert['id']}/pdf");
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('%PDF', $this->client->getResponse()->getContent());

        [$sd] = $this->json('DELETE', "/api/certificates/{$cert['id']}");
        self::assertSame(200, $sd);

        // isolation: another user can't see or delete this user's certificate.
        [, $cert2] = $this->json('POST', '/api/certificates', ['address' => 'X', 'titularName' => 'Y', 'companyName' => 'Z']);
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('other4@test.local');
        [, $l3] = $this->json('GET', '/api/certificates');
        self::assertCount(0, $l3);
        [$sf] = $this->json('DELETE', "/api/certificates/{$cert2['id']}");
        self::assertSame(404, $sf);
    }

    public function testInstallationDesignerComputeCrudAndIsolation(): void
    {
        $this->registerAndLogin('elec2@test.local');

        // stateless compute
        [$sc, $res] = $this->json('POST', '/api/installations/compute', [
            'rooms' => [['type' => 'salon', 'area' => 20], ['type' => 'cocina', 'area' => 9], ['type' => 'bano', 'area' => 4]],
        ]);
        self::assertSame(200, $sc);
        self::assertSame('basico', $res['grade']);
        self::assertNotEmpty($res['circuits']);

        // save a design; the result is derived from the stored input
        [$s, $inst] = $this->json('POST', '/api/installations', [
            'name' => 'Piso Ana',
            'rooms' => [['type' => 'salon', 'area' => 25], ['type' => 'dormitorio', 'area' => 14]],
            'loads' => ['aire' => true],
        ]);
        self::assertSame(201, $s);
        self::assertSame('Piso Ana', $inst['name']);
        self::assertSame('elevado', $inst['result']['grade']); // A/A ⇒ elevado

        [$sb] = $this->json('POST', '/api/installations', ['name' => '']);
        self::assertSame(400, $sb);

        [, $list] = $this->json('GET', '/api/installations');
        self::assertCount(1, $list);

        [, $d] = $this->json('GET', "/api/installations/{$inst['id']}");
        self::assertSame('elevado', $d['result']['grade']);

        [$su, $u] = $this->json('PUT', "/api/installations/{$inst['id']}", [
            'name' => 'Piso Ana 2', 'rooms' => [['type' => 'salon', 'area' => 20]],
        ]);
        self::assertSame(200, $su);
        self::assertSame('Piso Ana 2', $u['name']);

        [$sd] = $this->json('DELETE', "/api/installations/{$inst['id']}");
        self::assertSame(200, $sd);

        // isolation
        [, $inst2] = $this->json('POST', '/api/installations', ['name' => 'X', 'rooms' => []]);
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('other5@test.local');
        [, $l3] = $this->json('GET', '/api/installations');
        self::assertCount(0, $l3);
        [$sf] = $this->json('DELETE', "/api/installations/{$inst2['id']}");
        self::assertSame(404, $sf);
    }

    public function testInstallationLayoutCableAndPersistence(): void
    {
        $this->registerAndLogin('elec3@test.local');
        $layout = [
            'panel' => ['x' => 0, 'y' => 0],
            'devices' => [['type' => 'socket', 'x' => 3, 'y' => 0], ['type' => 'light', 'x' => 0, 'y' => 4]],
        ];

        // compute includes cable measured from the layout
        [$sc, $res] = $this->json('POST', '/api/installations/compute', [
            'rooms' => [['type' => 'salon', 'area' => 20]], 'layout' => $layout,
        ]);
        self::assertSame(200, $sc);
        self::assertArrayHasKey('layoutCable', $res);
        self::assertSame(2, $res['layoutCable']['devices']);

        // saving persists the layout; detail recomputes the exact cable from it
        [, $inst] = $this->json('POST', '/api/installations', [
            'name' => 'Con plano', 'rooms' => [['type' => 'salon', 'area' => 20]], 'layout' => $layout,
        ]);
        self::assertArrayHasKey('layoutCable', $inst['result']);

        [, $d] = $this->json('GET', "/api/installations/{$inst['id']}");
        self::assertCount(2, $d['layout']['devices']);
        self::assertArrayHasKey('layoutCable', $d['result']);
    }

    public function testComputeReportsWhichPointsOfUseTheDrawnPlanIsMissing(): void
    {
        $this->registerAndLogin('elec5@test.local');
        $kitchen = ['type' => 'cocina', 'points' => [
            ['x' => 0, 'y' => 0], ['x' => 4, 'y' => 0], ['x' => 4, 'y' => 3], ['x' => 0, 'y' => 3],
        ]];

        // a kitchen with a single light and nothing else cannot comply with tabla 2
        [$s, $r] = $this->json('POST', '/api/installations/compute', [
            'rooms' => [['type' => 'cocina', 'area' => 12]],
            'layout' => ['panel' => ['x' => 0, 'y' => 0], 'rooms' => [$kitchen], 'devices' => [
                ['type' => 'light', 'x' => 2, 'y' => 1.5],
            ]],
        ]);
        self::assertSame(200, $s);
        self::assertTrue($r['validation']['checked']);
        self::assertFalse($r['validation']['compliant']);
        self::assertGreaterThan(0, $r['validation']['missingTotal']);

        // the circuit a socket belongs to survives the round-trip and is honoured by the validator
        [, $inst] = $this->json('POST', '/api/installations', [
            'name' => 'Cocina', 'rooms' => [['type' => 'bano', 'area' => 4]],
            'layout' => ['panel' => ['x' => 0, 'y' => 0], 'devices' => [
                ['type' => 'socket', 'x' => 1, 'y' => 1, 'circuit' => 'C5'],
                ['type' => 'socket', 'x' => 2, 'y' => 1, 'circuit' => 'nope'],
            ], 'rooms' => []],
        ]);
        [, $d] = $this->json('GET', "/api/installations/{$inst['id']}");
        self::assertSame('C5', $d['layout']['devices'][0]['circuit']);
        self::assertArrayNotHasKey('circuit', $d['layout']['devices'][1], 'an unknown circuit is dropped');
    }

    public function testInstallationBackgroundPlanIsStoredAndSanitised(): void
    {
        $this->registerAndLogin('elec4@test.local');
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';

        // a calibrated background survives the round-trip, in metres
        [, $inst] = $this->json('POST', '/api/installations', [
            'name' => 'Con plano real', 'rooms' => [],
            'background' => ['src' => $png, 'x' => 1, 'y' => 1.5, 'w' => 8.19, 'h' => 6.2, 'opacity' => 0.6],
        ]);
        [, $d] = $this->json('GET', "/api/installations/{$inst['id']}");
        self::assertSame($png, $d['background']['src']);
        self::assertSame(8.19, $d['background']['w']);

        // no background at all is fine
        [$s, $none] = $this->json('POST', '/api/installations', ['name' => 'Sin plano', 'rooms' => []]);
        self::assertSame(201, $s);
        self::assertNull($none['background']);

        // anything that isn't an inline image is rejected loudly — never stored, never silently dropped
        [$s, $bad] = $this->json('POST', '/api/installations', [
            'name' => 'Con URL', 'rooms' => [],
            'background' => ['src' => 'https://evil.example/track.png'],
        ]);
        self::assertSame(400, $s);
        self::assertSame('background_not_inline_image', $bad['code']);

        // and so is an oversize image: a silent null would make the traced plan vanish on reload
        [$s, $big] = $this->json('POST', '/api/installations', [
            'name' => 'Plano enorme', 'rooms' => [],
            'background' => ['src' => 'data:image/png;base64,'.str_repeat('A', 3_000_001)],
        ]);
        self::assertSame(400, $s);
        self::assertSame('background_too_large', $big['code']);
    }

    public function testPerUserApiCredentialsByok(): void
    {
        $this->registerAndLogin('byok@test.local');

        [$s, $st] = $this->json('GET', '/api/account/integrations');
        self::assertSame(200, $s);
        self::assertFalse($st['anthropic']['configured']);
        self::assertFalse($st['gocardless']['configured']);

        // open banking is disabled until the user provides credentials
        [, $bank] = $this->json('GET', '/api/bank/status');
        self::assertFalse($bank['enabled']);

        // save an Anthropic key — status reports it configured with a masked hint, never the key
        [, $st] = $this->json('PUT', '/api/account/integrations/anthropic', ['key' => 'sk-ant-secret-9876']);
        self::assertTrue($st['anthropic']['configured']);
        self::assertSame('…9876', $st['anthropic']['hint']);
        self::assertArrayNotHasKey('key', $st['anthropic']);

        // saving GoCardless credentials enables open banking FOR THIS USER
        [, $st] = $this->json('PUT', '/api/account/integrations/gocardless', ['secretId' => 'gc-id-1', 'secretKey' => 'gc-key-1']);
        self::assertTrue($st['gocardless']['configured']);
        [, $bank] = $this->json('GET', '/api/bank/status');
        self::assertTrue($bank['enabled']);

        // removing them disables it again
        [$sd] = $this->json('DELETE', '/api/account/integrations/gocardless');
        self::assertSame(200, $sd);
        [, $bank] = $this->json('GET', '/api/bank/status');
        self::assertFalse($bank['enabled']);

        // another user shares nothing
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('byok2@test.local');
        [, $st2] = $this->json('GET', '/api/account/integrations');
        self::assertFalse($st2['anthropic']['configured']);
        self::assertFalse($st2['gocardless']['configured']);
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
