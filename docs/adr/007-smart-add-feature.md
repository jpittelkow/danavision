# ADR 007: Smart Add Feature with AI Aggregation

## Status

Accepted

## Date

2026-01-02

## Context

Users want to quickly add products to their shopping lists by:

1. Taking a photo of a product
2. Having AI identify the product
3. Automatically searching for prices
4. Adding to a list with pre-filled data

This needs to work across different AI providers and aggregate results for accuracy.

## Decision

We will implement a **Smart Add Feature** that combines:

### AI Image Analysis

1. Support multiple AI providers (Claude, OpenAI, Gemini, Ollama)
2. Use `MultiAIService` to query all configured providers
3. Aggregate responses for higher confidence identification
4. Extract: product name, brand, model, category, search terms

### Price Search Integration

After identifying the product:
1. Search configured price API for the identified product
2. Display prices from multiple vendors
3. Show product images from search results

### Add to List Flow (Two-Phase)

**Phase 1 - Search:**
1. User uploads image OR enters text search
2. AI analyzes and identifies product (if image)
3. Price search returns unique products with lowest prices and UPC codes
4. Results displayed as simplified cards with "Add" button

**Phase 2 - Add Modal:**
1. User clicks "Add" on a product card
2. Modal opens with form pre-filled (name, price, retailer, UPC)
3. Modal fetches detailed pricing via `POST /smart-add/price-details`
4. User can select different retailer/price or keep default
5. User selects target list and submits
6. Item created with all data

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    SmartAdd Page                         │
│  • Image Upload (camera on mobile)                      │
│  • Text Search Alternative                              │
│  • List Selector                                        │
└─────────────────────────────────────────────────────────┘
                          │
                    POST /smart-add/analyze
                          │
┌─────────────────────────────────────────────────────────┐
│                 SmartAddController                       │
│  • Parse image data                                     │
│  • Call MultiAIService for aggregation                  │
│  • Search PriceApiService                               │
└─────────────────────────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
    ┌──────────┐   ┌──────────┐   ┌──────────┐
    │  Claude  │   │  OpenAI  │   │  Gemini  │
    └──────────┘   └──────────┘   └──────────┘
          └───────────────┬───────────────┘
                          │
                   Aggregated Response
                          │
                          ▼
                ┌─────────────────┐
                │  PriceApiService │
                └─────────────────┘
                          │
                          ▼
              Price Results + Product Info
```

### Navigation

Smart Add is the **primary action** in the navigation:
- First item in sidebar
- Distinct styling (gradient accent)
- Sparkles icon for visual emphasis

### Mobile Experience

- Camera button for direct photo capture
- Gallery selection as alternative
- Touch-friendly result selection
- Quick add to list

## Consequences

### Positive

- Streamlined product addition workflow
- Higher identification accuracy via AI aggregation
- Works across multiple AI providers
- Mobile-optimized with camera support
- Pre-filled data reduces user input

### Negative

- Requires AI provider configuration
- Multiple API calls (AI + price search)
- Potential latency with multiple AI providers
- Accuracy depends on image quality

## Related Decisions

- [ADR 002: AI Provider Abstraction](002-ai-provider-abstraction.md) - AI service architecture
- [ADR 003: Price API Abstraction](003-price-api-abstraction.md) - Price search integration
- [ADR 004: Mobile-First Architecture](004-mobile-first-architecture.md) - Camera integration
