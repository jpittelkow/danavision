<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LLMProviderInterface
{
    private string $baseUrl;
    private string $model;
    private int $maxTokens;
    private bool $supportsVisionFlag;

    public function __construct(AIProvider $config)
    {
        $this->baseUrl = $config->settings['base_url'] ?? config('llm.providers.ollama.base_url', 'http://localhost:11434');
        $this->model = $config->model ?? config('llm.providers.ollama.model', 'llama3.2');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.ollama.max_tokens', 4096);
        $this->supportsVisionFlag = $config->settings['supports_vision'] ?? config('llm.providers.ollama.supports_vision', false);
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'num_predict' => $this->maxTokens,
            ],
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->post("{$this->baseUrl}/api/generate", $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Ollama API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['response'] ?? '',
            'tokens' => [
                'input' => $data['prompt_eval_count'] ?? 0,
                'output' => $data['eval_count'] ?? 0,
                'total' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0),
            ],
        ];
    }

    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array
    {
        if (!$this->supportsVision()) {
            throw new \RuntimeException('This Ollama model does not support vision');
        }

        // Ollama vision models use a different format
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'images' => [
                str_starts_with($imageData, 'http') ? $imageData : $imageData,
            ],
            'options' => [
                'num_predict' => $this->maxTokens,
            ],
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->post("{$this->baseUrl}/api/generate", $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Ollama API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['response'] ?? '',
            'tokens' => [
                'input' => $data['prompt_eval_count'] ?? 0,
                'output' => $data['eval_count'] ?? 0,
                'total' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0),
            ],
        ];
    }

    public function supportsVision(): bool
    {
        return $this->supportsVisionFlag;
    }

    public function getName(): string
    {
        return 'Ollama (Local)';
    }
}
