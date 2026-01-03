<?php

namespace App\Services\AI;

use App\Models\AIProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MultiAIService
{
    protected int $userId;
    protected Collection $providers;
    protected ?AIProvider $primaryProvider;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->providers = AIProvider::getActiveForUser($userId);
        $this->primaryProvider = AIProvider::getPrimaryForUser($userId);
    }

    /**
     * Create an instance for a specific user.
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Check if the service is available (has at least one active provider).
     */
    public function isAvailable(): bool
    {
        return $this->providers->isNotEmpty();
    }

    /**
     * Get the number of active providers.
     */
    public function getProviderCount(): int
    {
        return $this->providers->count();
    }

    /**
     * Get the primary provider.
     */
    public function getPrimaryProvider(): ?AIProvider
    {
        return $this->primaryProvider;
    }

    /**
     * Query all active providers in parallel and aggregate results.
     */
    public function processWithAllProviders(string $prompt, array $options = []): array
    {
        $startTime = microtime(true);

        // Query all active providers
        $individualResponses = $this->queryAllActive($prompt, $options);

        // Filter successful responses
        $successfulResponses = collect($individualResponses)->filter(fn ($r) => $r['error'] === null);

        // If no successful responses, return error
        if ($successfulResponses->isEmpty()) {
            return [
                'individual_responses' => $individualResponses,
                'aggregated_response' => null,
                'primary_provider' => $this->primaryProvider?->provider,
                'total_duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'error' => 'All AI providers failed to respond',
            ];
        }

        // If only one provider or no primary, return the first successful response
        if ($successfulResponses->count() === 1 || !$this->primaryProvider) {
            $first = $successfulResponses->first();
            return [
                'individual_responses' => $individualResponses,
                'aggregated_response' => $first['response'],
                'primary_provider' => $first['provider'],
                'total_duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'error' => null,
            ];
        }

        // Aggregate responses using the primary provider
        $aggregatedResponse = $this->aggregate($successfulResponses->toArray(), $prompt);

        return [
            'individual_responses' => $individualResponses,
            'aggregated_response' => $aggregatedResponse['response'],
            'primary_provider' => $this->primaryProvider->provider,
            'total_duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'error' => $aggregatedResponse['error'],
        ];
    }

    /**
     * Query all active providers.
     */
    public function queryAllActive(string $prompt, array $options = []): array
    {
        $responses = [];

        // Use parallel execution with promises/fibers if available
        // For now, we'll execute sequentially but can be optimized with async
        foreach ($this->providers as $provider) {
            $service = AIService::fromProvider($provider);
            
            if (!$service->isAvailable()) {
                $responses[$provider->provider] = [
                    'response' => null,
                    'error' => 'Provider not configured',
                    'duration_ms' => 0,
                    'model' => $provider->model,
                    'provider' => $provider->provider,
                ];
                continue;
            }

            $result = $service->completeWithMetadata($prompt, $options);
            $responses[$provider->provider] = $result;
        }

        return $responses;
    }

    /**
     * Aggregate multiple responses using the primary provider.
     */
    public function aggregate(array $responses, string $originalPrompt): array
    {
        if (!$this->primaryProvider) {
            return [
                'response' => null,
                'error' => 'No primary provider configured for aggregation',
            ];
        }

        $primaryService = AIService::fromProvider($this->primaryProvider);

        if (!$primaryService->isAvailable()) {
            return [
                'response' => null,
                'error' => 'Primary provider not available for aggregation',
            ];
        }

        // Build aggregation prompt
        $aggregationPrompt = $this->buildAggregationPrompt($responses, $originalPrompt);

        try {
            $aggregated = $primaryService->complete($aggregationPrompt);
            return [
                'response' => $aggregated,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Multi-AI aggregation failed', [
                'error' => $e->getMessage(),
                'primary_provider' => $this->primaryProvider->provider,
            ]);

            // Fall back to first successful response
            $firstSuccess = collect($responses)->first(fn ($r) => $r['error'] === null);
            return [
                'response' => $firstSuccess['response'] ?? null,
                'error' => 'Aggregation failed, returning first successful response',
            ];
        }
    }

    /**
     * Build the aggregation prompt.
     */
    protected function buildAggregationPrompt(array $responses, string $originalPrompt): string
    {
        $responseParts = [];
        
        foreach ($responses as $provider => $data) {
            if ($data['error'] === null && $data['response']) {
                $responseParts[] = "### Response from {$provider} ({$data['model']}):\n{$data['response']}";
            }
        }

        $allResponses = implode("\n\n", $responseParts);

        return <<<PROMPT
You are an AI aggregator. Multiple AI models have been queried with the same prompt, and you need to synthesize their responses into a single, comprehensive answer.

## Original Question/Prompt:
{$originalPrompt}

## Responses from Different AI Models:
{$allResponses}

## Your Task:
Analyze all the responses above and create a single, synthesized response that:
1. Combines the best insights from each response
2. Resolves any contradictions by choosing the most accurate/reasonable answer
3. Provides a comprehensive answer that covers all relevant points
4. Maintains accuracy and avoids including any incorrect information
5. Is clear, well-organized, and easy to understand

Provide only the synthesized response without meta-commentary about the aggregation process.
PROMPT;
    }

    /**
     * Analyze an image with all providers and aggregate results.
     */
    public function analyzeImageWithAllProviders(string $base64Image, string $mimeType, string $prompt): array
    {
        $startTime = microtime(true);
        $responses = [];

        foreach ($this->providers as $provider) {
            $service = AIService::fromProvider($provider);
            
            if (!$service->isAvailable()) {
                $responses[$provider->provider] = [
                    'response' => null,
                    'error' => 'Provider not configured',
                    'model' => $provider->model,
                    'provider' => $provider->provider,
                ];
                continue;
            }

            try {
                $imageStartTime = microtime(true);
                $response = $service->analyzeImage($base64Image, $mimeType, $prompt);
                $duration = (int) ((microtime(true) - $imageStartTime) * 1000);

                $responses[$provider->provider] = [
                    'response' => $response,
                    'error' => null,
                    'duration_ms' => $duration,
                    'model' => $provider->model,
                    'provider' => $provider->provider,
                ];
            } catch (\Exception $e) {
                $responses[$provider->provider] = [
                    'response' => null,
                    'error' => $e->getMessage(),
                    'duration_ms' => 0,
                    'model' => $provider->model,
                    'provider' => $provider->provider,
                ];
            }
        }

        // Aggregate if we have multiple successful responses
        $successfulResponses = collect($responses)->filter(fn ($r) => $r['error'] === null);

        if ($successfulResponses->count() <= 1 || !$this->primaryProvider) {
            $first = $successfulResponses->first();
            return [
                'individual_responses' => $responses,
                'aggregated_response' => $first['response'] ?? null,
                'primary_provider' => $first['provider'] ?? null,
                'total_duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'error' => $successfulResponses->isEmpty() ? 'All providers failed' : null,
            ];
        }

        $aggregated = $this->aggregate($successfulResponses->toArray(), $prompt);

        return [
            'individual_responses' => $responses,
            'aggregated_response' => $aggregated['response'],
            'primary_provider' => $this->primaryProvider->provider,
            'total_duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'error' => $aggregated['error'],
        ];
    }

    /**
     * Get a summary of provider status.
     */
    public function getProviderStatus(): array
    {
        return $this->providers->map(function (AIProvider $provider) {
            return [
                'provider' => $provider->provider,
                'model' => $provider->model,
                'is_active' => $provider->is_active,
                'is_primary' => $provider->is_primary,
                'has_api_key' => $provider->hasApiKey(),
                'test_status' => $provider->test_status,
                'last_tested_at' => $provider->last_tested_at?->toISOString(),
            ];
        })->keyBy('provider')->toArray();
    }
}
