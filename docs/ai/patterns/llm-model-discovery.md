# LLMModelDiscoveryService Pattern

Use `LLMModelDiscoveryService` to discover available models for an LLM provider using credentials.

- `discoverModels(provider, credentials)` — Returns normalized model list with server-side caching (1h TTL).
- `validateCredentials(provider, credentials)` — Returns true if credentials are valid (at least one model returned).

Each provider has a private `discover{Provider}Models()` method that:

1. Calls the provider's models/deployments API.
2. Normalizes the response to `[{id, name, provider, capabilities?}]`.
3. Filters to relevant models (e.g. chat or text-generation only).

## Credential Requirements

| Provider | Credentials |
|----------|-------------|
| openai, claude, gemini | `api_key` |
| ollama | `host` |
| azure | `endpoint`, `api_key` |
| bedrock | `region`, `access_key`, `secret_key` |

## Cache Key

The service cache key must include a hash of all credential fields used by the provider (e.g. endpoint for Azure, access_key/secret_key for Bedrock) so different accounts do not share the same cached model list.

**Key files:**
- `backend/app/Services/LLMModelDiscoveryService.php`
- `backend/app/Http/Controllers/Api/LLMModelController.php`

**Related:**
- [Recipe: Add LLM Provider](../recipes/add-llm-provider.md)
- [LLM Model Discovery Roadmap](../../plans/llm-model-discovery-roadmap.md)

## Implementation Journal

- [LLM Model Discovery (2026-01-29)](../../journal/2026-01-29-llm-model-discovery.md)
- [LLM Settings Consolidation (2026-01-29)](../../journal/2026-01-29-llm-settings-consolidation.md)
