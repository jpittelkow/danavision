<?php

namespace App\Services\LLM;

interface LLMProviderInterface
{
    /**
     * Execute a text query.
     *
     * @param string $prompt The user prompt
     * @param string|null $systemPrompt Optional system prompt
     * @return array ['content' => string, 'tokens' => ['input' => int, 'output' => int, 'total' => int]]
     */
    public function query(string $prompt, ?string $systemPrompt = null): array;

    /**
     * Execute a vision query with image.
     *
     * @param string $prompt The user prompt
     * @param string $imageData Base64 encoded image or URL
     * @param string $mimeType Image MIME type
     * @param string|null $systemPrompt Optional system prompt
     * @return array ['content' => string, 'tokens' => ['input' => int, 'output' => int, 'total' => int]]
     */
    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array;

    /**
     * Check if the provider supports vision/image queries.
     */
    public function supportsVision(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
