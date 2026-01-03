<?php

namespace App\Services\AI;

use App\Models\AIProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIModelService
{
    /**
     * Cache duration in seconds (5 minutes).
     */
    protected const CACHE_DURATION = 300;

    /**
     * Get available models for a provider.
     */
    public function getModelsForProvider(AIProvider $provider): array
    {
        return match ($provider->provider) {
            AIProvider::PROVIDER_CLAUDE => $this->getClaudeModels($provider->getDecryptedApiKey()),
            AIProvider::PROVIDER_OPENAI => $this->getOpenAIModels($provider->getDecryptedApiKey()),
            AIProvider::PROVIDER_GEMINI => $this->getGeminiModels($provider->getDecryptedApiKey(), $provider->base_url),
            AIProvider::PROVIDER_LOCAL => $this->getLocalModels($provider->base_url),
            default => [],
        };
    }

    /**
     * Get available models for Claude (Anthropic).
     * Anthropic doesn't have a public models endpoint, so we return known models.
     */
    public function getClaudeModels(?string $apiKey): array
    {
        // Return default models - Anthropic doesn't expose a models API
        return $this->getDefaultClaudeModels();
    }

    /**
     * Get available models for OpenAI.
     */
    public function getOpenAIModels(?string $apiKey, ?string $baseUrl = null): array
    {
        if (!$apiKey) {
            return $this->getDefaultOpenAIModels();
        }

        $baseUrl = rtrim($baseUrl ?? 'https://api.openai.com/v1', '/');
        $cacheKey = 'ai_models_openai_' . md5($baseUrl . $apiKey);

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($apiKey, $baseUrl) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                ])->timeout(10)->get("{$baseUrl}/models");

                if ($response->successful()) {
                    $data = $response->json();
                    $models = [];

                    if (isset($data['data']) && is_array($data['data'])) {
                        foreach ($data['data'] as $model) {
                            if (isset($model['id'])) {
                                $modelId = $model['id'];
                                // Filter to only chat models (gpt-*)
                                if (str_starts_with($modelId, 'gpt-')) {
                                    $models[$modelId] = $this->formatModelName($modelId);
                                }
                            }
                        }
                    }

                    // Merge with defaults to ensure key models are always available
                    $defaults = $this->getDefaultOpenAIModels();
                    return array_merge($defaults, $models);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch OpenAI models: {$e->getMessage()}");
            }

            return $this->getDefaultOpenAIModels();
        });
    }

    /**
     * Get available models for Gemini (Google).
     */
    public function getGeminiModels(?string $apiKey, ?string $baseUrl = null): array
    {
        if (!$apiKey) {
            return $this->getDefaultGeminiModels();
        }

        // Default base URL should not include version path - it's added in the endpoint
        $baseUrl = rtrim($baseUrl ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        // Ensure we're using the base URL without version for the models endpoint
        $baseUrl = preg_replace('#/v1(beta)?$#', '', $baseUrl);
        $cacheKey = 'ai_models_gemini_' . md5($baseUrl . $apiKey);

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($apiKey, $baseUrl) {
            try {
                // Use v1beta endpoint for models list
                $endpoint = "{$baseUrl}/v1beta/models?key={$apiKey}";
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(10)->get($endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    $models = [];

                    if (isset($data['models']) && is_array($data['models'])) {
                        foreach ($data['models'] as $model) {
                            if (isset($model['name'])) {
                                // Extract model name from full path (e.g., "models/gemini-1.5-pro")
                                $modelName = str_replace('models/', '', $model['name']);
                                
                                // Only include generation models (those supporting generateContent)
                                if (isset($model['supportedGenerationMethods']) &&
                                    in_array('generateContent', $model['supportedGenerationMethods'])) {
                                    $displayName = $model['displayName'] ?? $this->formatModelName($modelName);
                                    $models[$modelName] = $displayName;
                                }
                            }
                        }
                    }

                    // Merge with defaults to ensure key models are always available
                    $defaults = $this->getDefaultGeminiModels();
                    return array_merge($defaults, $models);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch Gemini models: {$e->getMessage()}");
            }

            return $this->getDefaultGeminiModels();
        });
    }

    /**
     * Get available models for Local (Ollama or OpenAI-compatible).
     */
    public function getLocalModels(?string $baseUrl, ?string $apiKey = null): array
    {
        if (!$baseUrl) {
            return $this->getDefaultLocalModels();
        }

        $isOllama = str_contains($baseUrl, '11434');
        $cacheKey = 'ai_models_local_' . md5($baseUrl);

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($baseUrl, $apiKey, $isOllama) {
            try {
                $headers = ['Content-Type' => 'application/json'];
                if ($apiKey) {
                    $headers['Authorization'] = "Bearer {$apiKey}";
                }

                if ($isOllama) {
                    // Ollama API
                    $response = Http::withHeaders($headers)
                        ->timeout(10)
                        ->get("{$baseUrl}/api/tags");

                    if ($response->successful()) {
                        $data = $response->json();
                        $models = [];

                        if (isset($data['models']) && is_array($data['models'])) {
                            foreach ($data['models'] as $model) {
                                if (isset($model['name'])) {
                                    $models[$model['name']] = $model['name'];
                                }
                            }
                        }

                        // Merge with defaults
                        $defaults = $this->getDefaultLocalModels();
                        return array_merge($defaults, $models);
                    }
                } else {
                    // OpenAI-compatible API
                    $baseUrl = rtrim($baseUrl, '/');
                    $response = Http::withHeaders($headers)
                        ->timeout(10)
                        ->get("{$baseUrl}/v1/models");

                    if ($response->successful()) {
                        $data = $response->json();
                        $models = [];

                        if (isset($data['data']) && is_array($data['data'])) {
                            foreach ($data['data'] as $model) {
                                if (isset($model['id'])) {
                                    $models[$model['id']] = $model['id'];
                                }
                            }
                        }

                        // Merge with defaults
                        $defaults = $this->getDefaultLocalModels();
                        return array_merge($defaults, $models);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch local models: {$e->getMessage()}");
            }

            return $this->getDefaultLocalModels();
        });
    }

    /**
     * Format a model name for display.
     */
    protected function formatModelName(string $modelId): string
    {
        // Convert model IDs like "gpt-4o" to "GPT-4o"
        $name = str_replace('-', ' ', $modelId);
        $name = ucwords($name);
        $name = str_replace(' ', '-', $name);
        
        // Handle specific patterns
        $name = preg_replace('/^Gpt/i', 'GPT', $name);
        $name = preg_replace('/^Gemini/i', 'Gemini', $name);
        
        return $name;
    }

    /**
     * Get default Claude models.
     */
    protected function getDefaultClaudeModels(): array
    {
        return [
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
        ];
    }

    /**
     * Get default OpenAI models.
     */
    protected function getDefaultOpenAIModels(): array
    {
        return [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
        ];
    }

    /**
     * Get default Gemini models.
     */
    protected function getDefaultGeminiModels(): array
    {
        return [
            'gemini-2.5-flash-preview-05-20' => 'Gemini 2.5 Flash (Preview)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B',
        ];
    }

    /**
     * Get default Local models.
     */
    protected function getDefaultLocalModels(): array
    {
        return [
            'llama3.2' => 'Llama 3.2',
            'llama3.1' => 'Llama 3.1',
            'llama3' => 'Llama 3',
            'mistral' => 'Mistral',
            'mixtral' => 'Mixtral',
            'codellama' => 'Code Llama',
        ];
    }

    /**
     * Clear cached models for a provider.
     */
    public function clearCache(string $provider, ?string $apiKey = null, ?string $baseUrl = null): void
    {
        $cacheKey = match ($provider) {
            AIProvider::PROVIDER_OPENAI => 'ai_models_openai_' . md5(($baseUrl ?? 'https://api.openai.com/v1') . $apiKey),
            AIProvider::PROVIDER_GEMINI => 'ai_models_gemini_' . md5(preg_replace('#/v1(beta)?$#', '', $baseUrl ?? 'https://generativelanguage.googleapis.com') . $apiKey),
            AIProvider::PROVIDER_LOCAL => 'ai_models_local_' . md5($baseUrl ?? ''),
            default => null,
        };

        if ($cacheKey) {
            Cache::forget($cacheKey);
        }
    }
}
