# Recipe: Add LLM Provider

Step-by-step guide to add a new LLM (Large Language Model) provider so users can select it in **Configuration > AI** and use it for queries. Optionally enable **model discovery** (Test Key / Fetch Models in the Add Provider dialog).

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Services/LLM/Providers/{Name}Provider.php` | Create | Provider implementation |
| `backend/app/Services/LLM/LLMOrchestrator.php` | Modify | Register provider in `getProviderInstance()` |
| `backend/config/llm.php` | Modify | Add provider config and council weight |
| `backend/app/Services/LLMModelDiscoveryService.php` | Modify | Add discovery (Test Key / Fetch Models) |
| `backend/app/Http/Controllers/Api/LLMModelController.php` | Modify | Allow new provider in validation rules |
| `frontend/app/(dashboard)/configuration/ai/page.tsx` | Modify | Add to `providerTemplates` |

---

## Step 1: Create the Provider Class

Providers implement `LLMProviderInterface` and are constructed with the user's `AIProvider` model (API key, model, settings from DB).

**Interface** (see `backend/app/Services/LLM/LLMProviderInterface.php`):

- `query(string $prompt, ?string $systemPrompt = null): array` — returns `['content' => string, 'tokens' => ['input' => int, 'output' => int, 'total' => int]]`
- `visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array` — same shape
- `supportsVision(): bool`
- `getName(): string`

**Example** (follow existing providers):

```php
<?php
// backend/app/Services/LLM/Providers/ExampleProvider.php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use Illuminate\Support\Facades\Http;

class ExampleProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->apiKey = $config->api_key;
        $this->model = $config->model ?? config('llm.providers.example.model', 'example-model-v1');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.example.max_tokens', 4096);
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = Http::timeout(config('llm.timeout', 120))
            ->withToken($this->apiKey)
            ->post('https://api.example.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Example API error: ' . $response->body());
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
        // Implement if provider supports vision; otherwise throw or delegate to query.
        throw new \RuntimeException('Example provider does not support vision');
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'example';
    }
}
```

**Reference implementations:** `OpenAIProvider.php`, `AnthropicProvider.php`, `GeminiProvider.php`, `OllamaProvider.php`.

---

## Step 2: Register in LLMOrchestrator

In `backend/app/Services/LLM/LLMOrchestrator.php`, add your provider to the `getProviderInstance()` match:

```php
private function getProviderInstance(string $providerName, AIProvider $config): LLMProviderInterface
{
    // ...
    $this->providerInstances[$key] = match ($providerName) {
        'claude' => new AnthropicProvider($config),
        'openai' => new OpenAIProvider($config),
        'gemini' => new GeminiProvider($config),
        'ollama' => new OllamaProvider($config),
        'azure' => new AzureOpenAIProvider($config),
        'bedrock' => new BedrockProvider($config),
        'example' => new ExampleProvider($config),  // add this
        default => throw new \InvalidArgumentException("Unknown provider: {$providerName}"),
    };
    // ...
}
```

---

## Step 3: Add to config/llm.php

In `backend/config/llm.php`, add your provider under `'providers'` and, if using council mode, under `'council' => 'weights'`:

```php
'providers' => [
    // ... existing providers ...
    'example' => [
        'name' => 'Example LLM',
        'driver' => 'example',
        'enabled' => !empty(env('EXAMPLE_LLM_API_KEY')),
        'api_key' => env('EXAMPLE_LLM_API_KEY'),
        'model' => env('EXAMPLE_LLM_MODEL', 'example-model-v1'),
        'max_tokens' => env('EXAMPLE_LLM_MAX_TOKENS', 4096),
        'supports_vision' => false,
        'supports_tools' => false,
    ],
],
// ...
'council' => [
    'weights' => [
        // ... existing ...
        'example' => 1.0,
    ],
],
```

---

## Step 4: Add Model Discovery (Test Key / Fetch Models)

To support **Test** and **Fetch Models** in the Add Provider dialog, wire the new provider into model discovery.

### 4.1 LLMModelDiscoveryService

In `backend/app/Services/LLMModelDiscoveryService.php`:

1. Add a case in `fetchModelsFromProvider()`:

```php
private function fetchModelsFromProvider(string $provider, array $credentials): array
{
    return match ($provider) {
        'openai' => $this->discoverOpenAIModels($credentials['api_key'] ?? ''),
        'claude' => $this->discoverAnthropicModels($credentials['api_key'] ?? ''),
        'gemini' => $this->discoverGeminiModels($credentials['api_key'] ?? ''),
        'ollama' => $this->discoverOllamaModels($credentials['host'] ?? 'http://localhost:11434'),
        'azure' => $this->discoverAzureModels($credentials['endpoint'] ?? '', $credentials['api_key'] ?? ''),
        'bedrock' => $this->discoverBedrockModels(
            $credentials['region'] ?? 'us-east-1',
            $credentials['access_key'] ?? '',
            $credentials['secret_key'] ?? ''
        ),
        'example' => $this->discoverExampleModels($credentials['api_key'] ?? ''),  // add your provider
        default => throw new \InvalidArgumentException("Unknown or unsupported provider: {$provider}"),
    };
}
```

Also update `cacheKey()` to include any credential fields your provider uses (e.g. `endpoint` for Azure, `region`/`access_key`/`secret_key` for Bedrock) so different accounts do not share cached model lists.

2. Add a private method that returns the same shape as other discover methods:

```php
/**
 * @return array<int, array{id: string, name: string, provider: string, capabilities?: array<string>}>
 */
