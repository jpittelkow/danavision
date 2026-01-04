# ADR 007: Smart Add Feature with AI Product Identification

## Status

Accepted (Updated 2026-01-04)

## Date

2026-01-02 (Updated 2026-01-04)

## Context

Users want to quickly add products to their shopping lists by:

1. Taking a photo of a product
2. Having AI identify the product
3. Adding to a list with pre-filled data
4. Automatically finding prices (in background)

This needs to work across different AI providers and aggregate results for accuracy.

**Update 2026-01-04**: Added status monitoring, review queue, and streamlined add-to-item flow.

## Decision

We will implement a **Smart Add Feature** using a two-phase approach with persistent queue and real-time status monitoring:

### Phase 1: Product Identification with Status Monitor

The first phase focuses on identifying what product the user wants to add:

1. User uploads image OR enters text search
2. Identification runs as background job with real-time progress
3. **StatusMonitor** component shows step-by-step progress:
   - Initializing AI providers
   - Analyzing input
   - Querying AI providers (with provider count)
   - Aggregating results
   - Finding product images
4. Results saved to **Review Queue** for persistence
5. User can leave page and come back to review later
6. AI returns up to 5 product suggestions with confidence scores

### Review Queue

Products identified by AI are stored in a review queue:

1. **Persistence**: If user leaves page during identification, results are saved
2. **Queue Display**: Shows pending items at top of Smart Add page
3. **Actions**: User can Review (select product) or Dismiss
4. **Expiration**: Queue items expire after 7 days
5. **Database**: `smart_add_queue` table stores pending identifications

### Phase 2: Add to List + Immediate Navigation

Once the user selects a product:

1. Inline form appears with pre-filled data (name, UPC, etc.)
2. User selects target shopping list
3. User can set target price, priority, notes
4. On submit:
   - Item is added to list immediately
   - User is **redirected to the item page** (not list page)
   - Background job (`FirecrawlDiscoveryJob`) runs to find prices
5. Item page shows **PriceUpdateStatus** with real-time progress
6. Price results appear as they're found

### AI Product Identification

1. Support multiple AI providers (Claude, OpenAI, Gemini, Ollama)
2. Use `MultiAIService` to query all configured providers
3. Aggregate responses for higher confidence identification
4. Extract: product name, brand, model, category, UPC, image_url, is_generic, unit_of_measure
5. Return up to 5 ranked suggestions based on confidence

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    SmartAdd Page                         │
│  • Review Queue (pending items)                         │
│  • Text Search OR Image Upload                          │
│  • Camera button (mobile)                               │
│  • StatusMonitor (during identification)                │
└─────────────────────────────────────────────────────────┘
                          │
                    POST /smart-add/identify (async=true)
                          │
┌─────────────────────────────────────────────────────────┐
│           ProductIdentificationJob                       │
│  • Emits detailed progress logs                         │
│  • Queries AI providers                                 │
│  • Saves results to smart_add_queue                     │
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
         ┌────────────────────────────────────┐
         │       smart_add_queue              │
         │  • Stores pending identifications  │
         │  • User can review later           │
         │  • Auto-expires after 7 days       │
         └────────────────────────────────────┘
                          │
               User Selects Product
                          │
                    POST /smart-add/add
                          │
               Item Created in Database
                          │
         ┌────────────────┴────────────────┐
         │       Redirect to Item Page     │
         │   (User sees item immediately)  │
         └─────────────────────────────────┘
                          │
         ┌────────────────┴────────────────┐
         │   FirecrawlDiscoveryJob         │
         │   (Background Queue)            │
         │   • Find prices at retailers    │
         │   • Update item with prices     │
         │   • PriceUpdateStatus shows     │
         │     progress on item page       │
         └─────────────────────────────────┘
```

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/smart-add` | GET | Smart Add page with queue |
| `/smart-add/identify` | POST | AI product identification (async, returns job_id) |
| `/smart-add/add` | POST | Add item to list, redirect to item page |
| `/smart-add/queue` | GET | List pending queue items (JSON) |
| `/smart-add/queue/{id}` | DELETE | Dismiss queue item |
| `/smart-add/queue/{id}/add` | POST | Add queue item to list |

### Frontend Components

| Component | Description |
|-----------|-------------|
| `StatusMonitor` | Real-time progress display during AI identification |
| `ReviewQueue` | List of pending items awaiting user review |
| `PriceUpdateStatus` | Shows price search progress on item page |

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
- **Real-time status monitoring** during identification
- **Persistent queue** - results saved if user leaves page
- **Immediate navigation to item page** - user sees item right away
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
- Queue items need periodic cleanup

## Database Schema

### smart_add_queue table

```sql
CREATE TABLE smart_add_queue (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'pending',  -- pending, reviewed, added, dismissed
    product_data JSON,                      -- Array of product suggestions
    source_type VARCHAR(20),                -- 'image' or 'text'
    source_query TEXT,                      -- Text query (if text)
    source_image_path VARCHAR(255),         -- Stored image path (if image)
    ai_job_id BIGINT REFERENCES ai_jobs(id),
    added_item_id BIGINT REFERENCES list_items(id),
    selected_index TINYINT,                 -- 0-4, which product was selected
    providers_used JSON,                    -- ['claude', 'openai', ...]
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Related Decisions

- [ADR 002: AI Provider Abstraction](002-ai-provider-abstraction.md) - AI service architecture
- [ADR 003: Price API Abstraction](003-price-api-abstraction.md) - Price search integration
- [ADR 004: Mobile-First Architecture](004-mobile-first-architecture.md) - Camera integration
- [ADR 009: AI Job System](009-ai-job-system.md) - Background job architecture
