# LLM Model Discovery Roadmap

Automatically fetch and display available models when an API key is entered for each LLM provider.

**Priority**: MEDIUM  
**Status**: Complete (2026-01-29)  
**Last Updated**: 2026-01-29

**Dependencies**:
- ~~[Env to Database Migration](env-to-database-roadmap.md)~~ - Complete (LLM settings stored in database)

---

## Task Checklist

- [x] Create backend model discovery service
- [x] Add API endpoints for fetching models from each provider (discover-models, test-key)
- [x] Add API key input fields to LLM configuration page
- [x] Implement dynamic model dropdown that populates on Fetch Models
- [x] Add loading states and error handling for model fetching
- [x] Cache discovered models to reduce API calls (server-side 1h TTL)
- [x] Add model validation before saving (test key validates credentials)
- [x] Update documentation

---

## 1. Overview

**Purpose**: When an administrator enters an API key for an LLM provider (OpenAI, Anthropic, etc.), automatically query that provider's API to fetch all available models and display them in a dropdown for selection.

**Benefits**:
- Users see exactly which models their API key has access to
- No need to manually update model lists when providers add new models
- Reduces configuration errors from typos in model names
- Provides validation that the API key is valid

**Current State**: LLM provider selection is hardcoded; no API key storage or model discovery exists.

---

## 2. Provider API Model Endpoints

### 2.1 Provider Model Listing APIs

| Provider | Endpoint | Auth Method |
|----------|----------|-------------|
| OpenAI | `GET https://api.openai.com/v1/models` | Bearer token |
| Anthropic (Claude) | `GET https://api.anthropic.com/v1/models` | `x-api-key` header |
| Google (Gemini) | `GET https://generativelanguage.googleapis.com/v1beta/models` | API key param |
| Azure OpenAI | `GET https://{endpoint}/openai/deployments?api-version=2024-02-01` | API key header |
| Ollama | `GET http://{host}/api/tags` | None (local) |
| AWS Bedrock | AWS SDK `ListFoundationModels` | IAM credentials |

### 2.2 Response Handling

Each provider returns models differently; normalize to:

```php
[
    [
        'id' => 'gpt-4-turbo',           // Model ID to use in API calls
        'name' => 'GPT-4 Turbo',         // Display name
        'provider' => 'openai',          // Provider identifier
        'capabilities' => ['chat', 'vision'], // Optional capabilities
    ],
    // ...
]
```

---

## 3. Backend Implementation

### 3.1 Model Discovery Service

```php
// backend/app/Services/LLMModelDiscoveryService.php

class LLMModelDiscoveryService
{
    public function discoverModels(string $provider, array $credentials): array
    {
        return match ($provider) {
            'openai' => $this->discoverOpenAIModels($credentials['api_key']),
            'claude' => $this->discoverAnthropicModels($credentials['api_key']),
            'gemini' => $this->discoverGeminiModels($credentials['api_key']),
            'azure' => $this->discoverAzureModels($credentials),
            'ollama' => $this->discoverOllamaModels($credentials['host'] ?? 'http://localhost:11434'),
            'bedrock' => $this->discoverBedrockModels($credentials),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    public function validateApiKey(string $provider, array $credentials): bool
    {
        try {
            $models = $this->discoverModels($provider, $credentials);
            return count($models) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### 3.2 Provider-Specific Implementations

**OpenAI:**
```php
private function discoverOpenAIModels(string $apiKey): array
{
    $response = Http::withToken($apiKey)
        ->get('https://api.openai.com/v1/models');
    
    if (!$response->successful()) {
        throw new \Exception('Invalid API key or API error');
    }
    
    return collect($response->json('data'))
        ->filter(fn($m) => str_starts_with($m['id'], 'gpt-'))
        ->map(fn($m) => [
            'id' => $m['id'],
            'name' => $this->formatModelName($m['id']),
            'provider' => 'openai',
        ])
        ->values()
        ->toArray();
}
```

**Anthropic (Claude):**
```php
private function discoverAnthropicModels(string $apiKey): array
{
    $response = Http::withHeaders([
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
    ])->get('https://api.anthropic.com/v1/models');
    
    // Handle response and normalize...
}
```

### 3.3 API Controller

```php
// backend/app/Http/Controllers/Api/LLMModelController.php