private function discoverExampleModels(string $apiKey): array
{
    $response = Http::timeout(15)
        ->withToken($apiKey)
        ->get('https://api.example.com/v1/models');

    if (!$response->successful()) {
        $body = $response->json();
        $message = $body['error']['message'] ?? $response->body();
        throw new \RuntimeException('Example API error: ' . $message);
    }

    $data = $response->json('data', []);
    $models = [];
    foreach ($data as $m) {
        $id = $m['id'] ?? '';
        if ($id === '') continue;
        $models[] = [
            'id' => $id,
            'name' => $m['name'] ?? $id,
            'provider' => 'example',
            'capabilities' => ['chat'],
        ];
    }
    return array_values($models);
}
```

Use the provider’s real models/list endpoint and normalize to `id`, `name`, `provider`, and optional `capabilities`. See `discoverOpenAIModels` / `discoverAnthropicModels` for patterns.

### 4.2 LLMModelController

In `backend/app/Http/Controllers/Api/LLMModelController.php`, allow the new provider in both endpoints:

- `testKey()`: validation rule `'provider' => ['required', 'string', 'in:openai,claude,gemini,ollama,azure,bedrock,example']`
- `discover()`: same `'provider' => ... 'in:...,example'`

Add `example` (or your provider id) to the existing `in:` list in both places. For Azure include `endpoint` in validation and credentials; for Bedrock include `region`, `access_key`, `secret_key`.

---

## Step 5: Add to Frontend (Configuration > AI)

In `frontend/app/(dashboard)/configuration/ai/page.tsx`, add an entry to `providerTemplates`:

```ts
const providerTemplates: ProviderTemplate[] = [
  // ... existing (claude, openai, gemini, ollama, azure, bedrock) ...
  {
    id: "example",
    name: "Example LLM",
    requires_api_key: true,
    supports_vision: false,
    supports_discovery: true,
    // Optional: requires_endpoint: true (Azure-style), or requires_aws_credentials: true (Bedrock-style)
  },
];
```

- `id` must match the backend provider key (e.g. `config/llm.php` and orchestrator).
- Use `supports_discovery: true` only if you implemented Step 4; otherwise the Add Provider dialog will not show Test / Fetch Models for this provider.
- For Azure-style providers (endpoint + API key), set `requires_endpoint: true` and add endpoint input UI and request payload.
- For AWS-style providers (region + access key + secret key), set `requires_aws_credentials: true` and add region/credentials UI and request payload.

---

## Checklist

- [ ] Provider class created implementing `LLMProviderInterface` (`query`, `visionQuery`, `supportsVision`, `getName`)
- [ ] Constructor accepts `AIProvider $config` and reads `api_key`, `model`, `settings`
- [ ] Provider registered in `LLMOrchestrator::getProviderInstance()`
- [ ] Provider and optional council weight added in `config/llm.php`
- [ ] (Optional) Model discovery: case and `discoverXxxModels()` added in `LLMModelDiscoveryService`
- [ ] (Optional) Model discovery: provider id added to `LLMModelController` validation `in:...` for `testKey` and `discover`
- [ ] Frontend: entry added to `providerTemplates` in `configuration/ai/page.tsx`
- [ ] Error handling and timeouts (e.g. `config('llm.timeout')`) in provider and discovery
- [ ] ADR reference: [ADR-006: LLM Orchestration Modes](../../adr/006-llm-orchestration-modes.md); model discovery: [LLM Model Discovery Roadmap](../../plans/llm-model-discovery-roadmap.md)

---

## Existing Providers for Reference

| Provider | Class | Discovery | Credentials |
|----------|--------|-----------|-------------|
| OpenAI | `OpenAIProvider.php` | `discoverOpenAIModels()` | `api_key` |
| Claude | `AnthropicProvider.php` | `discoverAnthropicModels()` | `api_key` |
| Gemini | `GeminiProvider.php` | `discoverGeminiModels()` | `api_key` |
| Ollama | `OllamaProvider.php` | `discoverOllamaModels()` | `host` |
| Azure OpenAI | `AzureOpenAIProvider.php` | `discoverAzureModels()` | `endpoint`, `api_key` |
| AWS Bedrock | `BedrockProvider.php` | `discoverBedrockModels()` | `region`, `access_key`, `secret_key` |

Use the existing discover methods in `LLMModelDiscoveryService` as templates for your provider’s models API and response shape.

## Implementation Journal

- [LLM Settings Consolidation (2026-01-29)](../../journal/2026-01-29-llm-settings-consolidation.md)
- [LLM Model Discovery (2026-01-29)](../../journal/2026-01-29-llm-model-discovery.md)
