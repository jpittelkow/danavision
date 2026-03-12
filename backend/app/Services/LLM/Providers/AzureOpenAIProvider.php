<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class AzureOpenAIProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $endpoint;
    private string $deployment;
    private string $apiVersion;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->apiKey = $config->api_key;
        $this->endpoint = $config->settings['endpoint'] ?? config('llm.providers.azure.endpoint');
        $this->deployment = $config->settings['deployment'] ?? config('llm.providers.azure.deployment');
        $this->apiVersion = $config->settings['api_version'] ?? config('llm.providers.azure.api_version', '2024-02-15-preview');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.azure.max_tokens', 4096);

        if (empty($this->endpoint) || empty($this->deployment)) {
            throw new \InvalidArgumentException('Azure OpenAI requires endpoint and deployment configuration');
        }
    }

    /**
     * Build the Azure OpenAI API URL.
     */
    private function buildUrl(): string
    {
        $baseUrl = rtrim($this->endpoint, '/');
        return "{$baseUrl}/openai/deployments/{$this->deployment}/chat/completions?api-version={$this->apiVersion}";
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
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->buildUrl(), [
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Azure OpenAI API error: ' . $response->body());
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

        // Azure OpenAI uses the same format as OpenAI for vision
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
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->buildUrl(), [
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Azure OpenAI API error: ' . $response->body());
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
        return 'Azure OpenAI';
    }
}
