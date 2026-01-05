# ADR 014: Tiered Search URL Detection System

## Status

Accepted

## Date

2026-01-04

## Context

When adding stores to the Store Registry (ADR 012), we need to detect each store's search URL template. The original implementation had several limitations:

### Problems with Original Detection

1. **Static Homepage Scraping**: Only scraped the homepage, missing JavaScript-rendered search functionality
2. **Truncated AI Content**: AI only saw limited content, reducing accuracy
3. **Limited Pattern Detection**: Tried ~10 common patterns, missing many valid formats
4. **No Validation**: Didn't verify if detected URLs actually return search results
5. **Single Method**: No fallback if the primary method failed
6. **Expensive Failures**: Used Firecrawl API even for well-known stores

### Requirements

- High accuracy for major retailers (90%+)
- Cost-effective (minimize API usage)
- Fast for known stores (instant lookup)
- Graceful degradation when detection fails
- User opt-in for expensive methods

## Decision

We implemented a **4-tier detection system** that progressively tries more complex (and expensive) methods:

```
┌─────────────────────────────────────────────────────┐
│          Tier 1: Known Templates (instant)          │
│                                                     │
│  - 50+ pre-configured store templates               │
│  - Domain matching with partial match support       │
│  - Includes local stock/price metadata              │
│  - Cost: FREE                                       │
└─────────────────────────────────────────────────────┘
              ↓ Not found
┌─────────────────────────────────────────────────────┐
│        Tier 2: Common Patterns (fast)               │
│                                                     │
│  - 20+ common URL pattern formats                   │
│  - HTTP validation (tests if URL returns results)   │
│  - Product content verification                     │
│  - Cost: ~1 HTTP request per pattern tested         │
└─────────────────────────────────────────────────────┘
              ↓ No valid pattern
┌─────────────────────────────────────────────────────┐
│         Tier 3: AI Analysis (medium)                │
│                                                     │
│  - Scrapes homepage with Firecrawl                  │
│  - AI analyzes page structure                       │
│  - Detects search forms and action URLs             │
│  - Cost: ~5-10 Firecrawl credits                    │
└─────────────────────────────────────────────────────┘
              ↓ Failed, returns agent_available=true
┌─────────────────────────────────────────────────────┐
│      Tier 4: Firecrawl Agent (expensive)            │
│                                                     │
│  - Requires explicit user opt-in                    │
│  - Uses Firecrawl Actions API                       │
│  - Interacts with page: click, type, submit         │
│  - Captures resulting search URL                    │
│  - Cost: ~50-100 Firecrawl credits                  │
└─────────────────────────────────────────────────────┘
```

### Tier 1: Known Templates

Pre-configured templates for 50+ major US retailers:

```php
protected const KNOWN_STORE_TEMPLATES = [
    'walmart.com' => [
        'template' => 'https://www.walmart.com/search?q={query}',
        'local_stock' => true,
        'local_price' => false,
    ],
    'target.com' => [
        'template' => 'https://www.target.com/s?searchTerm={query}',
        'local_stock' => true,
        'local_price' => false,
    ],
    // ... 50+ more stores organized by category
];
```

Categories covered:
- **General Retailers**: Walmart, Target, Amazon, Dollar stores
- **Electronics**: Best Buy, Newegg, Micro Center, B&H Photo
- **Grocery**: Kroger, Albertsons, Publix, H-E-B, Meijer, Aldi
- **Home/Hardware**: Home Depot, Lowe's, Menards, Ace
- **Pharmacy**: CVS, Walgreens, Rite Aid
- **Pet**: Petco, PetSmart, Chewy
- **Sporting Goods**: Dick's, REI, Academy, Bass Pro
- **Auto Parts**: AutoZone, O'Reilly, Advance Auto, NAPA
- **Craft/Hobby**: Michaels, Joann, Hobby Lobby
- **Furniture**: IKEA, Wayfair

### Tier 2: Common Patterns

Expanded pattern list covering e-commerce platforms:

```php
$patterns = [
    // Standard patterns
    $base . '/search?q={query}',
    $base . '/search?query={query}',
    $base . '/search/{query}',
    $base . '/s?k={query}',
    
    // E-commerce platforms
    $base . '/catalogsearch/result/?q={query}', // Magento
    $base . '/shop/search?q={query}',
    $base . '/products?search={query}',
    
    // Legacy patterns
    $base . '/SearchDisplay?searchTerm={query}',
    $base . '/site/searchpage.jsp?st={query}',
    // ... 20+ patterns
];
```

