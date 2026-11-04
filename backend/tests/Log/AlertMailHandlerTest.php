<?php

namespace App\Tests\Log;

use App\Log\AlertMailHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class AlertMailHandlerTest extends TestCase
{
    /** @var list<Email> */
    private array $sent = [];

    private function mailer(bool $fail = false): MailerInterface
    {
        return new class($this->sent, $fail) implements MailerInterface {
            public function __construct(private array &$sent, private bool $fail) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($this->fail) {
                    throw new \RuntimeException('smtp down');
                }
                $this->sent[] = $message;
            }
        };
    }

    private function record(string $message, Level $level = Level::Error, ?\Throwable $e = null): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'request', $level, $message, $e ? ['exception' => $e] : []);
    }

    private function handler(string $to = 'jefe@example.com', bool $fail = false): AlertMailHandler
    {
        return new AlertMailHandler($this->mailer($fail), new ArrayAdapter(), $to, 'Cuentia <no-reply@example.com>');
    }

    public function testAnErrorSendsOneEmailWithTheException(): void
    {
        $this->handler()->handle($this->record('Uncaught PHP Exception', Level::Critical, new \LogicException('boom')));

        $this->assertCount(1, $this->sent);
        $this->assertSame('jefe@example.com', $this->sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('[Cuentia] Error: Uncaught PHP Exception', $this->sent[0]->getSubject());
        $this->assertStringContainsString('LogicException: boom', $this->sent[0]->getTextBody());
    }

    public function testTheSameErrorIsSentOnlyOnceAnHour(): void
    {
        $h = $this->handler();
        $h->handle($this->record('Database down'));
        $h->handle($this->record('Database down'));
        $h->handle($this->record('Another failure'));

        $this->assertCount(2, $this->sent);
    }

    public function testWarningsAreNotSent(): void
    {
        $this->handler()->handle($this->record('Slow request', Level::Warning));

        $this->assertCount(0, $this->sent);
    }

    public function testNoAddressMeansNoAlerts(): void
    {
        $h = $this->handler('');
        $this->assertFalse($h->isHandling($this->record('Database down')));
        $h->handle($this->record('Database down'));

        $this->assertCount(0, $this->sent);
    }

    public function testAMailerFailureDoesNotBreakTheRequest(): void
    {
        $this->handler(fail: true)->handle($this->record('Database down'));

        $this->assertCount(0, $this->sent); // no exception escaped / ninguna excepción se escapa
    }
}
