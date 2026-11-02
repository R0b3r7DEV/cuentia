<?php

namespace App\Tests\Api;

use App\Entity\PasswordResetToken;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * "Forgot my password", end to end: e-mail, link, new password, and every way it must refuse.
 * ES: "He olvidado la contraseña", de punta a punta: correo, enlace, contraseña nueva y todo lo que debe rechazar.
 */
class PasswordResetTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);

        // the limiter's counters live in the cache: each test starts at zero
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function json(string $method, string $url, ?array $body = null): array
    {
        $this->client->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json'], $body !== null ? json_encode($body) : null);
        $res = $this->client->getResponse();

        return [$res->getStatusCode(), json_decode($res->getContent() ?: 'null', true)];
    }

    private function register(string $email, string $password = 'secret123'): void
    {
        $this->json('POST', '/api/register', ['email' => $email, 'password' => $password]);
    }

    private function login(string $email, string $password): int
    {
        return $this->json('POST', '/api/login', ['email' => $email, 'password' => $password])[0];
    }

    /** The token inside the link of the last e-mail sent. */
    private function tokenFromLastEmail(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertMatchesRegularExpression('~/restablecer#token=([a-f0-9]{64})~', $email->getTextBody());
        preg_match('~#token=([a-f0-9]{64})~', $email->getTextBody(), $m);

        return $m[1];
    }

    public function testFullResetFlow(): void
    {
        $this->register('Ionut@Test.local');

        // asked with different capitalisation: still found
        [$s, $body] = $this->json('POST', '/api/password/forgot', ['email' => 'ionut@test.local']);
        self::assertSame(202, $s);
        self::assertSame('reset_requested', $body['code']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'Ionut@Test.local');
        self::assertEmailHtmlBodyContains($email, 'Elegir una contraseña nueva');
        $token = $this->tokenFromLastEmail();

        // the database never holds the token itself, only its hash
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $stored = $em->getRepository(PasswordResetToken::class)->findAll();
        self::assertCount(1, $stored);
        self::assertSame(hash('sha256', $token), $stored[0]->getTokenHash());
        self::assertNotSame($token, $stored[0]->getTokenHash());

        [$rs, $rb] = $this->json('POST', '/api/password/reset', ['token' => $token, 'password' => 'nueva-clave-segura']);
        self::assertSame(200, $rs);
        self::assertSame('password_changed', $rb['code']);

        $this->json('POST', '/api/logout');
        self::assertSame(401, $this->login('Ionut@Test.local', 'secret123'), 'the old password no longer works');
        self::assertSame(200, $this->login('Ionut@Test.local', 'nueva-clave-segura'));

        // single use
        [$again, $ab] = $this->json('POST', '/api/password/reset', ['token' => $token, 'password' => 'otra-clave-mas']);
        self::assertSame(400, $again);
        self::assertSame('invalid_token', $ab['code']);
    }

    /** Same answer whether the account exists or not, and no e-mail for unknown addresses. */
    public function testUnknownEmailGivesTheSameAnswerAndSendsNothing(): void
    {
        $this->register('existe@test.local');

        [$s1, $b1] = $this->json('POST', '/api/password/forgot', ['email' => 'no-existe@test.local']);
        self::assertEmailCount(0);
        [$s2, $b2] = $this->json('POST', '/api/password/forgot', ['email' => 'existe@test.local']);

        self::assertSame([$s1, $b1], [$s2, $b2], 'the response does not reveal whether the account exists');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $this->register('caduca@test.local');
        $this->json('POST', '/api/password/forgot', ['email' => 'caduca@test.local']);
        $token = $this->tokenFromLastEmail();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(PasswordResetToken::class)->findOneBy([])->expireAt(new \DateTimeImmutable('-1 minute'));
        $em->flush();

        [$s, $b] = $this->json('POST', '/api/password/reset', ['token' => $token, 'password' => 'nueva-clave-segura']);
        self::assertSame(400, $s);
        self::assertSame('invalid_token', $b['code']);
        self::assertSame(200, $this->login('caduca@test.local', 'secret123'), 'the password did not change');
    }

    public function testANewLinkInvalidatesThePreviousOne(): void
    {
        $this->register('dos@test.local');
        $this->json('POST', '/api/password/forgot', ['email' => 'dos@test.local']);
        $first = $this->tokenFromLastEmail();
        $this->json('POST', '/api/password/forgot', ['email' => 'dos@test.local']);
        $second = $this->tokenFromLastEmail();
        self::assertNotSame($first, $second);

        self::assertSame(400, $this->json('POST', '/api/password/reset', ['token' => $first, 'password' => 'nueva-clave-segura'])[0]);
        self::assertSame(200, $this->json('POST', '/api/password/reset', ['token' => $second, 'password' => 'nueva-clave-segura'])[0]);
    }

    public function testWeakPasswordAndBadTokensAreRefused(): void
    {
        $this->register('debil@test.local');
        $this->json('POST', '/api/password/forgot', ['email' => 'debil@test.local']);
        $token = $this->tokenFromLastEmail();

        self::assertSame('weak_password', $this->json('POST', '/api/password/reset', ['token' => $token, 'password' => 'corta'])[1]['code']);
        self::assertSame('invalid_token', $this->json('POST', '/api/password/reset', ['token' => '', 'password' => 'nueva-clave-segura'])[1]['code']);
        self::assertSame('invalid_token', $this->json('POST', '/api/password/reset', ['token' => str_repeat('a', 64), 'password' => 'nueva-clave-segura'])[1]['code']);
        // the weak attempt did not burn the token
        self::assertSame(200, $this->json('POST', '/api/password/reset', ['token' => $token, 'password' => 'nueva-clave-segura'])[0]);
    }

    public function testInvalidEmailIsA400(): void
    {
        self::assertSame(400, $this->json('POST', '/api/password/forgot', ['email' => 'no-es-un-email'])[0]);
    }

    /** Spam protection: the same address can only be asked for 3 times an hour. */
    public function testRequestsAreRateLimited(): void
    {
        $this->register('spam@test.local');
        for ($i = 0; $i < 3; $i++) {
            self::assertSame(202, $this->json('POST', '/api/password/forgot', ['email' => 'spam@test.local'])[0]);
        }
        [$s, $b] = $this->json('POST', '/api/password/forgot', ['email' => 'spam@test.local']);
        self::assertSame(429, $s);
        self::assertSame('too_many_requests', $b['code']);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
        self::assertEmailCount(0, null, 'the refused request sends nothing');
    }

    /** Changing the password ends the other open sessions (Symfony re-checks the password hash). */
    public function testResetLogsOutOtherSessions(): void
    {
        $this->register('sesion@test.local');
        self::assertSame(200, $this->login('sesion@test.local', 'secret123'));
        self::assertSame(200, $this->json('GET', '/api/me')[0]);

        // the reset happens elsewhere (e.g. from the phone) — simulated straight through the service
        $this->json('POST', '/api/password/forgot', ['email' => 'sesion@test.local']);
        $token = $this->tokenFromLastEmail();
        static::getContainer()->get(\App\Service\PasswordResetService::class)->reset($token, 'nueva-clave-segura');

        self::assertSame(401, $this->json('GET', '/api/me')[0], 'the old session no longer authenticates');
    }

    public function testEndpointsArePublic(): void
    {
        // no login needed: an anonymous user can ask (202), not a 401
        self::assertSame(202, $this->json('POST', '/api/password/forgot', ['email' => 'quien@test.local'])[0]);
        self::assertSame(400, $this->json('POST', '/api/password/reset', ['token' => 'x', 'password' => 'nueva-clave-segura'])[0]);
    }
}
