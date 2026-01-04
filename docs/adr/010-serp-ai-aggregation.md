# ADR 010: SERP API + AI Aggregation Architecture

## Status

Accepted

## Date

2026-01-04

## Context

DanaVision needs to provide accurate, real-time pricing data for products. The previous implementation had an "AI-only fallback" mode where if SERP API was unavailable, the AI would generate prices based on its training data.

This approach had critical flaws:
1. AI training data is outdated (often 6+ months old)
2. Prices change frequently (daily/weekly)
3. AI "hallucinates" prices that seem reasonable but are incorrect
4. Users made purchasing decisions based on inaccurate pricing

## Decision

We implemented a strict SERP API-first architecture where:

1. **SERP API is the ONLY source of pricing data**
2. **AI is used purely for intelligent aggregation and analysis**
3. **AI does NOT generate or estimate prices**

### Architecture Flow

```
User Search Query
       ↓
   SERP API
       ↓ (raw JSON with real prices)
  AI Service
       ↓ (structured, validated output)
   Response
```

### SERP API Usage

We use multiple SERP API engines:
- `google_shopping`: Primary source for product prices
- `google`: Fallback for organic results with price mentions
- `google_local`: Store availability when shop_local is enabled
- `google_product`: Detailed single product information

### AI Aggregation Role

AI is given the raw SERP data and tasked with:
1. Parsing and structuring results
2. Ranking by relevance and local store preference
3. Identifying product variants (new vs used)
4. Extracting metadata (UPC, stock status)
5. **NOT** adding any prices not in the original data

### Validation Layer

To prevent AI from fabricating prices:
1. Original SERP prices are cached
2. AI output is cross-referenced against original data
3. Any price not in SERP data is rejected with logging
4. Tolerance of 1% for float precision differences

### AI Prompt

The prompt explicitly instructs:
```
*** CRITICAL INSTRUCTIONS ***
1. You MUST only use prices that appear in the search results below.
2. Do NOT invent, estimate, guess, or hallucinate ANY prices.
3. Every price in your output MUST match a price from the input data.
4. If you cannot find a price in the data, use null for that result.
```

### Error Handling

If SERP API returns no results:
- Return empty results to user
- Do NOT attempt to use AI to generate prices
- Log the empty result for debugging
- Show user-friendly message: "No products found matching your search"

## Alternatives Considered

### AI-Only Price Search
- **Pros**: Works without API key, simpler implementation
- **Cons**: Inaccurate prices, outdated data, misleading users
- **Why rejected**: Fundamentally unreliable for pricing data

### Multiple Price APIs
- **Pros**: Redundancy, potentially more coverage
- **Cons**: Complex aggregation, different data formats, cost
- **Why rejected**: SERP API (Google Shopping) has comprehensive coverage

### Manual Price Database
- **Pros**: Full control over data
- **Cons**: Massive maintenance burden, quickly outdated
- **Why rejected**: Not scalable

## Consequences

### Positive

1. **Accuracy**: All prices come from real, current listings
2. **Transparency**: SERP data is logged for verification
3. **Trust**: Users can trust the prices shown
4. **Debugging**: Can trace any price back to its source

### Negative

1. **Dependency**: Requires SERP API to be available
2. **Cost**: SERP API has per-request pricing
3. **Empty Results**: Some searches may return no prices
4. **No Offline Mode**: Cannot work without internet/API

## Implementation Notes

1. SERP API key is configured per-user in Settings
2. Raw SERP data is stored in `ai_request_logs.serp_data`
3. `validateAgainstSerpData()` method validates AI output
4. Discrepancies are logged for monitoring

## Code Changes

Key files modified:
- `app/Services/AI/AIPriceSearchService.php`: Removed AI-only fallback
- `app/Services/Search/WebSearchService.php`: Multi-engine support
- `app/Models/AIRequestLog.php`: Added `serp_data` field
- `app/Jobs/AI/PriceSearchJob.php`: SERP-first implementation

## Related ADRs

- ADR 002: AI Provider Abstraction
- ADR 003: Price API Abstraction
- ADR 009: AI Background Job System
