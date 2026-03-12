<?php

namespace App\Services\LLM;

use App\Models\User;
use App\Models\AIProvider;
use App\Models\AIRequestLog;
use App\Services\UrlValidationService;
use App\Services\UsageTrackingService;
use App\Services\LLM\Providers\AnthropicProvider;
use App\Services\LLM\Providers\OpenAIProvider;
use App\Services\LLM\Providers\GeminiProvider;
use App\Services\LLM\Providers\OllamaProvider;
use App\Services\LLM\Providers\AzureOpenAIProvider;
use App\Services\LLM\Providers\BedrockProvider;
use Illuminate\Support\Facades\Log;

class LLMOrchestrator
{
    private array $providerInstances = [];

    /**
     * Persist user LLM mode and provider overrides.
     */
    public function updateUserConfig(User $user, ?string $mode, ?array $providers): void
    {
        if ($mode !== null) {
            $user->setSetting('defaults', 'llm_mode', $mode);
        }

        if ($providers !== null) {
            foreach ($providers as $providerConfig) {
                $data = [
                    'provider' => $providerConfig['provider'],
                ];

                if (isset($providerConfig['api_key'])) {
                    $data['api_key'] = $providerConfig['api_key'];
                }
                if (isset($providerConfig['model'])) {
                    $data['model'] = $providerConfig['model'];
                }
                if (isset($providerConfig['is_enabled'])) {
                    $data['is_enabled'] = $providerConfig['is_enabled'];
                }
                if (isset($providerConfig['is_primary'])) {
                    $data['is_primary'] = $providerConfig['is_primary'];

                    // Ensure only one primary
                    if ($providerConfig['is_primary']) {
                        $user->aiProviders()
                            ->where('provider', '!=', $providerConfig['provider'])
                            ->update(['is_primary' => false]);
                    }
                }

                $user->aiProviders()->updateOrCreate(
                    ['provider' => $providerConfig['provider']],
                    $data
                );
            }
        }
    }

    /**
     * Resolve image input from a request: returns [imageData, mimeType].
     * imageData is base64 string for uploads, or the URL string for URL-based images.
     *
     * Note: validateUrl() (not validateAndResolve) is intentional here. The URL is passed
     * to the external LLM provider which fetches it — our server does not make the HTTP
     * request, so DNS pinning is not applicable.
     *
     * @return array{0: string, 1: string|null}
     * @throws \InvalidArgumentException if URL fails SSRF validation
     */
    public function resolveImageInput(\Illuminate\Http\Request $request, UrlValidationService $urlValidator): array
    {
        if ($request->hasFile('image')) {
            return [
                base64_encode(file_get_contents($request->file('image')->path())),
                $request->file('image')->getMimeType(),
            ];
        }

        $imageUrl = $request->input('image_url');
        if (!$urlValidator->validateUrl($imageUrl)) {
            throw new \InvalidArgumentException('Image URL is not allowed (invalid or internal address).');
        }

        return [$imageUrl, null];
    }

    /**
     * Execute an LLM query.
     */
    public function query(
        User $user,
        string $prompt,
        ?string $systemPrompt = null,
        ?string $mode = null,
        ?string $provider = null,
    ): array {
        $mode = $mode ?? $user->getSetting('defaults', 'llm_mode', config('llm.mode'));
        $startTime = microtime(true);

        $result = match ($mode) {
            'single' => $this->singleQuery($user, $prompt, $systemPrompt, $provider),
            'aggregation' => $this->aggregationQuery($user, $prompt, $systemPrompt),
            'council' => $this->councilQuery($user, $prompt, $systemPrompt),
            default => throw new \InvalidArgumentException("Invalid LLM mode: {$mode}"),
        };

        $result['mode'] = $mode;
        $result['total_duration_ms'] = round((microtime(true) - $startTime) * 1000);

        // Log the request
        $this->logRequest($user, $mode, $prompt, $result);

        if ($result['success']) {
            Log::info('LLM query completed', [
                'user_id' => $user->id,
                'mode' => $mode,
                'provider' => $result['provider'] ?? $result['synthesis_provider'] ?? 'multiple',
                'duration_ms' => $result['total_duration_ms'],
            ]);
        } else {
            Log::warning('LLM query failed', [
                'user_id' => $user->id,
                'mode' => $mode,
                'error' => $result['error'] ?? 'Unknown',
            ]);
        }

        return $result;
    }

