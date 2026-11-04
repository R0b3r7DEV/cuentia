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
        // Rate limiters (login throttling, sign-ups) start empty in every test. / Limitadores vacíos en cada test.
        static::getContainer()->get('cache.rate_limiter')->clear();

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

        [$out] = $this->json('POST', '/api/logout');
        self::assertSame(204, $out, 'no redirect: a cross-origin redirect made the browser\'s logout fetch fail');
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
        // This test exercises the full Verifactu artefacts, so put the issuer in demo mode.
        $this->json('PUT', '/api/account/settings', ['billingMode' => 'verifactu']);

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

    public function testStandardModeIssuesInvoicesWithoutVerifactuArtefacts(): void
    {
        $this->registerAndLogin('std@test.local');

        // default mode is standard
        [$ss, $settings] = $this->json('GET', '/api/account/settings');
        self::assertSame(200, $ss);
        self::assertSame('standard', $settings['billingMode']);

        [$s1, $inv] = $this->json('POST', '/api/invoices', [
            'customer' => ['name' => 'Vecino', 'taxId' => 'B1'],
            'lines' => [['description' => 'Reparación cuadro', 'unitPrice' => '100.00', 'vatRate' => '21.00']],
        ]);
        self::assertSame(201, $s1);
        // no demonstration artefacts are surfaced
        self::assertFalse($inv['demoMode']);
        self::assertNull($inv['verifactu']);

        // but the hash chain still runs underneath — verify is OK over the record just created
        [$vs, $vr] = $this->json('GET', '/api/invoices/verify');
        self::assertSame(200, $vs);
        self::assertTrue($vr['ok']);
        self::assertSame(1, $vr['count']);

        // QR and XML are refused in standard mode; the invoice PDF is still downloadable
        $id = $inv['id'];
        $this->client->request('GET', "/api/invoices/$id/qr");
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', "/api/invoices/$id/xml");
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', "/api/invoices/$id/pdf");
        $pdf = $this->client->getResponse();
        self::assertSame(200, $pdf->getStatusCode());
        self::assertStringStartsWith('%PDF', (string) $pdf->getContent());
    }

    public function testChangingBillingModeDoesNotBreakTheChainOverPreexistingInvoices(): void
    {
        // The required guarantee: the fingerprint does not depend on the billing mode, so invoices issued
        // BEFORE a mode switch still verify afterwards.
        $this->registerAndLogin('switch@test.local');
        foreach (['10.00', '20.00', '30.00'] as $p) {
            $this->json('POST', '/api/invoices', [
                'customer' => ['name' => 'C', 'taxId' => 'B1'],
                'lines' => [['description' => 'x', 'unitPrice' => $p, 'vatRate' => '21.00']],
            ]);
        }
        [, $before] = $this->json('GET', '/api/invoices/verify');
        self::assertTrue($before['ok']);
        self::assertSame(3, $before['count']);

        [$s] = $this->json('PUT', '/api/account/settings', ['billingMode' => 'verifactu']);
        self::assertSame(200, $s);

        [, $after] = $this->json('GET', '/api/invoices/verify');
        self::assertTrue($after['ok'], 'the chain must survive a billing-mode change');
        self::assertSame(3, $after['count']);

        // the artefacts are now exposed for those same invoices
        [, $list] = $this->json('GET', '/api/invoices');
        $id = $list[0]['id'];
        $this->client->request('GET', "/api/invoices/$id/qr");
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testBillingSettingsPersistIssuerProfileAndRejectBadMode(): void
    {
        $this->registerAndLogin('issuer@test.local');
        [$s, $d] = $this->json('PUT', '/api/account/settings', [
            'billingMode'   => 'standard',
            'businessName'  => 'Instalaciones Pérez SL',
            'fiscalAddress' => 'C/ Major 1, València',
            'taxId'         => 'B99999999',
        ]);
        self::assertSame(200, $s);
        self::assertSame('Instalaciones Pérez SL', $d['businessName']);
        self::assertSame('B99999999', $d['taxId']);

        // an unknown mode falls back to standard, never persists garbage
        [, $d2] = $this->json('PUT', '/api/account/settings', ['billingMode' => 'nonsense']);
        self::assertSame('standard', $d2['billingMode']);
    }

    public function testWorkOrderConvertsToInvoiceIdempotently(): void
    {
        $this->registerAndLogin('parte@test.local');

        [$sc, $wo] = $this->json('POST', '/api/work-orders', [
            'title'      => 'Avería en cuadro',
            'customer'   => ['name' => 'Comunidad Sol', 'taxId' => 'H1'],
            'laborHours' => '2', 'laborRate' => '35',
            'lines'      => [['description' => 'Magnetotérmico 16A', 'quantity' => 2, 'unitPrice' => '12.00', 'vatRate' => '21.00']],
        ]);
        self::assertSame(201, $sc);
        self::assertSame('pendiente', $wo['status']);
        $id = $wo['id'];

        // only a finished order can be invoiced
        [$early, $err] = $this->json('POST', "/api/work-orders/$id/convert");
        self::assertSame(409, $early);
        self::assertSame('not_finished', $err['error']);
        $this->json('POST', "/api/work-orders/$id/status", ['status' => 'en_curso']);
        $this->json('POST', "/api/work-orders/$id/status", ['status' => 'terminado', 'withoutSignature' => true]);

        // convert once → a real invoice; the order becomes 'facturado'
        [$c1, $inv1] = $this->json('POST', "/api/work-orders/$id/convert");
        self::assertSame(201, $c1);
        $invoiceId = $inv1['invoiceId'];

        // converting again returns the SAME invoice and never duplicates
        [$c2, $inv2] = $this->json('POST', "/api/work-orders/$id/convert");
        self::assertSame(201, $c2);
        self::assertSame($invoiceId, $inv2['invoiceId'], 'a work order converts to exactly one invoice');

        [, $invoices] = $this->json('GET', '/api/invoices');
        self::assertCount(1, $invoices, 'converting twice must not create two invoices');

        // the invoice carries materials (2 × 12.00 = 24.00 + 21% = 29.04) + labour (2h × 35 = 70.00 + 21% = 84.70)
        [, $detail] = $this->json('GET', "/api/invoices/$invoiceId");
        self::assertCount(2, $detail['lines']);
        self::assertSame('113.74', $detail['total']);

        // an invoiced order is immutable
        [$us] = $this->json('PUT', "/api/work-orders/$id", ['title' => 'x']);
        self::assertSame(409, $us);
        [$ds] = $this->json('DELETE', "/api/work-orders/$id");
        self::assertSame(409, $ds);
    }

    public function testWorkOrdersAreScopedPerUser(): void
    {
        $this->registerAndLogin('woA@test.local');
        [, $wo] = $this->json('POST', '/api/work-orders', [
            'title' => 'Mío', 'customer' => ['name' => 'C', 'taxId' => 'B1'],
            'lines' => [['description' => 'x', 'unitPrice' => '10.00', 'vatRate' => '21.00']],
        ]);
        $id = $wo['id'];

        $this->json('POST', '/api/logout');
        $this->registerAndLogin('woB@test.local');
        [, $list] = $this->json('GET', '/api/work-orders');
        self::assertCount(0, $list, 'a user never sees another user\'s work orders');
        [$gs] = $this->json('GET', "/api/work-orders/$id");
        self::assertSame(404, $gs);
    }

    /** Multipart upload of one photo; returns [status, decoded body]. */
    private function uploadPhoto(int $id, string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'wo');
        file_put_contents($path, $bytes);
        $this->client->request('POST', "/api/work-orders/$id/photos", [], [
            'photo' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true),
        ]);
        $res = $this->client->getResponse();

        return [$res->getStatusCode(), json_decode($res->getContent() ?: 'null', true)];
    }

    /**
     * The whole job at the site: start, photo, signature, finish, PDF, invoice once — and the invoice hash
     * chain still verifies afterwards.
     */
    public function testWorkOrderFullLifecycleAtTheSite(): void
    {
        $this->registerAndLogin('obra@test.local');
        [, $wo] = $this->json('POST', '/api/work-orders', [
            'title' => 'Enchufe quemado', 'customer' => ['name' => 'María', 'taxId' => '12345678Z'],
            'laborHours' => '1.5', 'laborRate' => '40',
            'lines' => [['description' => 'Base schuko', 'quantity' => 1, 'unitPrice' => '6.50', 'vatRate' => '21.00']],
        ]);
        $id = $wo['id'];
        self::assertSame(['en_curso'], $wo['transitions']);
        // 6.50 + 60.00 = 66.50 base; VAT 1.37 + 12.60 = 13.97; total 80.47
        self::assertSame(['baseCents' => 6650, 'vatCents' => 1397, 'totalCents' => 8047], $wo['totals']);

        // a PUT cannot move the status: only /status can
        [, $same] = $this->json('PUT', "/api/work-orders/$id", ['status' => 'facturado']);
        self::assertSame('pendiente', $same['status']);

        [$bad, $err] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'terminado']);
        self::assertSame(409, $bad);
        self::assertSame('invalid_transition', $err['error']);

        [, $started] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'en_curso']);
        self::assertSame('en_curso', $started['status']);

        // a photo: stored re-encoded, served back as a JPEG with nosniff
        [$ps, $photo] = $this->uploadPhoto($id, \App\Tests\Service\ImageSanitizerTest::jpeg(120, 90));
        self::assertSame(201, $ps);
        $this->client->request('GET', $photo['url']);
        $img = $this->client->getResponse();
        self::assertSame(200, $img->getStatusCode());
        self::assertSame('image/jpeg', $img->headers->get('Content-Type'));
        self::assertSame('nosniff', $img->headers->get('X-Content-Type-Options'));

        // a fake "photo" is refused with a code, not an exception message
        [$fs, $fake] = $this->uploadPhoto($id, '<?php echo 1; ?>');
        self::assertSame(400, $fs);
        self::assertSame('invalid_image', $fake['error']);

        // finishing without signature is refused; with it, accepted
        [$ns, $nerr] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'terminado']);
        self::assertSame(409, $ns);
        self::assertSame('signature_required', $nerr['error']);

        $png = 'data:image/png;base64,' . base64_encode(\App\Tests\Service\ImageSanitizerTest::png(300, 120, true));
        [$ss, $signed] = $this->json('PUT', "/api/work-orders/$id/signature", ['png' => $png, 'signedBy' => 'María']);
        self::assertSame(200, $ss);
        self::assertTrue($signed['signature']['signed']);
        self::assertSame('María', $signed['signature']['signedBy']);

        [, $done] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'terminado']);
        self::assertSame('terminado', $done['status']);
        self::assertFalse($done['editable']);

        // finished ⇒ locked: no edits, no new photos
        [$es] = $this->json('PUT', "/api/work-orders/$id", ['title' => 'otro']);
        self::assertSame(409, $es);
        [$lp] = $this->uploadPhoto($id, \App\Tests\Service\ImageSanitizerTest::jpeg());
        self::assertSame(409, $lp);

        // the signed PDF downloads
        $this->client->request('GET', "/api/work-orders/$id/pdf");
        $pdf = $this->client->getResponse();
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', (string) $pdf->getContent());

        // invoice it twice → one invoice, with the right total
        [, $inv1] = $this->json('POST', "/api/work-orders/$id/convert");
        [, $inv2] = $this->json('POST', "/api/work-orders/$id/convert");
        self::assertSame($inv1['invoiceId'], $inv2['invoiceId']);
        [, $invoice] = $this->json('GET', '/api/invoices/' . $inv1['invoiceId']);
        self::assertSame('80.47', $invoice['total'], 'the invoice matches the work order totals to the cent');

        // the hash chain is intact after all of it
        [$vs, $vr] = $this->json('GET', '/api/invoices/verify');
        self::assertSame(200, $vs);
        self::assertTrue($vr['ok']);
    }

    public function testReopeningAFinishedOrderDropsItsSignature(): void
    {
        $this->registerAndLogin('reabrir@test.local');
        [, $wo] = $this->json('POST', '/api/work-orders', ['title' => 'x', 'customer' => ['name' => 'C', 'taxId' => 'B1'], 'status' => 'en_curso']);
        $id = $wo['id'];
        $png = base64_encode(\App\Tests\Service\ImageSanitizerTest::png());
        $this->json('PUT', "/api/work-orders/$id/signature", ['png' => $png, 'signedBy' => 'C']);
        $this->json('POST', "/api/work-orders/$id/status", ['status' => 'terminado']);

        [, $reopened] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'en_curso']);
        self::assertSame('en_curso', $reopened['status']);
        self::assertFalse($reopened['signature']['signed']);
        [$gs] = $this->json('GET', "/api/work-orders/$id/signature");
        self::assertSame(404, $gs);
    }

    /** IDOR: another user's photo, signature, PDF and actions are all 404, even knowing the ids. */
    public function testPhotosSignatureAndPdfAreScopedPerUser(): void
    {
        $this->registerAndLogin('fotoA@test.local');
        [, $wo] = $this->json('POST', '/api/work-orders', ['title' => 'x', 'customer' => ['name' => 'C', 'taxId' => 'B1'], 'status' => 'en_curso']);
        $id = $wo['id'];
        [, $photo] = $this->uploadPhoto($id, \App\Tests\Service\ImageSanitizerTest::jpeg());
        $this->json('PUT', "/api/work-orders/$id/signature", ['png' => base64_encode(\App\Tests\Service\ImageSanitizerTest::png()), 'signedBy' => 'C']);

        $this->json('POST', '/api/logout');
        $this->registerAndLogin('fotoB@test.local');
        [, $mine] = $this->json('POST', '/api/work-orders', ['title' => 'y', 'customer' => ['name' => 'D', 'taxId' => 'B2'], 'status' => 'en_curso']);

        foreach ([$photo['url'], "/api/work-orders/$id/signature", "/api/work-orders/$id/pdf"] as $url) {
            $this->client->request('GET', $url);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $url);
        }
        // the photo id under B's OWN order does not resolve either
        $this->client->request('GET', sprintf('/api/work-orders/%d/photos/%d', $mine['id'], $photo['id']));
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        [$ds] = $this->json('DELETE', $photo['url']);
        self::assertSame(404, $ds);
        [$ss] = $this->json('POST', "/api/work-orders/$id/status", ['status' => 'pendiente']);
        self::assertSame(404, $ss);
    }

    public function testDefaultLabourRateFromAccountSettings(): void
    {
        $this->registerAndLogin('tarifa@test.local');
        [$s, $settings] = $this->json('PUT', '/api/account/settings', ['defaultLaborRate' => '38,50']);
        self::assertSame(200, $s);
        self::assertSame('38.50', $settings['defaultLaborRate']);

        [, $wo] = $this->json('POST', '/api/work-orders', ['title' => 'x', 'customer' => ['name' => 'C', 'taxId' => 'B1']]);
        self::assertSame('38.50', $wo['laborRate']);

        [$bad] = $this->json('PUT', '/api/account/settings', ['defaultLaborRate' => '-5']);
        self::assertSame(400, $bad);
    }

    /** Freeze the app's business clock at a UTC instant; returns the clock so the test can move it. */
    private function freezeClockAt(string $utc): \Symfony\Component\Clock\MockClock
    {
        $mock = new \Symfony\Component\Clock\MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC')));
        static::getContainer()->set(\App\Time\BusinessClock::class, new \App\Time\BusinessClock($mock));

        return $mock;
    }

    /**
     * New Year's Eve in Madrid, end to end: an invoice at 23:00 Madrid time stays in 2026; one at 00:30
     * on 1 January (23:30 UTC on the 31st) is dated 2027 and starts the 2027 series at number 1. The first
     * record is rewritten exactly as the code BEFORE this fix stored it (generatedAt in UTC, "+00:00"), so
     * the chain mixes old and new records — and GET /api/invoices/verify must still say ok.
     */
    public function testNewYearsEveInvoicesAndTheChainOverPreexistingRecords(): void
    {
        $clock = $this->freezeClockAt('2026-12-31 22:00:00'); // 23:00 in Madrid
        $this->registerAndLogin('nochevieja@test.local');
        $this->json('PUT', '/api/account/settings', ['taxId' => 'B12345678']);

        $line = ['description' => 'Reparación', 'unitPrice' => '100.00', 'vatRate' => '21.00'];
        [$s1, $old] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'ACME', 'taxId' => 'B1'], 'lines' => [$line]]);
        self::assertSame(201, $s1);
        self::assertSame('2026/1', $old['number']);

        // what the pre-fix code stored for that invoice: the same fields, generatedAt in UTC
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $record = $em->getRepository(\App\Entity\InvoiceRecord::class)->findOneBy(['fullNumber' => '2026/1']);
        $record->setGeneratedAt('2026-12-31T22:00:00+00:00');
        $record->setHash((new \App\Service\VerifactuHasher())->fingerprint($record));
        $em->flush();

        $clock->modify('+90 minutes'); // 23:30 UTC = 00:30 on 1 January in Madrid
        [, $new] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'ACME', 'taxId' => 'B1'], 'lines' => [$line]]);
        self::assertSame('2027/1', $new['number'], 'new year in Spain: new series, numbering restarted');

        [, $list] = $this->json('GET', '/api/invoices');
        $dates = array_column($list, 'issuedAt', 'number');
        self::assertSame('2026-12-31', $dates['2026/1']);
        self::assertSame('2027-01-01', $dates['2027/1']);

        $em->clear();
        $newRecord = $em->getRepository(\App\Entity\InvoiceRecord::class)->findOneBy(['fullNumber' => '2027/1']);
        self::assertSame('01-01-2027', $newRecord->getIssueDate());
        self::assertSame('2027-01-01T00:30:00+01:00', $newRecord->getGeneratedAt());

        [$vs, $vr] = $this->json('GET', '/api/invoices/verify');
        self::assertSame(200, $vs);
        self::assertTrue($vr['ok'], 'old (UTC) and new (Madrid) records verify together');
        self::assertSame(2, $vr['count']);
    }

    /** The time typed on the form is Spanish time; stored in UTC; shown back as typed. */
    public function testWorkOrderTimesRoundTripInSpanishTime(): void
    {
        $this->freezeClockAt('2026-10-25 22:30:00'); // 23:30 in Madrid, the day the clocks went back
        $this->registerAndLogin('hora@test.local');

        [, $wo] = $this->json('POST', '/api/work-orders', [
            'title' => 'Avería', 'customer' => ['name' => 'C', 'taxId' => 'B1'], 'status' => 'en_curso',
            'scheduledAt' => '2026-07-01T10:30',
        ]);
        self::assertSame('2026-07-01 10:30', $wo['scheduledAt'], 'shown back as typed');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $stored = $em->getRepository(\App\Entity\WorkOrder::class)->find($wo['id'])->getScheduledAt();
        self::assertSame('2026-07-01 08:30', $stored->format('Y-m-d H:i'), 'stored as the UTC instant (summer, UTC+2)');

        // createdAt was taken now() — the real clock — so it is just shown in Madrid's zone
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $wo['createdAt']);
    }

    /** Multipart upload of a logo; returns [status, decoded body]. */
    private function uploadLogo(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $bytes);
        $this->client->request('POST', '/api/account/logo', [], [
            'logo' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'logo.png', 'image/png', null, true),
        ]);
        $res = $this->client->getResponse();

        return [$res->getStatusCode(), json_decode($res->getContent() ?: 'null', true)];
    }

    /** "Mis datos" for a self-employed electrician: what is missing, the IBAN check, the logo, and where it all shows up. */
    public function testMyDataProfileLogoAndWhereTheyAreUsed(): void
    {
        $this->registerAndLogin('autonomo@test.local');

        // a new account is missing everything an invoice and a CIE need
        [, $empty] = $this->json('GET', '/api/account/settings');
        self::assertSame(['businessName', 'taxId', 'fiscalAddress'], $empty['missing']['invoice']);
        self::assertSame(['businessName', 'taxId', 'installerRegNumber'], $empty['missing']['certificate']);
        self::assertNull($empty['logoUrl']);

        // a mistyped IBAN is refused and nothing is half-saved
        [$bad, $err] = $this->json('PUT', '/api/account/settings', ['iban' => 'ES91 2100 0418 4502 0005 1333']);
        self::assertSame(400, $bad);
        self::assertSame('invalid_iban', $err['error']);

        [$ok, $saved] = $this->json('PUT', '/api/account/settings', [
            'businessName' => 'José Pérez García', 'taxId' => '12345678Z', 'fiscalAddress' => 'C/ Major 1, Castelló',
            'installerRegNumber' => 'CV-12345', 'phone' => '600 123 456', 'iban' => 'es91 2100 0418 4502 0005 1332',
        ]);
        self::assertSame(200, $ok);
        self::assertSame('ES91 2100 0418 4502 0005 1332', $saved['iban']);
        self::assertSame([], $saved['missing']['invoice']);
        self::assertSame([], $saved['missing']['certificate']);

        // logo: sanitised, served back as PNG with nosniff; a fake one is refused
        [$fake] = $this->uploadLogo('<svg onload="alert(1)"/>');
        self::assertSame(400, $fake);
        [$ls, $withLogo] = $this->uploadLogo(\App\Tests\Service\ImageSanitizerTest::png(1200, 300, true));
        self::assertSame(200, $ls);
        self::assertSame('/api/account/logo', $withLogo['logoUrl']);
        $this->client->request('GET', '/api/account/logo');
        $logo = $this->client->getResponse();
        self::assertSame('image/png', $logo->headers->get('Content-Type'));
        self::assertSame('nosniff', $logo->headers->get('X-Content-Type-Options'));
        self::assertSame([600, 150], array_slice(getimagesizefromstring((string) $logo->getContent()), 0, 2));

        // the invoice PDF carries the logo (an image) — and issuing it leaves the hash chain intact
        [, $inv] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'ACME', 'taxId' => 'B1'], 'lines' => [['description' => 'x', 'unitPrice' => '10.00', 'vatRate' => '21.00']]]);
        $this->client->request('GET', '/api/invoices/' . $inv['id'] . '/pdf');
        self::assertGreaterThanOrEqual(1, substr_count((string) $this->client->getResponse()->getContent(), '/Subtype /Image'));
        self::assertTrue($this->json('GET', '/api/invoices/verify')[1]['ok']);

        // a new CIE with the company left blank takes it from "Mis datos"
        [$cs, $cie] = $this->json('POST', '/api/certificates', ['address' => 'C/ Mar 3, Castelló', 'titularName' => 'María López']);
        self::assertSame(201, $cs);
        self::assertSame('José Pérez García', $cie['companyName']);
        self::assertSame('12345678Z', $cie['companyNif']);
        self::assertSame('CV-12345', $cie['companyRegNumber']);
        self::assertSame('José Pérez García', $cie['installerName']);

        // the logo can be removed; another user never sees it (there is no id to guess)
        [, $noLogo] = $this->json('DELETE', '/api/account/logo');
        self::assertNull($noLogo['logoUrl']);
        $this->uploadLogo(\App\Tests\Service\ImageSanitizerTest::png());
        $this->json('POST', '/api/logout');
        $this->registerAndLogin('otro@test.local');
        $this->client->request('GET', '/api/account/logo');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The CIE assistant over HTTP, with Claude replaced by a fake: without a key the Claude buttons are off
     * (409); with the user's own key a bill is read into CIE fields; and the CUPS is saved on the certificate.
     */
    public function testCieAssistantReadsABillWithTheUsersOwnKey(): void
    {
        // Claude is replaced before the first request builds the real gateway.
        static::getContainer()->set(\App\Assistant\AnthropicGateway::class, new \App\Tests\Assistant\FakeClaude([
            'titularName' => 'María López', 'titularNif' => '12345678Z', 'distributor' => 'i-DE', 'streetType' => 'Calle',
            'streetName' => 'del Mar', 'portal' => '3', 'stair' => null, 'floor' => null, 'door' => null,
            'postalCode' => '12001', 'locality' => 'Castelló', 'province' => 'Castellón',
            'cups' => 'ES0021000012345678LB', 'contractedPowerKw' => '5.75', 'supplyType' => 'monofasico',
        ]));
        $this->registerAndLogin('cie-ia@test.local');

        [, $status] = $this->json('GET', '/api/certificates/assistant/status');
        self::assertFalse($status['enabled']);
        [$noKey, $err] = $this->json('POST', '/api/certificates/assistant/help', ['step' => 'client', 'question' => '¿Qué es el CUPS?', 'form' => []]);
        self::assertSame(409, $noKey);
        self::assertSame('no_api_key', $err['error']);

        $this->json('PUT', '/api/account/integrations/anthropic', ['key' => 'sk-ant-test-key']);
        [, $on] = $this->json('GET', '/api/certificates/assistant/status');
        self::assertTrue($on['enabled']);

        $path = tempnam(sys_get_temp_dir(), 'bill');
        file_put_contents($path, \App\Tests\Service\ImageSanitizerTest::jpeg(800, 600));
        $this->client->request('POST', '/api/certificates/assistant/bill', [], [
            'bill' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'factura.jpg', 'image/jpeg', null, true),
        ]);
        $read = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('ES0021000012345678LB', $read['fields']['cups']);
        self::assertSame([], $read['checks']);
        self::assertSame('5.750', $read['contractedPowerKw']);

        // the read fields go into a CIE; the CUPS is stored normalised and printed on the PDF
        [$cs, $cie] = $this->json('POST', '/api/certificates', ['cups' => 'es 0021 0000 1234 5678 lb', 'companyName' => 'José Pérez'] + $read['fields']);
        self::assertSame(201, $cs);
        self::assertSame('ES0021000012345678LB', $cie['cups']);
    }

    /** The CERTINS E model over HTTP: lists, a certificate filled section by section, the GVA sheet and defaults. */
    public function testCertificateFollowsTheCertinsModel(): void
    {
        $this->registerAndLogin('certins@test.local');
        $this->json('PUT', '/api/account/settings', ['businessName' => 'José Pérez García', 'taxId' => '11111111H', 'installerRegNumber' => 'CV-12345']);

        [, $model] = $this->json('GET', '/api/certificates/model');
        self::assertSame('Local húmedo', $model['risks']['humedo']);
        self::assertSame('Vivienda', $model['uses']['vivienda']);

        [$s, $cie] = $this->json('POST', '/api/certificates', [
            'titularName' => 'María López', 'titularNif' => '12345678Z', 'supplySource' => 'red', 'distributor' => 'i-DE',
            'streetType' => 'Calle', 'streetName' => 'del Mar', 'portal' => '3', 'locality' => 'Castelló', 'province' => 'Castellón',
            'postalCode' => '12001', 'installedPower' => '5,75', 'maxPower' => '5,75', 'voltage' => '230', 'cups' => 'ES0021000012345678LB',
            'specificRisks' => ['humedo', 'inventado'], 'useType' => 'vivienda', 'secondaryUses' => ['vivienda', 'alumbrado_exterior'],
            'designBasis' => 'mtd', 'designDate' => '2026-10-30', 'earthResistance' => '18', 'insulationResistance' => '250',
        ]);
        self::assertSame(201, $s);
        self::assertSame('Calle del Mar 3', $cie['address'], 'the one-line address is composed from the parts');
        self::assertSame(['humedo'], $cie['specificRisks'], 'unknown risks are dropped');
        self::assertSame(['alumbrado_exterior'], $cie['secondaryUses'], 'the main use is not repeated as secondary');
        self::assertSame('11111111H', $cie['installerNif'], 'the installer NIF comes from "Mis datos"');
        self::assertSame('5.750', $cie['installedPower'], 'a decimal comma is accepted, not dropped');
        self::assertSame('5.750', $cie['maxPower']);
        self::assertSame('CV-12345', $cie['companyRegNumber']);

        [$gs, $sheet] = $this->json('GET', '/api/certificates/' . $cie['id'] . '/gva');
        self::assertSame(200, $gs);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'MTD'], array_column($sheet['sections'], 'code'));
        self::assertSame([], $sheet['missing']);

        $this->json('POST', '/api/logout');
        $this->registerAndLogin('otro-certins@test.local');
        [$other] = $this->json('GET', '/api/certificates/' . $cie['id'] . '/gva');
        self::assertSame(404, $other, 'another user never sees the sheet');
    }

    /** Security review: brute force on login and mass sign-ups are throttled. / Fuerza bruta y altas en masa. */
    public function testLoginAndSignUpAreThrottled(): void
    {
        $this->json('POST', '/api/register', ['email' => 'victima@test.local', 'password' => 'secret123']);
        $this->json('POST', '/api/logout');
        $codes = [];
        for ($i = 0; $i < 6; $i++) {
            [$s, $b] = $this->json('POST', '/api/login', ['email' => 'victima@test.local', 'password' => 'mala-' . $i]);
            $codes[] = $s . ':' . ($b['code'] ?? '');
        }
        self::assertSame('401:bad_credentials', $codes[0]);
        self::assertSame('429:too_many_attempts', $codes[5], 'the 6th wrong password in a minute is refused: ' . implode(', ', $codes));
        [$s] = $this->json('POST', '/api/login', ['email' => 'victima@test.local', 'password' => 'secret123']);
        self::assertSame(429, $s, 'even the right password waits until the window passes');

        static::getContainer()->get('cache.rate_limiter')->clear();
        $statuses = [];
        for ($i = 0; $i < 11; $i++) {
            [$statuses[]] = $this->json('POST', '/api/register', ['email' => "alta$i@test.local", 'password' => 'secret123']);
            $this->json('POST', '/api/logout');
        }
        self::assertSame(201, $statuses[9]);
        self::assertSame(429, $statuses[10], 'the 11th sign-up from one IP in an hour is refused');
    }

    /** Security review: the CSV import has a size cap. / La importación tiene tamaño máximo. */
    public function testCsvImportRefusesHugeFiles(): void
    {
        $this->registerAndLogin('csv-grande@test.local');
        $this->client->request('POST', '/api/import/csv', [], [], ['CONTENT_TYPE' => 'text/csv'], str_repeat("2026-01-01;Algo;-1,00\n", 120000));
        self::assertSame(413, $this->client->getResponse()->getStatusCode());
    }

    /** Security review: the invoice PDF fetches nothing remote, and the Verifactu QR still renders. */
    public function testInvoicePdfRendersTheQrWithoutRemoteFetching(): void
    {
        $this->registerAndLogin('pdf-qr@test.local');
        $this->json('PUT', '/api/account/settings', ['billingMode' => 'verifactu', 'businessName' => 'José Pérez', 'taxId' => '12345678Z', 'fiscalAddress' => 'C/ Major 1']);
        [, $inv] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'ACME', 'taxId' => 'B12345674'], 'lines' => [['description' => 'x', 'quantity' => '1', 'unitPrice' => '10.00', 'vatRate' => '21.00']]]);
        $this->client->request('GET', '/api/invoices/' . $inv['id'] . '/pdf');
        $pdf = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF', $pdf);
        // The QR is an SVG drawn as vector paths: compare with the same invoice without it (standard mode).
        // ES: El QR es un SVG dibujado con trazos: se compara con la misma factura sin QR (modo estándar).
        $this->json('PUT', '/api/account/settings', ['billingMode' => 'standard']);
        $this->client->request('GET', '/api/invoices/' . $inv['id'] . '/pdf');
        $plain = (string) $this->client->getResponse()->getContent();
        self::assertGreaterThan(strlen($plain) + 2000, strlen($pdf), 'the QR adds its drawing to the PDF');
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Service/InvoicePdf.php');
        self::assertStringContainsString("'isRemoteEnabled', false", $src);
    }

    /** Marking an invoice paid is bookkeeping: it never touches the Verifactu chain. */
    public function testAnInvoiceCanBeMarkedPaidWithoutTouchingTheChain(): void
    {
        $this->registerAndLogin('cobros@test.local');
        $line = ['description' => 'Cambio de diferencial', 'quantity' => '1', 'unitPrice' => '100.00', 'vatRate' => '21.00'];
        [, $a] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'Bar La Plaça', 'taxId' => 'B12345674'], 'lines' => [$line]]);
        [, $b] = $this->json('POST', '/api/invoices', ['customer' => ['name' => 'Talleres Roig', 'taxId' => 'B98765432'], 'lines' => [$line]]);
        self::assertNull($a['paidAt'], 'a new invoice is unpaid');
        [, $before] = $this->json('GET', '/api/invoices/verify');

        [$s, $paid] = $this->json('POST', '/api/invoices/' . $a['id'] . '/paid', ['paid' => true]);
        self::assertSame(200, $s);
        self::assertNotNull($paid['paidAt']);
        [, $again] = $this->json('POST', '/api/invoices/' . $a['id'] . '/paid', ['paid' => true]);
        self::assertSame($paid['paidAt'], $again['paidAt'], 'marking it again keeps the first date');

        [, $list] = $this->json('GET', '/api/invoices');
        $byId = array_column($list, 'paidAt', 'id');
        self::assertNotNull($byId[$a['id']]);
        self::assertNull($byId[$b['id']]);

        [, $after] = $this->json('GET', '/api/invoices/verify');
        self::assertTrue($after['ok']);
        self::assertSame($before, $after, 'the chain is exactly as before');

        [, $undone] = $this->json('POST', '/api/invoices/' . $a['id'] . '/paid', ['paid' => false]);
        self::assertNull($undone['paidAt']);
        [$bad] = $this->json('POST', '/api/invoices/' . $a['id'] . '/paid', ['paid' => 'yes']);
        self::assertSame(400, $bad);

        $this->json('POST', '/api/logout');
        $this->registerAndLogin('otro-cobros@test.local');
        [$other] = $this->json('POST', '/api/invoices/' . $a['id'] . '/paid', ['paid' => true]);
        self::assertSame(404, $other, 'another user cannot touch it');
    }

    /** What the code review found: bad dates and over-long texts are a 400 naming the field, never a 500. */
    public function testCertificateRefusesBadInputByField(): void
    {
        $this->registerAndLogin('certins-input@test.local');
        // a company: its CIF is never offered as the installer's NIF
        $this->json('PUT', '/api/account/settings', ['businessName' => 'Instalaciones Mar SL', 'taxId' => 'B12345674', 'installerRegNumber' => 'CV-1']);
        $base = ['titularName' => 'María López', 'streetName' => 'del Mar'];

        [, $model] = $this->json('GET', '/api/certificates/model');
        self::assertContains('installerNif', $model['required']);
        self::assertSame(['distributor', 'cups'], $model['gridRequired']);
        self::assertSame(10, $model['maxLength']['floor']);

        foreach (['31/12/2026', 'next monday', '2026-02-30'] as $bad) {
            [$s, $err] = $this->json('POST', '/api/certificates', $base + ['designDate' => $bad]);
            self::assertSame(400, $s, $bad);
            self::assertSame(['error' => 'invalid_field', 'field' => 'designDate'], $err);
        }
        [$s, $err] = $this->json('POST', '/api/certificates', $base + ['floor' => 'Bajo izquierda']);
        self::assertSame(400, $s);
        self::assertSame('floor', $err['field']);
        self::assertSame(0, count($this->json('GET', '/api/certificates')[1]), 'nothing refused was saved');

        [$s, $cie] = $this->json('POST', '/api/certificates', $base + [
            'installedPower' => '1.500,5', 'maxPower' => '5.750',
            'supplySource' => 'generacion_aislada', 'distributor' => 'i-DE', 'cups' => 'ES0021000012345678LB',
        ]);
        self::assertSame(201, $s);
        self::assertSame('1500.500', $cie['installedPower'], 'with a comma, the dots are thousands');
        self::assertSame('5.750', $cie['maxPower'], 'without one, the dot is the decimal point');
        self::assertNull($cie['cups'], 'an isolated installation has no CUPS');
        self::assertNull($cie['distributor']);
        self::assertSame('B12345674', $cie['companyNif']);
        self::assertNull($cie['installerNif']);
        self::assertNull($cie['installerName']);

        // an update with a bad date changes nothing
        [$s] = $this->json('PUT', '/api/certificates/' . $cie['id'], $base + ['designDate' => 'abc', 'installedPower' => '9']);
        self::assertSame(400, $s);
        self::assertEqualsWithDelta(1500.5, (float) $this->json('GET', '/api/certificates/' . $cie['id'])[1]['installedPower'], 0.0001);
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