class LLMModelController extends Controller
{
    public function discover(Request $request, LLMModelDiscoveryService $service)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:openai,claude,gemini,azure,ollama,bedrock',
            'api_key' => 'required_unless:provider,ollama|string',
            'host' => 'required_if:provider,ollama|string',
            'endpoint' => 'required_if:provider,azure|string',
            'region' => 'required_if:provider,bedrock|string',
        ]);

        try {
            $models = $service->discoverModels($validated['provider'], $validated);
            return response()->json(['models' => $models]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch models',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
```

### 3.4 Routes

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/llm-settings/discover-models', [LLMModelController::class, 'discover']);
});
```

---

## 4. Frontend Implementation

### 4.1 API Key Input Component

Add API key inputs for each provider on the LLM settings page:

```tsx
// Provider credentials section
<Card>
  <CardHeader>
    <CardTitle>Provider API Keys</CardTitle>
    <CardDescription>
      Enter API keys to see available models for each provider.
    </CardDescription>
  </CardHeader>
  <CardContent className="space-y-4">
    {providers.map(provider => (
      <ProviderKeyInput
        key={provider.id}
        provider={provider}
        onKeyChange={handleKeyChange}
        onModelsDiscovered={handleModelsDiscovered}
      />
    ))}
  </CardContent>
</Card>
```

### 4.2 Model Discovery Flow

```tsx
const ProviderKeyInput = ({ provider, onModelsDiscovered }) => {
  const [apiKey, setApiKey] = useState('');
  const [isDiscovering, setIsDiscovering] = useState(false);
  const [models, setModels] = useState<Model[]>([]);
  const [error, setError] = useState<string | null>(null);

  const discoverModels = async () => {
    if (!apiKey) return;
    
    setIsDiscovering(true);
    setError(null);
    
    try {
      const response = await api.post('/llm-settings/discover-models', {
        provider: provider.id,
        api_key: apiKey,
      });
      setModels(response.data.models);
      onModelsDiscovered(provider.id, response.data.models);
    } catch (err) {
      setError('Failed to fetch models. Check your API key.');
    } finally {
      setIsDiscovering(false);
    }
  };

  // Debounced discovery on key change
  useEffect(() => {
    const timer = setTimeout(discoverModels, 1000);
    return () => clearTimeout(timer);
  }, [apiKey]);

  return (
    <div className="space-y-2">
      <Label>{provider.name} API Key</Label>
      <div className="flex gap-2">
        <Input
          type="password"
          value={apiKey}
          onChange={(e) => setApiKey(e.target.value)}
          placeholder={`Enter ${provider.name} API key`}
        />
        <Button
          variant="outline"
          onClick={discoverModels}
          disabled={!apiKey || isDiscovering}
        >
          {isDiscovering ? <Spinner /> : 'Fetch Models'}
        </Button>
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
      {models.length > 0 && (
        <Select>
          <SelectTrigger>
            <SelectValue placeholder="Select a model" />
          </SelectTrigger>
          <SelectContent>
            {models.map(model => (
              <SelectItem key={model.id} value={model.id}>
                {model.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </div>
  );
};
```

### 4.3 UX Considerations

- **Debounced API calls**: Wait 1 second after typing stops before fetching
- **Loading indicator**: Show spinner while fetching models
- **Error states**: Clear error messages for invalid keys
- **Key masking**: Show API keys as `sk-****...****` after entry
- **Cache models**: Store discovered models to avoid re-fetching on page revisit
- **Validation feedback**: Green checkmark when key is valid and models loaded

---

## 5. Caching Strategy

### 5.1 Server-Side Caching

```php
// Cache discovered models for 1 hour
public function discoverModels(string $provider, array $credentials): array
{
    $cacheKey = "llm_models:{$provider}:" . md5(json_encode($credentials));
    
    return Cache::remember($cacheKey, 3600, function () use ($provider, $credentials) {
        return $this->fetchModelsFromProvider($provider, $credentials);
    });
}
```

### 5.2 Client-Side Caching

- Store models in component state
- Persist to localStorage for page revisits
- Refresh on explicit "Refresh Models" button click

---

## 6. Database Schema Updates

### 6.1 Store Provider Credentials

Extend `system_settings` table usage:

| Group | Key | Value | Encrypted |
|-------|-----|-------|-----------|
| llm | openai_api_key | sk-... | true |
| llm | openai_model | gpt-4-turbo | false |
| llm | claude_api_key | sk-ant-... | true |
| llm | claude_model | claude-3-opus-20240229 | false |
| llm | gemini_api_key | AI... | true |
| llm | gemini_model | gemini-pro | false |

### 6.2 Store Cached Models (Optional)

```sql
CREATE TABLE llm_cached_models (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    model_id VARCHAR(100) NOT NULL,
    model_name VARCHAR(200) NOT NULL,
    capabilities JSON NULL,
    cached_at TIMESTAMP NOT NULL,
    UNIQUE KEY unique_model (provider, model_id)
);
```

---

## 7. Implementation Phases

### Phase 1: Core Infrastructure
- [x] Create `LLMModelDiscoveryService`
- [x] Implement OpenAI model discovery
- [x] Add API endpoint for model discovery (discover-models, test-key)
- [x] Add basic API key input to frontend (Test + Fetch Models in Add Provider dialog)

### Phase 2: Expand Provider Support
- [x] Add Anthropic (Claude) discovery
- [x] Add Google (Gemini) discovery
- [x] Add Ollama discovery
- [x] Add Azure OpenAI discovery (requires endpoint config)
- [x] Add AWS Bedrock discovery (requires IAM config)

### Phase 3: Polish & Caching
- [x] Add server-side model caching (1h TTL)
- [x] Add client-side caching (sessionStorage, 1h TTL)
- [x] Add "Refresh Models" button
- [x] Improve error messages per provider (sanitized in controller)
- [x] Add model capability indicators (chat, vision, etc.)

### Phase 4: Documentation
- [x] Update user docs for LLM configuration
- [x] Add troubleshooting guide for API key issues (see Troubleshooting section below)
- [x] Document supported providers and requirements

---

## 8. Troubleshooting

### Azure OpenAI

- **"Azure OpenAI endpoint is required"** — Enter the full base URL (e.g. `https://your-resource.openai.azure.com`) in the endpoint field. Do not include a path or trailing slash.
- **"Azure OpenAI API error"** — Verify the API key is correct and has access to the Azure OpenAI resource. Ensure the key is from **Keys and Endpoint** in the Azure portal for your resource.
- **No deployments returned** — Create at least one model deployment in the Azure OpenAI Studio (Models > Deployments) and ensure the API key has **Cognitive Services User** or equivalent role.

### AWS Bedrock

- **"AWS access key and secret key are required"** — Use IAM credentials with `bedrock:ListFoundationModels` (and at runtime `bedrock:InvokeModel`) permissions. Do not use root account keys.
- **"AWS Bedrock API error"** — Check that the region supports Bedrock (e.g. `us-east-1`, `us-west-2`). Verify the IAM user/role has the required Bedrock permissions.
- **No models returned** — Ensure the region is correct and that Bedrock model access is enabled in the AWS console for the models you need.

### General

- **"Failed to fetch models"** — Ensure your API key or credentials are valid and not expired. For cloud providers, check service status and network connectivity.
- **Rate limits** — If you see throttling errors, wait a moment and use the **Refresh** button to retry.

---

## 9. Testing

### 9.1 Backend Tests

```php
public function test_discovers_openai_models_with_valid_key()
{
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4', 'object' => 'model'],
                ['id' => 'gpt-3.5-turbo', 'object' => 'model'],
            ]
        ], 200),
    ]);

    $service = new LLMModelDiscoveryService();
    $models = $service->discoverModels('openai', ['api_key' => 'test-key']);

    $this->assertCount(2, $models);
    $this->assertEquals('gpt-4', $models[0]['id']);
}

public function test_returns_error_for_invalid_key()
{
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['error' => 'Invalid key'], 401),
    ]);

    $service = new LLMModelDiscoveryService();
    
    $this->expectException(\Exception::class);
    $service->discoverModels('openai', ['api_key' => 'invalid-key']);
}
```

### 9.2 E2E Tests

- Test entering valid API key shows models
- Test entering invalid API key shows error
- Test model selection persists after save
- Test page reload shows cached models

---

## 10. Security Considerations

- API keys stored encrypted in database (using existing `is_encrypted` column)
- API keys never logged
- API keys masked in UI (`sk-****...****`)
- Model discovery endpoint requires admin authentication
- Rate limit model discovery requests

---

## 11. Files Reference

**Backend** (to create):
- `backend/app/Services/LLMModelDiscoveryService.php`
- `backend/app/Http/Controllers/Api/LLMModelController.php`

**Backend** (to modify):
- `backend/routes/api.php` - Add discovery route

**Frontend** (to modify):
- `frontend/app/(dashboard)/configuration/llm-system/page.tsx` - Add API key inputs and model dropdowns

---

## 12. Related Roadmaps and Docs

- [Env to Database Migration](env-to-database-roadmap.md) - Foundation for storing LLM credentials
- [Integration Settings](integration-settings-roadmap.md) - Similar pattern for other integrations
- [Recipe: Add LLM Provider](../ai/recipes/add-llm-provider.md) - How to add a new provider and wire it into model discovery (Test Key / Fetch Models)