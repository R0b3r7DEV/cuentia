<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * POST /api/logout answers 204 instead of Symfony's default redirect to "/". That redirect pointed at the API's
 * own origin, so the browser's fetch followed it cross-origin, failed with "Failed to fetch", and the app never
 * learnt it was logged out (the "Salir" button seemed to do nothing until a reload).
 * ES: /api/logout responde 204 en vez de redirigir a «/». La redirección apuntaba al dominio de la API, el fetch
 * la seguía a otro origen, fallaba, y la app no se enteraba de que había salido.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.main')]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
