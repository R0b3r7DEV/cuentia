<?php

namespace App\Tests\Assistant;

use App\Assistant\ClaudeGateway;

/** A ClaudeGateway that records what it was sent and answers with a canned object. */
final class FakeClaude implements ClaudeGateway
{
    public array $calls = [];

    public function __construct(private array $reply) {}

    public function structured(string $apiKey, string $system, array $content, array $schema, string $effort, int $maxTokens): array
    {
        $this->calls[] = compact('apiKey', 'system', 'content', 'schema', 'effort', 'maxTokens');

        return $this->reply;
    }

    public function text(string $apiKey, string $system, array $content, string $effort, int $maxTokens): string
    {
        $this->calls[] = compact('apiKey', 'system', 'content', 'effort', 'maxTokens');
        if (($this->reply['throw'] ?? null) !== null) {
            throw new \App\Assistant\AssistantException($this->reply['throw']);
        }

        return (string) ($this->reply['text'] ?? '');
    }
}
