<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->apiKey = $config->api_key;
        $this->model = $config->model ?? config('llm.providers.openai.model', 'gpt-4o');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.openai.max_tokens', 4096);
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens' => [
                'input' => $data['usage']['prompt_tokens'] ?? 0,
                'output' => $data['usage']['completion_tokens'] ?? 0,
                'total' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array
    {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $imageUrl = str_starts_with($imageData, 'http')
            ? $imageData
            : "data:{$mimeType};base64,{$imageData}";

        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ],
        ];

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens' => [
                'input' => $data['usage']['prompt_tokens'] ?? 0,
                'output' => $data['usage']['completion_tokens'] ?? 0,
                'total' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    public function supportsVision(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'OpenAI';
    }
}
