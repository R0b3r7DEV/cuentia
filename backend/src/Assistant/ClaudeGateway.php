<?php

namespace App\Assistant;

/**
 * One structured request to Claude: system prompt + user content in, a JSON object that matches $schema out.
 * An interface so the assistant's logic can be tested without calling the API.
 *
 * ES: Una petición estructurada a Claude: prompt de sistema + contenido del usuario, y sale un objeto JSON
 * que cumple $schema. Es una interfaz para poder probar el asistente sin llamar a la API.
 */
interface ClaudeGateway
{
    /**
     * @param list<array<string,mixed>> $content user content blocks (text / image)
     * @param array<string,mixed>       $schema  JSON Schema of the expected object
     * @return array<string,mixed>
     *
     * @throws AssistantException
     */
    public function structured(string $apiKey, string $system, array $content, array $schema, string $effort, int $maxTokens): array;
}
