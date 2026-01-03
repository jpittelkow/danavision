# ADR 002: AI Provider Abstraction

## Status
Accepted

## Date
2025-01-01

## Context
DanaVision uses AI for:
- Product image analysis and identification
- Price recommendation analysis
- Smart shopping suggestions

Users should be able to choose their preferred AI provider based on cost, performance, or privacy preferences.

## Decision
We will implement a provider-agnostic AI service with the following supported providers:

1. **Claude** (Anthropic) - Default recommended option
2. **GPT-4** (OpenAI) - Alternative for OpenAI users
3. **Gemini** (Google) - Google Cloud integration
4. **Local** (Future) - Ollama/local models for privacy

The `AIService` class provides a unified interface:
- `complete(prompt)` - Text completion
- `analyzeImage(image, prompt)` - Vision/image analysis
- `isAvailable()` - Check if configured
- `getProvider()` / `getModel()` - Get current settings

Configuration is stored per-user in the `settings` table with encrypted API keys.

## Consequences

### Positive
- Users control their AI costs and provider choice
- Easy to add new providers
- Encrypted API key storage
- Graceful fallback if AI unavailable

### Negative
- Different providers may have varying capabilities
- Need to maintain multiple API integrations
- Response quality may vary by provider

## Related Decisions
- [ADR-003: Price API Abstraction](003-price-api-abstraction.md)
