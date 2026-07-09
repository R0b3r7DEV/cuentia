<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Turns a failed login into a clean JSON error with a stable machine-readable `code`, which the frontend
 * translates. The message is **deliberately generic**: we never reveal whether an account exists for that
 * email — telling them apart would allow user enumeration.
 *
 * ES: Convierte un login fallido en un error JSON limpio con un `code` estable que el frontend traduce.
 * El mensaje es **genérico a propósito**: nunca revelamos si existe una cuenta con ese email — distinguir
 * ambos casos permitiría enumerar usuarios.
 */
class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['error' => 'Too many login attempts, please wait a moment', 'code' => 'too_many_attempts'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        // Same answer for "no such user" and "wrong password". / La misma respuesta para ambos casos.
        return new JsonResponse(
            ['error' => 'Invalid email or password', 'code' => 'bad_credentials'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
