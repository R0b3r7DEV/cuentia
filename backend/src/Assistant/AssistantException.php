<?php

namespace App\Assistant;

/**
 * Why the assistant could not answer, as a fixed code safe to show the client:
 * no_api_key · declined (Claude refused) · unavailable (network / API error) · bad_output.
 * ES: Por qué el asistente no pudo responder, como código fijo seguro para el cliente.
 */
final class AssistantException extends \RuntimeException
{
}
