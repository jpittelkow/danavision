<?php

namespace App\Services\AskDana;

use App\Models\AIProvider;
use App\Models\AIRequestLog;
use App\Models\User;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskDanaLLMAdapter
{
    /**
     * Send a tool-use conversation to the user's configured LLM provider.
     *
     * Returns the raw API response parsed into a normalized format:
     * [
     *   'content'    => ?string,    // text response (null when tool_use)
     *   'tool_calls' => array,      // array of ['id' => string, 'name' => string, 'input' => array]
     *   'stop_reason' => string,    // 'end_turn', 'tool_use', etc.
     *   'tokens'     => ['input' => int, 'output' => int, 'total' => int],
     *   'model'      => string,
     *   'provider'   => string,
     * ]
     */
    public function send(
        User $user,
        array $messages,
        string $systemPrompt,
        array $tools,
    ): array {
        $providerConfig = $this->resolveProvider($user);
        $providerName = $providerConfig->provider;

        $startMs = microtime(true);

        $result = match ($providerName) {
            'claude' => $this->sendAnthropic($providerConfig, $messages, $systemPrompt, $tools),
            'openai' => $this->sendOpenAI($providerConfig, $messages, $systemPrompt, $tools),
            default => throw new \RuntimeException("Provider \"{$providerName}\" does not support tool use. Configure Claude or OpenAI."),
        };

        $durationMs = (int) ((microtime(true) - $startMs) * 1000);
        $result['provider'] = $providerName;

        // Log the request
        $this->logRequest($user, $providerName, $result, $durationMs);

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Anthropic Messages API with tool use
    // ──────────────────────────────────────────────────────────────────────

    private function sendAnthropic(AIProvider $config, array $messages, string $systemPrompt, array $tools): array
    {
        $model = $config->model ?? config('llm.providers.claude.model', 'claude-sonnet-4-20250514');
        $maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.claude.max_tokens', 4096);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $this->formatMessagesForAnthropic($messages),
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'x-api-key' => $config->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();

        return $this->parseAnthropicResponse($data, $model);
    }

    private function formatMessagesForAnthropic(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'user') {
                $formatted[] = ['role' => 'user', 'content' => $msg['content']];
            } elseif ($msg['role'] === 'assistant') {
                // Could be text content or tool_use blocks
                if (isset($msg['tool_calls']) && !empty($msg['tool_calls'])) {
                    $content = [];
                    if (!empty($msg['content'])) {
                        $content[] = ['type' => 'text', 'text' => $msg['content']];
                    }
                    foreach ($msg['tool_calls'] as $tc) {
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => $tc['id'],
                            'name' => $tc['name'],
                            'input' => $tc['input'],
                        ];
                    }
                    $formatted[] = ['role' => 'assistant', 'content' => $content];
                } else {
                    $formatted[] = ['role' => 'assistant', 'content' => $msg['content']];
                }
            } elseif ($msg['role'] === 'tool_result') {
                $formatted[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg['tool_use_id'],
                            'content' => is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']),
                        ],
                    ],
                ];
            }
        }

        return $formatted;
    }

    private function parseAnthropicResponse(array $data, string $model): array
    {
        $textContent = '';
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $textContent .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'content' => $textContent ?: null,
            'tool_calls' => $toolCalls,
            'stop_reason' => $data['stop_reason'] ?? 'end_turn',
            'tokens' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
                'total' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            'model' => $model,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // OpenAI Chat Completions API with tool use
    // ──────────────────────────────────────────────────────────────────────

    private function sendOpenAI(AIProvider $config, array $messages, string $systemPrompt, array $tools): array
    {
        $model = $config->model ?? config('llm.providers.openai.model', 'gpt-4o');
        $maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.openai.max_tokens', 4096);

        $openAIMessages = $this->formatMessagesForOpenAI($messages, $systemPrompt);

        $payload = [
            'model' => $model,
            'messages' => $openAIMessages,
            'max_tokens' => $maxTokens,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->convertToolsToOpenAI($tools);
        }

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $config->api_key,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();

        return $this->parseOpenAIResponse($data, $model);
    }

    private function formatMessagesForOpenAI(array $messages, string $systemPrompt): array
    {
        $formatted = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'user') {
                $formatted[] = ['role' => 'user', 'content' => $msg['content']];
            } elseif ($msg['role'] === 'assistant') {
                $entry = ['role' => 'assistant', 'content' => $msg['content'] ?? null];
                if (!empty($msg['tool_calls'])) {
                    $entry['tool_calls'] = array_map(fn ($tc) => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => json_encode($tc['input']),
                        ],
                    ], $msg['tool_calls']);
                }
                $formatted[] = $entry;
            } elseif ($msg['role'] === 'tool_result') {
                $formatted[] = [
                    'role' => 'tool',
                    'tool_call_id' => $msg['tool_use_id'],
                    'content' => is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']),
                ];
            }
        }

        return $formatted;
    }

    private function convertToolsToOpenAI(array $anthropicTools): array
    {
        return array_map(fn ($tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['input_schema'],
            ],
        ], $anthropicTools);
    }

    private function parseOpenAIResponse(array $data, string $model): array
    {
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = [
                'id' => $tc['id'],
                'name' => $tc['function']['name'],
                'input' => json_decode($tc['function']['arguments'], true) ?? [],
            ];
        }

        $finishReason = $choice['finish_reason'] ?? 'stop';
        $stopReason = match ($finishReason) {
            'tool_calls' => 'tool_use',
            'stop' => 'end_turn',
            default => $finishReason,
        };

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $toolCalls,
            'stop_reason' => $stopReason,
            'tokens' => [
                'input' => $data['usage']['prompt_tokens'] ?? 0,
                'output' => $data['usage']['completion_tokens'] ?? 0,
                'total' => $data['usage']['total_tokens'] ?? 0,
            ],
            'model' => $model,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Provider Resolution
    // ──────────────────────────────────────────────────────────────────────

    private function resolveProvider(User $user): AIProvider
    {
        // Try user's primary provider first (must be claude or openai for tool use)
        $primary = $user->aiProviders()
            ->where('is_enabled', true)
            ->where('is_primary', true)
            ->whereIn('provider', ['claude', 'openai'])
            ->first();

        if ($primary) {
            return $primary;
        }

        // Fall back to any enabled tool-use-capable provider
        $fallback = $user->aiProviders()
            ->where('is_enabled', true)
            ->whereIn('provider', ['claude', 'openai'])
            ->first();

        if ($fallback) {
            return $fallback;
        }

        // Fall back to system-level config
        $systemApiKey = config('llm.providers.claude.api_key');
        if ($systemApiKey) {
            $systemProvider = new AIProvider();
            $systemProvider->provider = 'claude';
            $systemProvider->api_key = $systemApiKey;
            $systemProvider->model = config('llm.providers.claude.model', 'claude-sonnet-4-20250514');
            $systemProvider->settings = ['max_tokens' => config('llm.providers.claude.max_tokens', 4096)];

            return $systemProvider;
        }

        $openaiKey = config('llm.providers.openai.api_key');
        if ($openaiKey) {
            $systemProvider = new AIProvider();
            $systemProvider->provider = 'openai';
            $systemProvider->api_key = $openaiKey;
            $systemProvider->model = config('llm.providers.openai.model', 'gpt-4o');
            $systemProvider->settings = ['max_tokens' => config('llm.providers.openai.max_tokens', 4096)];

            return $systemProvider;
        }

        throw new \RuntimeException('No LLM provider configured. Please configure Claude or OpenAI in your AI provider settings.');
    }

    private function logRequest(User $user, string $provider, array $result, int $durationMs): void
    {
        if (!config('llm.logging_enabled', true)) {
            return;
        }

        try {
            AIRequestLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $result['model'],
                'mode' => 'ask_dana',
                'prompt' => '[ask-dana conversation turn]',
                'response' => $result['content'] ?? '[tool_use]',
                'input_tokens' => $result['tokens']['input'],
                'output_tokens' => $result['tokens']['output'],
                'total_tokens' => $result['tokens']['total'],
                'duration_ms' => $durationMs,
                'success' => true,
            ]);

            if (app()->bound(UsageTrackingService::class)) {
                app(UsageTrackingService::class)->recordLLM(
                    $provider,
                    $result['tokens']['input'],
                    $result['tokens']['output'],
                );
            }
        } catch (\Exception $e) {
            Log::warning('AskDanaLLMAdapter: failed to log request', ['error' => $e->getMessage()]);
        }
    }
}
