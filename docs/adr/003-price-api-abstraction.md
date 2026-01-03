# ADR 003: Price API Provider Abstraction

## Status

Accepted

## Date

2024-12-30

## Context

DanaVision needs to fetch product prices from various online retailers. Different price API providers have different strengths:

- **SerpAPI**: Google Shopping results, broad coverage
- **Rainforest API**: Amazon-specific, detailed product data

Users may have API keys for different providers, and we want to support switching between them without code changes.

## Decision

We will implement a **Price API Provider Abstraction** with:

1. **PriceProviderInterface** - Common interface for all providers
2. **Provider implementations** - SerpAPI, Rainforest, etc.
3. **PriceApiService** - Factory/coordinator that selects the right provider
4. **User configuration** - API keys and provider selection stored per user

### Interface

```php
interface PriceProviderInterface
{
    public function isConfigured(): bool;
    public function search(string $query, array $options = []): array;
    public function testConnection(): bool;
}
```

### Service Usage

```php
$service = PriceApiService::forUser($userId);
$results = $service->search('Sony headphones');
```

### Configuration

Users configure their preferred provider and API key in Settings. The service automatically uses the configured provider.

## Consequences

### Positive

- Easy to add new price providers
- Users can choose their preferred provider
- Provider-specific logic isolated in implementations
- Graceful fallback when provider unavailable

### Negative

- Each provider may return slightly different data structures
- Need to normalize responses across providers
- Testing requires mocking multiple providers

## Related Decisions

- [ADR 002: AI Provider Abstraction](002-ai-provider-abstraction.md) - Similar pattern for AI providers
