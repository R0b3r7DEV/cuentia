<?php

namespace App\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * E-mails an alert when the app logs an error in production, so a failure is known the same day and not when a
 * customer complains. Off while ALERT_EMAIL is empty. The same error (same message and exception class) is sent
 * at most once an hour, so a broken page refreshed a hundred times is one e-mail, not a hundred. The e-mail
 * carries the message, the exception and where it happened — never the request body, so no customer data.
 *
 * ES: Manda un aviso por correo cuando la app registra un error en producción, para enterarse el mismo día y no
 * cuando se queje un cliente. Apagado mientras ALERT_EMAIL esté vacío. El mismo error (mismo mensaje y clase de
 * excepción) se manda como mucho una vez por hora: una página rota recargada cien veces es un correo, no cien.
 * El correo lleva el mensaje, la excepción y dónde pasó — nunca el cuerpo de la petición, así que nada de datos
 * de clientes.
 */
class AlertMailHandler extends AbstractProcessingHandler
{
    public const DEDUP_SECONDS = 3600;

    /** True while an alert is being sent: if sending itself logs an error, it is not sent again (no loop). */
    private bool $sending = false;

    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(service: 'cache.app')] private CacheItemPoolInterface $cache,
        #[Autowire(env: 'ALERT_EMAIL')] private string $to,
        #[Autowire(env: 'MAILER_FROM')] private string $from,
    ) {
        parent::__construct(Level::Error, true);
    }

    public function isHandling(LogRecord $record): bool
    {
        return trim($this->to) !== '' && !$this->sending && parent::isHandling($record);
    }

    protected function write(LogRecord $record): void
    {
        $exception = $record->context['exception'] ?? null;
        $key = 'alert_' . hash('sha256', $record->message . '|' . ($exception instanceof \Throwable ? $exception::class : ''));
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return; // already sent within the hour / ya se avisó en esta hora
        }

        $this->sending = true;
        try {
            $this->mailer->send($this->email($record, $exception instanceof \Throwable ? $exception : null));
            $this->cache->save($item->set(true)->expiresAfter(self::DEDUP_SECONDS));
        } catch (\Throwable) {
            // An alert that cannot be sent must never break the request that hit the error.
            // ES: Un aviso que no se puede enviar nunca debe romper la petición que tuvo el error.
        } finally {
            $this->sending = false;
        }
    }

    private function email(LogRecord $record, ?\Throwable $e): Email
    {
        $lines = [
            'Cuentia ha registrado un error en producción.',
            '',
            'Cuándo: ' . $record->datetime->format('Y-m-d H:i:s T'),
            'Nivel: ' . $record->level->getName() . ' · canal: ' . $record->channel,
            'Mensaje: ' . $record->message,
        ];
        if ($e !== null) {
            $lines[] = 'Excepción: ' . $e::class . ': ' . $e->getMessage();
            $lines[] = 'Dónde: ' . $e->getFile() . ':' . $e->getLine();
            $lines[] = '';
            $lines[] = mb_substr($e->getTraceAsString(), 0, 4000);
        }
        $lines[] = '';
        $lines[] = 'Si se repite, no recibirás otro aviso igual hasta dentro de una hora. Los detalles están en los logs.';

        return (new Email())
            ->from(Address::create($this->from))
            ->to($this->to)
            ->subject('[Cuentia] Error: ' . mb_substr($record->message, 0, 120))
            ->text(implode("\n", $lines));
    }
}