    /**
     * Execute a vision query with image.
     */
    public function visionQuery(
        User $user,
        string $prompt,
        string $imageData,
        string $mimeType = 'image/jpeg',
        ?string $systemPrompt = null,
        ?string $mode = null,
        ?string $provider = null,
    ): array {
        $mode = $mode ?? $user->getSetting('defaults', 'llm_mode', config('llm.mode'));
        $startTime = microtime(true);

        // Filter to vision-capable providers
        $providers = $this->getEnabledProviders($user)
            ->filter(fn ($p) => $this->supportsVision($p->provider));

        if ($providers->isEmpty()) {
            throw new \RuntimeException('No vision-capable providers configured');
        }

        $result = match ($mode) {
            'single' => $this->singleVisionQuery($user, $prompt, $imageData, $mimeType, $systemPrompt, $provider),
            'aggregation' => $this->aggregationVisionQuery($user, $prompt, $imageData, $mimeType, $systemPrompt),
            'council' => $this->councilVisionQuery($user, $prompt, $imageData, $mimeType, $systemPrompt),
            default => throw new \InvalidArgumentException("Invalid LLM mode: {$mode}"),
        };

        $result['mode'] = $mode;
        $result['total_duration_ms'] = round((microtime(true) - $startTime) * 1000);

        return $result;
    }

