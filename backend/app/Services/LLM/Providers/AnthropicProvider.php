<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->apiKey = $config->api_key;
        $this->model = $config->model ?? config('llm.providers.claude.model', 'claude-sonnet-4-20250514');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.claude.max_tokens', 4096);
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
                'total' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array
    {
        $imageContent = [
            'type' => 'image',
            'source' => str_starts_with($imageData, 'http')
                ? ['type' => 'url', 'url' => $imageData]
                : ['type' => 'base64', 'media_type' => $mimeType, 'data' => $imageData],
        ];

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    $imageContent,
                    ['type' => 'text', 'text' => $prompt],
                ],
            ],
        ];

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
                'total' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function supportsVision(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Claude (Anthropic)';
    }
}
