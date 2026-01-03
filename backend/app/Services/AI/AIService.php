<?php

namespace App\Services\AI;

use App\Models\AIProvider;
use Illuminate\Support\Facades\Http;

class AIService
{
    protected AIProvider $provider;
    protected string $apiKey;
    protected string $model;
    protected ?string $baseUrl;

    public function __construct(AIProvider $provider)
    {
        $this->provider = $provider;
        $this->apiKey = $provider->getDecryptedApiKey() ?? '';
        $this->model = $provider->model ?? $provider->getDefaultModel();
        $this->baseUrl = $provider->base_url;
    }

    /**
     * Create an instance from an AIProvider model.
     */
    public static function fromProvider(AIProvider $provider): self
    {
        return new self($provider);
    }

    /**
     * Create an instance for a specific user's primary provider.
     */
    public static function forUser(int $userId): ?self
    {
        $provider = AIProvider::getPrimaryForUser($userId);
        
        if (!$provider) {
            // Fall back to any active provider
            $provider = AIProvider::getActiveForUser($userId)->first();
        }

        return $provider ? new self($provider) : null;
    }

    /**
     * Check if the service is available.
     */
    public function isAvailable(): bool
    {
        if ($this->provider->provider === AIProvider::PROVIDER_LOCAL) {
            return true;
        }

        return !empty($this->apiKey);
    }

    /**
     * Get the provider type.
     */
    public function getProviderType(): string
    {
        return $this->provider->provider;
    }

    /**
     * Get the model being used.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the AIProvider instance.
     */
    public function getProvider(): AIProvider
    {
        return $this->provider;
    }

    /**
     * Complete a prompt.
     */
    public function complete(string $prompt, array $options = []): string
    {
        $result = $this->completeWithMetadata($prompt, $options);

        if ($result['error']) {
            throw new \RuntimeException($result['error']);
        }

        return $result['response'];
    }

    /**
     * Complete a prompt with metadata (response, error, duration).
     */
    public function completeWithMetadata(string $prompt, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'response' => null,
                'error' => 'AI service is not configured.',
                'duration_ms' => 0,
                'model' => $this->model,
                'provider' => $this->provider->provider,
            ];
        }

        $startTime = microtime(true);

        try {
            $response = match ($this->provider->provider) {
                AIProvider::PROVIDER_CLAUDE => $this->completeClaude($prompt, $options),
                AIProvider::PROVIDER_OPENAI => $this->completeOpenAI($prompt, $options),
                AIProvider::PROVIDER_GEMINI => $this->completeGemini($prompt, $options),
                AIProvider::PROVIDER_LOCAL => $this->completeOllama($prompt, $options),
                default => throw new \RuntimeException('Unknown AI provider: ' . $this->provider->provider),
            };

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'response' => $response,
                'error' => null,
                'duration_ms' => $duration,
                'model' => $this->model,
                'provider' => $this->provider->provider,
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'response' => null,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
                'model' => $this->model,
                'provider' => $this->provider->provider,
            ];
        }
    }

    /**
     * Analyze an image.
     */
    public function analyzeImage(string $base64Image, string $mimeType, string $prompt): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('AI service is not configured.');
        }

        return match ($this->provider->provider) {
            AIProvider::PROVIDER_CLAUDE => $this->analyzeImageClaude($base64Image, $mimeType, $prompt),
            AIProvider::PROVIDER_OPENAI => $this->analyzeImageOpenAI($base64Image, $mimeType, $prompt),
            AIProvider::PROVIDER_GEMINI => $this->analyzeImageGemini($base64Image, $mimeType, $prompt),
            AIProvider::PROVIDER_LOCAL => $this->analyzeImageOllama($base64Image, $mimeType, $prompt),
            default => throw new \RuntimeException('Image analysis not supported by provider: ' . $this->provider->provider),
        };
    }

    /**
     * Test the API connection.
     */
    public function testConnection(): array
    {
        try {
            $response = $this->complete('Say "Connection successful" in exactly 2 words.', ['max_tokens' => 50]);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /**
     * Complete using Claude.
     */
    protected function completeClaude(string $prompt, array $options = []): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Claude API error: ' . $response->body());
        }

        return $response->json('content.0.text', '');
    }

    /**
     * Analyze image using Claude.
     */
    protected function analyzeImageClaude(string $base64Image, string $mimeType, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $base64Image,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Claude API error: ' . $response->body());
        }

        return $response->json('content.0.text', '');
    }

    /**
     * Complete using OpenAI.
     */
    protected function completeOpenAI(string $prompt, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Analyze image using OpenAI.
     */
    protected function analyzeImageOpenAI(string $base64Image, string $mimeType, string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Complete using Gemini.
     */
    protected function completeGemini(string $prompt, array $options = []): string
    {
        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * Analyze image using Gemini.
     */
    protected function analyzeImageGemini(string $base64Image, string $mimeType, string $prompt): string
    {
        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image,
                                ],
                            ],
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * Complete using Ollama (local).
     */
    protected function completeOllama(string $prompt, array $options = []): string
    {
        $baseUrl = $this->baseUrl ?? 'http://localhost:11434';

        $response = Http::timeout(120)->post("{$baseUrl}/api/generate", [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ollama API error: ' . $response->body());
        }

        return $response->json('response', '');
    }

    /**
     * Analyze image using Ollama (local).
     */
    protected function analyzeImageOllama(string $base64Image, string $mimeType, string $prompt): string
    {
        $baseUrl = $this->baseUrl ?? 'http://localhost:11434';

        $response = Http::timeout(120)->post("{$baseUrl}/api/generate", [
            'model' => $this->model,
            'prompt' => $prompt,
            'images' => [$base64Image],
            'stream' => false,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ollama API error: ' . $response->body());
        }

        return $response->json('response', '');
    }

    /**
     * List available Ollama models.
     */
    public static function listOllamaModels(?string $baseUrl = null): array
    {
        $baseUrl = $baseUrl ?? 'http://localhost:11434';

        try {
            $response = Http::timeout(10)->get("{$baseUrl}/api/tags");

            if (!$response->successful()) {
                return [];
            }

            $models = $response->json('models', []);
            
            return array_map(function ($model) {
                return [
                    'name' => $model['name'],
                    'size' => $model['size'] ?? 0,
                    'modified_at' => $model['modified_at'] ?? null,
                ];
            }, $models);
        } catch (\Exception $e) {
            return [];
        }
    }
}