    /**
     * Test a provider connection.
     */
    public function testProvider(User $user, string $providerName): array
    {
        $providerConfig = $user->aiProviders()->where('provider', $providerName)->first();

        if (!$providerConfig) {
            throw new \RuntimeException("Provider not configured: {$providerName}");
        }

        $provider = $this->getProviderInstance($providerName, $providerConfig);
        $startTime = microtime(true);

        try {
            $response = $provider->query('Hello, please respond with "OK" to confirm the connection is working.');
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::info('LLM provider test succeeded', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'duration_ms' => $durationMs,
            ]);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'response' => $response,
                'duration_ms' => $durationMs,
            ];
        } catch (\Exception $e) {
            Log::warning('LLM provider test failed', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Single provider query.
     */
    private function singleQuery(User $user, string $prompt, ?string $systemPrompt, ?string $providerName): array
    {
        $providerConfig = $providerName
            ? $user->aiProviders()->where('provider', $providerName)->first()
            : $user->aiProviders()->primary()->first() ?? $user->aiProviders()->enabled()->first();

        if (!$providerConfig) {
            throw new \RuntimeException('No LLM provider configured');
        }

        $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
        $startTime = microtime(true);

        try {
            $response = $provider->query($prompt, $systemPrompt);

            return [
                'success' => true,
                'response' => $response['content'],
                'provider' => $providerConfig->provider,
                'model' => $providerConfig->model,
                'tokens' => $response['tokens'] ?? null,
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $providerConfig->provider,
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Aggregation mode: query all providers, primary synthesizes.
     */
    private function aggregationQuery(User $user, string $prompt, ?string $systemPrompt): array
    {
        $providers = $this->getEnabledProviders($user);

        if ($providers->count() < 1) {
            throw new \RuntimeException('At least one provider required');
        }

        $primaryConfig = $providers->firstWhere('is_primary', true) ?? $providers->first();
        $responses = [];

        // Query all providers in parallel (simulated with sequential for simplicity)
        foreach ($providers as $providerConfig) {
            $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
            $startTime = microtime(true);

            try {
                $response = $provider->query($prompt, $systemPrompt);
                $responses[$providerConfig->provider] = [
                    'success' => true,
                    'content' => $response['content'],
                    'tokens' => $response['tokens'] ?? null,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                ];
            } catch (\Exception $e) {
                Log::warning('LLM aggregation provider failed', [
                    'provider' => $providerConfig->provider,
                    'error' => $e->getMessage(),
                ]);
                $responses[$providerConfig->provider] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                ];
            }
        }

        // Have primary provider synthesize the responses
        $successfulResponses = array_filter($responses, fn ($r) => $r['success']);

        if (empty($successfulResponses)) {
            return [
                'success' => false,
                'error' => 'All providers failed',
                'provider_responses' => $responses,
            ];
        }

        $synthesisPrompt = $this->buildSynthesisPrompt($prompt, $successfulResponses);
        $primaryProvider = $this->getProviderInstance($primaryConfig->provider, $primaryConfig);

        try {
            $synthesis = $primaryProvider->query($synthesisPrompt);

            return [
                'success' => true,
                'response' => $synthesis['content'],
                'synthesis_provider' => $primaryConfig->provider,
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        } catch (\Exception $e) {
            // Fall back to first successful response
            $firstSuccess = array_values($successfulResponses)[0];
            return [
                'success' => true,
                'response' => $firstSuccess['content'],
                'synthesis_failed' => true,
                'synthesis_error' => $e->getMessage(),
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        }
    }

    /**
     * Council mode: all providers vote/contribute, consensus resolution.
     */
    private function councilQuery(User $user, string $prompt, ?string $systemPrompt): array
    {
        $providers = $this->getEnabledProviders($user);
        $minProviders = config('llm.council.min_providers', 2);

        if ($providers->count() < $minProviders) {
            throw new \RuntimeException("Council mode requires at least {$minProviders} providers");
        }

        $responses = [];
        $weights = config('llm.council.weights', []);

        // Query all providers
        foreach ($providers as $providerConfig) {
            $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
            $startTime = microtime(true);

            try {
                $response = $provider->query($prompt, $systemPrompt);
                $responses[$providerConfig->provider] = [
                    'success' => true,
                    'content' => $response['content'],
                    'tokens' => $response['tokens'] ?? null,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                    'weight' => $weights[$providerConfig->provider] ?? 1.0,
                ];
            } catch (\Exception $e) {
                Log::warning('LLM council provider failed', [
                    'provider' => $providerConfig->provider,
                    'error' => $e->getMessage(),
                ]);
                $responses[$providerConfig->provider] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                    'weight' => 0,
                ];
            }
        }

        $successfulResponses = array_filter($responses, fn ($r) => $r['success']);

        if (count($successfulResponses) < $minProviders) {
            return [
                'success' => false,
                'error' => 'Not enough providers succeeded for council consensus',
                'provider_responses' => $responses,
            ];
        }

        // Use primary provider to synthesize council responses
        $primaryConfig = $providers->firstWhere('is_primary', true) ?? $providers->first();
        $primaryProvider = $this->getProviderInstance($primaryConfig->provider, $primaryConfig);

        $councilPrompt = $this->buildCouncilPrompt($prompt, $successfulResponses);

        try {
            $synthesis = $primaryProvider->query($councilPrompt);

            return [
                'success' => true,
                'response' => $synthesis['content'],
                'council_size' => count($successfulResponses),
                'synthesis_provider' => $primaryConfig->provider,
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Council synthesis failed: ' . $e->getMessage(),
                'provider_responses' => $responses,
            ];
        }
    }

    /**
     * Single vision query.
     */
    private function singleVisionQuery(User $user, string $prompt, string $imageData, string $mimeType, ?string $systemPrompt, ?string $providerName): array
    {
        $providers = $this->getEnabledProviders($user)
            ->filter(fn ($p) => $this->supportsVision($p->provider));

        $providerConfig = $providerName
            ? $providers->firstWhere('provider', $providerName)
            : $providers->firstWhere('is_primary', true) ?? $providers->first();

        if (!$providerConfig) {
            throw new \RuntimeException('No vision-capable provider configured');
        }

        $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
        $startTime = microtime(true);

        try {
            $response = $provider->visionQuery($prompt, $imageData, $mimeType, $systemPrompt);

            return [
                'success' => true,
                'response' => $response['content'],
                'provider' => $providerConfig->provider,
                'model' => $providerConfig->model,
                'tokens' => $response['tokens'] ?? null,
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $providerConfig->provider,
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Aggregation vision query: query all vision-capable providers, primary synthesizes.
     */
    private function aggregationVisionQuery(User $user, string $prompt, string $imageData, string $mimeType, ?string $systemPrompt): array
    {
        $providers = $this->getEnabledProviders($user)
            ->filter(fn ($p) => $this->supportsVision($p->provider));

        if ($providers->count() < 1) {
            throw new \RuntimeException('At least one vision-capable provider required');
        }

        $primaryConfig = $providers->firstWhere('is_primary', true) ?? $providers->first();
        $responses = [];

        // Query all vision-capable providers
        foreach ($providers as $providerConfig) {
            $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
            $startTime = microtime(true);

            try {
                $response = $provider->visionQuery($prompt, $imageData, $mimeType, $systemPrompt);
                $responses[$providerConfig->provider] = [
                    'success' => true,
                    'content' => $response['content'],
                    'tokens' => $response['tokens'] ?? null,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                ];
            } catch (\Exception $e) {
                $responses[$providerConfig->provider] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                ];
            }
        }

        // Have primary provider synthesize the responses
        $successfulResponses = array_filter($responses, fn ($r) => $r['success']);

        if (empty($successfulResponses)) {
            return [
                'success' => false,
                'error' => 'All vision providers failed',
                'provider_responses' => $responses,
            ];
        }

        // If only one response, return it directly
        if (count($successfulResponses) === 1) {
            $firstSuccess = array_values($successfulResponses)[0];
            return [
                'success' => true,
                'response' => $firstSuccess['content'],
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        }

        $synthesisPrompt = $this->buildSynthesisPrompt($prompt, $successfulResponses);
        $primaryProvider = $this->getProviderInstance($primaryConfig->provider, $primaryConfig);

        try {
            $synthesis = $primaryProvider->query($synthesisPrompt);

            return [
                'success' => true,
                'response' => $synthesis['content'],
                'synthesis_provider' => $primaryConfig->provider,
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        } catch (\Exception $e) {
            // Fall back to first successful response
            $firstSuccess = array_values($successfulResponses)[0];
            return [
                'success' => true,
                'response' => $firstSuccess['content'],
                'synthesis_failed' => true,
                'synthesis_error' => $e->getMessage(),
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        }
    }

    /**
     * Council vision query: all vision-capable providers vote/contribute, consensus resolution.
     */
    private function councilVisionQuery(User $user, string $prompt, string $imageData, string $mimeType, ?string $systemPrompt): array
    {
        $providers = $this->getEnabledProviders($user)
            ->filter(fn ($p) => $this->supportsVision($p->provider));

        $minProviders = config('llm.council.min_providers', 2);

        if ($providers->count() < $minProviders) {
            // Fall back to single query if not enough vision providers for council
            return $this->singleVisionQuery($user, $prompt, $imageData, $mimeType, $systemPrompt, null);
        }

        $responses = [];
        $weights = config('llm.council.weights', []);

        // Query all vision-capable providers
        foreach ($providers as $providerConfig) {
            $provider = $this->getProviderInstance($providerConfig->provider, $providerConfig);
            $startTime = microtime(true);

            try {
                $response = $provider->visionQuery($prompt, $imageData, $mimeType, $systemPrompt);
                $responses[$providerConfig->provider] = [
                    'success' => true,
                    'content' => $response['content'],
                    'tokens' => $response['tokens'] ?? null,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                    'weight' => $weights[$providerConfig->provider] ?? 1.0,
                ];
            } catch (\Exception $e) {
                $responses[$providerConfig->provider] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                    'weight' => 0,
                ];
            }
        }

        $successfulResponses = array_filter($responses, fn ($r) => $r['success']);

        if (count($successfulResponses) < $minProviders) {
            // Not enough for council, return first successful or fail
            if (!empty($successfulResponses)) {
                $firstSuccess = array_values($successfulResponses)[0];
                return [
                    'success' => true,
                    'response' => $firstSuccess['content'],
                    'council_insufficient' => true,
                    'providers_used' => array_keys($successfulResponses),
                    'provider_responses' => $responses,
                ];
            }
            return [
                'success' => false,
                'error' => 'Not enough vision providers succeeded for council consensus',
                'provider_responses' => $responses,
            ];
        }

        // Use primary provider to synthesize council responses
        $primaryConfig = $providers->firstWhere('is_primary', true) ?? $providers->first();
        $primaryProvider = $this->getProviderInstance($primaryConfig->provider, $primaryConfig);

        $councilPrompt = $this->buildCouncilPrompt($prompt, $successfulResponses);

        try {
            $synthesis = $primaryProvider->query($councilPrompt);

            return [
                'success' => true,
                'response' => $synthesis['content'],
                'council_size' => count($successfulResponses),
                'synthesis_provider' => $primaryConfig->provider,
                'providers_used' => array_keys($successfulResponses),
                'provider_responses' => $responses,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Council synthesis failed: ' . $e->getMessage(),
                'provider_responses' => $responses,
            ];
        }
    }

    /**
     * Get enabled providers for user.
     */
    private function getEnabledProviders(User $user)
    {
        return $user->aiProviders()->enabled()->get();
    }

    /**
     * Check if provider supports vision.
     */
    private function supportsVision(string $provider): bool
    {
        return config("llm.providers.{$provider}.supports_vision", false);
    }

    /**
     * Get or create provider instance.
     */
    private function getProviderInstance(string $providerName, AIProvider $config): LLMProviderInterface
    {
        $key = "{$providerName}_{$config->id}";

        if (!isset($this->providerInstances[$key])) {
            $this->providerInstances[$key] = match ($providerName) {
                'claude' => new AnthropicProvider($config),
                'openai' => new OpenAIProvider($config),
                'gemini' => new GeminiProvider($config),
                'ollama' => new OllamaProvider($config),
                'azure' => new AzureOpenAIProvider($config),
                'bedrock' => new BedrockProvider($config),
                default => throw new \InvalidArgumentException("Unknown provider: {$providerName}"),
            };
        }

        return $this->providerInstances[$key];
    }

    /**
     * Build synthesis prompt for aggregation mode.
     */
    private function buildSynthesisPrompt(string $originalPrompt, array $responses): string
    {
        $responsesText = "";
        foreach ($responses as $provider => $response) {
            $responsesText .= "\n--- {$provider} ---\n{$response['content']}\n";
        }

        return <<<PROMPT
        You are synthesizing responses from multiple AI models to provide the best possible answer.

        Original question: {$originalPrompt}

        Responses from different AI models:
        {$responsesText}

        Please synthesize these responses into a single, comprehensive answer that:
        1. Combines the best insights from each response
        2. Resolves any contradictions by choosing the most accurate information
        3. Provides a clear, well-structured final answer

        Synthesized response:
        PROMPT;
    }

    /**
     * Build council prompt.
     */
    private function buildCouncilPrompt(string $originalPrompt, array $responses): string
    {
        $responsesText = "";
        foreach ($responses as $provider => $response) {
            $weight = $response['weight'];
            $responsesText .= "\n--- {$provider} (weight: {$weight}) ---\n{$response['content']}\n";
        }

        return <<<PROMPT
        You are the council moderator, synthesizing responses from an AI council to reach consensus.

        Original question: {$originalPrompt}

        Council member responses (with weights):
        {$responsesText}

        Please analyze all council responses and provide a consensus answer that:
        1. Weighs each response according to its weight factor
        2. Identifies areas of agreement among council members
        3. Resolves disagreements by favoring higher-weighted or more detailed responses
        4. Produces a final consensus answer that represents the council's collective wisdom

        Council consensus:
        PROMPT;
    }

    /**
     * Log the LLM request.
     */
    private function logRequest(User $user, string $mode, string $prompt, array $result): void
    {
        if (!config('llm.logging_enabled')) {
            return;
        }

        try {
            AIRequestLog::create([
                'user_id' => $user->id,
                'provider' => $result['provider'] ?? $result['synthesis_provider'] ?? 'multiple',
                'model' => $result['model'] ?? null,
                'mode' => $mode,
                'prompt' => $prompt,
                'response' => $result['response'] ?? null,
                'input_tokens' => $result['tokens']['input'] ?? null,
                'output_tokens' => $result['tokens']['output'] ?? null,
                'total_tokens' => $result['tokens']['total'] ?? null,
                'duration_ms' => $result['total_duration_ms'] ?? null,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
                'metadata' => [
                    'providers_used' => $result['providers_used'] ?? null,
                ],
            ]);

            // Record usage for the integration usage dashboard
            if ($result['success'] ?? false) {
                $provider = $result['provider'] ?? $result['synthesis_provider'] ?? 'multiple';
                $tokensIn = (int) ($result['tokens']['input'] ?? 0);
                $tokensOut = (int) ($result['tokens']['output'] ?? 0);
                // Providers don't currently populate 'cost'; falls back to estimateLLMCost() in UsageTrackingService
                $cost = $result['cost'] ?? null;

                app(UsageTrackingService::class)->recordLLM(
                    $provider,
                    $result['model'] ?? null,
                    $tokensIn,
                    $tokensOut,
                    $cost !== null ? (float) $cost : null,
                    $user->id
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to log LLM request', ['error' => $e->getMessage()]);
        }
    }
}