Each pattern is tested with an HTTP request and validated for product content.

### Tier 3: AI Analysis

Uses Firecrawl to scrape the homepage and AI to analyze:
- Search form elements
- Action URLs
- Input field names
- Form submission methods

### Tier 4: Firecrawl Agent

Only used when explicitly requested by user. The agent:
1. Navigates to homepage
2. Finds search input (multiple selectors)
3. Types test query
4. Submits search
5. Captures resulting URL
6. Extracts template pattern

### Response Format

When detection fails, the response indicates agent availability:

```php
return [
    'success' => false,
    'error' => 'Could not detect search URL pattern',
    'agent_available' => true,
    'agent_cost_estimate' => '~50-100 API credits',
];
```

Frontend prompts user to try advanced detection:

```
┌─────────────────────────────────────────────┐
│ ⚡ Standard detection couldn't find the URL │
│                                             │
│ Try advanced detection using Firecrawl      │
│ Agent? This interacts with the page         │
│ directly to find the search URL.            │
│                                             │
│ Estimated cost: ~50-100 API credits         │
│                                             │
│ [Try Advanced Detection]                    │
└─────────────────────────────────────────────┘
```

## Alternatives Considered

### Single AI-Based Detection

- **Pros**: Simple implementation
- **Cons**: Expensive for every store, misses known patterns
- **Why rejected**: Cost inefficient for major retailers

### External URL Database Service

- **Pros**: Someone else maintains templates
- **Cons**: No such service exists, dependency risk
- **Why rejected**: Would need to build anyway

### Browser Extension Detection

- **Pros**: Free, can handle JavaScript
- **Cons**: Privacy concerns, requires extension install
- **Why rejected**: Poor UX, security concerns

### Manual Entry Only

- **Pros**: 100% accurate, no API cost
- **Cons**: Terrible UX, users won't do it
- **Why rejected**: Major friction for users

## Consequences

### Positive

1. **Cost Reduction**: 90%+ of stores detected without API calls
2. **Speed**: Tier 1 is instant lookup
3. **Accuracy**: Pre-configured templates are verified
4. **User Control**: Expensive agent requires opt-in
5. **Metadata**: Local stock/price info included
6. **Extensible**: Easy to add new known stores

### Negative

1. **Maintenance**: Need to update templates if URLs change
2. **Coverage Gap**: Some stores won't match any tier
3. **Complexity**: More code paths to test

### Neutral

1. **Documentation**: Need to document how to add templates
2. **Testing**: Each tier needs separate tests

## Implementation

### Modified Files

- `app/Services/Crawler/StoreAutoConfigService.php` - Added 4-tier detection
- `app/Jobs/AI/StoreAutoConfigJob.php` - Handle agent flag
- `app/Http/Controllers/SettingController.php` - New agent endpoint
- `routes/web.php` - Agent route
- `resources/js/Components/StorePreferences.tsx` - Agent prompt UI

### New API Endpoints

- `POST /api/stores/{id}/find-search-url` - Standard tiered detection
- `POST /api/stores/{id}/find-search-url-agent` - Force agent detection

### Detection Response Fields

```typescript
interface DetectionResult {
    success: boolean;
    template?: string;
    validated?: boolean;
    tier?: 'known_template' | 'common_pattern' | 'ai_analysis' | 'firecrawl_agent';
    local_stock?: boolean;
    local_price?: boolean;
    agent_available?: boolean;
    agent_cost_estimate?: string;
    error?: string;
}
```

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Tier 1 hit rate | >60% | % of stores found in known templates |
| Tier 2 hit rate | >20% | % of remaining found via patterns |
| Tier 3 hit rate | >10% | % of remaining found via AI |
| Agent usage | <5% | % requiring expensive agent |
| Overall accuracy | >90% | % of templates that work correctly |

## Related ADRs

- ADR 009: AI Background Job System
- ADR 011: Firecrawl Web Crawler Integration
- ADR 012: Store Registry Architecture
- ADR 013: Nearby Store Discovery

## Future Enhancements

1. **Template Health Monitoring**: Track which templates are working
2. **User-Contributed Templates**: Community can submit templates
3. **Auto-Learning**: Detect patterns from successful user entries
4. **International Support**: Add templates for non-US stores
5. **Template Versioning**: Track URL format changes over time
