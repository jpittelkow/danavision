# ADR 007: Smart Add Feature with AI Product Identification

## Status

Accepted (Updated 2026-01-03)

## Date

2026-01-02 (Updated 2026-01-03)

## Context

Users want to quickly add products to their shopping lists by:

1. Taking a photo of a product
2. Having AI identify the product
3. Adding to a list with pre-filled data
4. Automatically finding prices (in background)

This needs to work across different AI providers and aggregate results for accuracy.

## Decision

We will implement a **Smart Add Feature** using a two-phase approach:

### Phase 1: Product Identification

The first phase focuses on identifying what product the user wants to add:

1. User uploads image OR enters text search
2. AI analyzes and returns up to 5 product suggestions
3. Each suggestion includes: product name, brand, model, category, UPC, confidence score
4. Product images are fetched for visual confirmation
5. User selects which product is correct

### Phase 2: Add to List + Background Price Search

Once the user selects a product:

1. Inline form appears with pre-filled data (name, UPC, etc.)
2. User selects target shopping list
3. User can set target price, priority, notes
4. On submit, item is added to list immediately
5. Background job (`SearchItemPrices`) runs to find prices across retailers
6. Price results are added to the item asynchronously

### AI Product Identification

1. Support multiple AI providers (Claude, OpenAI, Gemini, Ollama)
2. Use `MultiAIService` to query all configured providers
3. Aggregate responses for higher confidence identification
4. Extract: product name, brand, model, category, UPC, is_generic, unit_of_measure
5. Return up to 5 ranked suggestions based on confidence

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    SmartAdd Page                         │
│  • Text Search OR Image Upload                          │
│  • Camera button (mobile)                               │
│  • Gallery selection                                    │
└─────────────────────────────────────────────────────────┘
                          │
                    POST /smart-add/identify
                          │
┌─────────────────────────────────────────────────────────┐
│                 SmartAddController                       │
│  • Parse image data / text query                        │
│  • Call MultiAIService for identification               │
│  • Return up to 5 product suggestions                   │
└─────────────────────────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
    ┌──────────┐   ┌──────────┐   ┌──────────┐
    │  Claude  │   │  OpenAI  │   │  Gemini  │
    └──────────┘   └──────────┘   └──────────┘
          └───────────────┬───────────────┘
                          │
                   Up to 5 Product Suggestions
                   (with images, confidence scores)
                          │
                          ▼
               User Selects Product
                          │
                    POST /smart-add/add
                          │
               Item Created in Database
                          │
           ┌──────────────┴──────────────┐
           │   SearchItemPrices Job      │
           │   (Background Queue)        │
           │   • Find prices at retailers │
           │   • Update item with prices  │
           └─────────────────────────────┘
```

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/smart-add` | GET | Smart Add page |
| `/smart-add/identify` | POST | AI product identification (returns suggestions) |
| `/smart-add/add` | POST | Add item to list (dispatches price search job) |

### Navigation

Smart Add is the **primary action** in the navigation:
- First item in sidebar
- Distinct styling (gradient accent)
- Sparkles icon for visual emphasis

### Mobile Experience

- Camera button for direct photo capture
- Gallery selection as alternative
- Touch-friendly product selection
- Large touch targets for accessibility

### Generic Items Support

The system handles both specific products and generic items:
- Specific: Sony WH-1000XM5 (has UPC, model number)
- Generic: Bananas, Ground Beef (sold by weight, no UPC)

Generic items are identified by setting `is_generic: true` and include a `unit_of_measure` (lb, oz, gallon, each, dozen, etc.)

## Consequences

### Positive

- Streamlined product addition workflow
- Higher identification accuracy via AI aggregation
- User confirms product before adding (reduces errors)
- Non-blocking UX (price search runs in background)
- Works across multiple AI providers
- Mobile-optimized with camera support
- Pre-filled data reduces user input
- Supports both specific products and generic items

### Negative

- Requires AI provider configuration
- Two-step flow (identify, then add)
- Price data arrives asynchronously (not instant)
- Accuracy depends on image quality
- Multiple AI calls may have latency

## Related Decisions

- [ADR 002: AI Provider Abstraction](002-ai-provider-abstraction.md) - AI service architecture
- [ADR 003: Price API Abstraction](003-price-api-abstraction.md) - Price search integration
- [ADR 004: Mobile-First Architecture](004-mobile-first-architecture.md) - Camera integration
