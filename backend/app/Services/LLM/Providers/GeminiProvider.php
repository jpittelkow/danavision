<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use App\Services\UrlValidationService;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->apiKey = $config->api_key;
        $this->model = $config->model ?? config('llm.providers.gemini.model', 'gemini-1.5-pro');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.gemini.max_tokens', 4096);
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $contents = [];

        if ($systemPrompt) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "System instruction: {$systemPrompt}"]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Understood. I will follow these instructions.']],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        $response = Http::timeout(config('llm.timeout', 120))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => $this->maxTokens,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }

    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array
    {
        $parts = [];

        if (str_starts_with($imageData, 'http')) {
            // For URL, we need to fetch and convert to base64 with SSRF protection
            $urlValidator = app(UrlValidationService::class);
            $imageContent = $urlValidator->fetchContent($imageData);
            if ($imageContent === null) {
                throw new \RuntimeException('Failed to fetch image: URL validation failed or request error');
            }
            $imageData = base64_encode($imageContent);
        }

        $parts[] = [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $imageData,
            ],
        ];

        $parts[] = ['text' => $prompt];

        $contents = [
            [
                'role' => 'user',
                'parts' => $parts,
            ],
        ];

        $response = Http::timeout(config('llm.timeout', 120))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => $this->maxTokens,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }

    public function supportsVision(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Gemini (Google)';
    }
}
