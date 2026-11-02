<?php

namespace App\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * ClaudeGateway over the official Anthropic PHP SDK, with the user's own API key.
 *
 * - Model: Claude Opus 5.5. Effort is set per call: low for reading a bill or answering a question,
 *   medium for the final review.
 * - Structured outputs (`output_config.format` with a JSON Schema): the reply is guaranteed to be valid JSON
 *   in the expected shape — no parsing of free text.
 * - Server-side fallback (`fallbacks: "default"`): if the model declines for policy reasons, the API retries
 *   on the server-defined fallback model instead of failing.
 *
 * ES: ClaudeGateway sobre el SDK oficial de PHP, con la clave del propio usuario. Modelo Claude Opus 5.5;
 * esfuerzo por llamada; salida estructurada con esquema JSON; y reintento en el servidor si el modelo declina.
 */
#[AsAlias(ClaudeGateway::class)]
final class AnthropicGateway implements ClaudeGateway
{
    public const MODEL = 'claude-opus-5-5';

    public function __construct(private LoggerInterface $logger) {}

    public function structured(string $apiKey, string $system, array $content, array $schema, string $effort, int $maxTokens): array
    {
        if ($apiKey === '') {
            throw new AssistantException('no_api_key');
        }

        try {
            $client = new Client(apiKey: $apiKey, requestOptions: ['timeout' => 60, 'maxRetries' => 2]);
            $message = $client->beta->messages->create(
                model: self::MODEL,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            // The user's key is wrong or revoked: something they can fix, so say so.
            throw new AssistantException('invalid_api_key', previous: $e);
        } catch (APIException $e) {
            // Rate limits, overload, network, 5xx (the SDK already retried): nothing the user can fix now.
            $this->logger->warning('Claude request failed', ['exception' => $e]);
            throw new AssistantException('unavailable', previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new AssistantException('declined');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        throw new AssistantException('bad_output');
    }
}
