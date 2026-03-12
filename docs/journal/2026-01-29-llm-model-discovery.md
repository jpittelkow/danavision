# LLM Model Discovery - 2026-01-29

## Overview

Implemented dynamic LLM model discovery so administrators can validate API keys and fetch available models from provider APIs when adding an AI provider. The Add Provider dialog now uses a Test button for credential validation and a Fetch Models button to populate the model dropdown from live provider APIs instead of hardcoded lists.

## Implementation Approach

- **Backend**: New `LLMModelDiscoveryService` calls each provider’s models/list endpoint (OpenAI, Anthropic, Gemini, Ollama), normalizes responses to `{ id, name, provider, capabilities? }`, and caches results for 1 hour per provider+credentials.
- **Validation**: `validateCredentials()` uses the same discovery path; valid means at least one model returned. No separate validation endpoint per provider.
- **Controller**: New `LLMModelController` with `testKey` and `discover` actions. Both accept `provider`, optional `api_key`, and optional `host` (Ollama). Errors are sanitized (no raw API messages) before returning to the client.
- **Frontend**: Add Provider dialog reordered to API key (or Ollama host) first, then Test, then Fetch Models, then model dropdown. State for key validation and discovered models is reset when the dialog closes or the provider selection changes.
- **Routes**: `POST /llm-settings/test-key` and `POST /llm-settings/discover-models` added under the existing `llm-settings` prefix (auth + `can:manage-settings`).

## Challenges Encountered

- Anthropic list-models response shape (array vs nested key) differed from docs; handled by checking both `data` and `models` and falling back to full JSON.
- Gemini model `name` is `models/gemini-1.5-flash`; we strip the `models/` prefix for the `id` used in API calls.

## Observations

- Server-side caching (1h TTL) reduces provider API load when users reopen the dialog or click Fetch Models again.
- Test and Fetch Models are separate actions so users can validate the key before loading a potentially large model list.
- Azure OpenAI and AWS Bedrock were left out of discovery (endpoint/IAM config); they can be added later using the same pattern.

## Trade-offs

- Model discovery is admin-only (same as adding providers). Letting non-admins discover models would require either exposing these endpoints to all authenticated users or duplicating logic.
- Sanitized error messages avoid leaking provider-specific details but may make debugging harder; backend logs still contain full exceptions.

## Next Steps (Future Considerations)

- Add Azure OpenAI and Bedrock to discovery when endpoint/region config is available.
- Optional: “Refresh models” that bypasses cache for the current provider.
- Optional: E2E tests for Add Provider flow (enter key, test, fetch models, select, save).

## Testing Notes

- Manual: Add Provider → enter OpenAI/Claude/Gemini API key → Test (checkmark or error) → Fetch Models → confirm dropdown lists models → select and save.
- Ollama: Enter host (e.g. `http://localhost:11434`) → Test → Fetch Models → confirm local models appear.
- Invalid key: Test and Fetch Models both return clear error messages without raw API text.
