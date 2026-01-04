# ADR 012: Store Registry Architecture for Cost-Effective Price Discovery

## Status

Accepted

## Date

2026-01-04

## Context

The existing Firecrawl integration (ADR 011) uses the Agent API for price discovery. While powerful, the Agent API is expensive:

- **Agent API costs**: ~50-100 credits per search (autonomous web browsing)
- **Common searches**: Most users search the same major retailers repeatedly
- **Cost accumulation**: For active users, Agent API costs can become prohibitive

Users typically shop at a predictable set of stores:
- Major retailers: Amazon, Walmart, Target, Best Buy
- Warehouse clubs: Costco, Sam's Club
- Specialty stores: B&H Photo, Newegg, Chewy
- Local grocery: Kroger, Publix, Safeway

For these known stores, using the expensive Agent API is overkill.

## Decision

We implemented a **Store Registry** system with **tiered price discovery** that significantly reduces Firecrawl API costs while maintaining functionality.

### Architecture

```
Product Added
       ↓
  ┌─────────────────────────────────────────┐
  │        Tier 1: Store Templates          │
  │   (1 credit/store via Scrape API)       │
  │                                         │
  │  For each enabled store with template:  │
  │  1. Generate search URL from template   │
  │  2. Scrape results with Firecrawl       │
  │  3. Extract prices with JSON schema     │
  └─────────────────────────────────────────┘
       ↓ (if < 3 results)
  ┌─────────────────────────────────────────┐
  │        Tier 2: Search Discovery         │
  │   (5-10 credits via Search API)         │
  │                                         │
  │  1. Use Firecrawl Search API            │
  │  2. Find new stores/products            │
  │  3. Learn new store URLs for future     │
  └─────────────────────────────────────────┘
       ↓ (if still insufficient, optional)
  ┌─────────────────────────────────────────┐
  │        Tier 3: Agent Fallback           │
  │   (50-100 credits via Agent API)        │
  │                                         │
  │  Only for complex searches:             │
  │  - Local store discovery                │
  │  - Obscure products                     │
  │  - When all else fails                  │
  └─────────────────────────────────────────┘
       ↓
  Results Aggregated & Displayed
```

### Store Registry Components

**1. `stores` Table**
- Pre-populated with major retailers and their URL templates
- Stores learn new stores from search results
- Includes store metadata (category, logo, local flag)

**2. `user_store_preferences` Table**
- Users can enable/disable stores
- Mark favorites (searched first, higher priority)
- Custom priority ordering via drag-and-drop

**3. `StoreDiscoveryService`**
- Orchestrates tiered discovery
- Generates URLs from templates
- Merges results from all tiers
- Learns new stores automatically

### URL Templates

Each store has a `search_url_template` field:

```
Amazon:   https://www.amazon.com/s?k={query}
Walmart:  https://www.walmart.com/search?q={query}
Target:   https://www.target.com/s?searchTerm={query}
Best Buy: https://www.bestbuy.com/site/searchpage.jsp?st={query}
```

The `{query}` placeholder is replaced with the URL-encoded product name.

### Cost Comparison

| Scenario | Old (Agent Only) | New (Tiered) | Savings |
|----------|------------------|--------------|---------|
| Search Amazon+Walmart+Target | ~100 credits | 3 credits | 97% |
| Search with 1 new store | ~100 credits | ~15 credits | 85% |
| Complex local search | ~100 credits | ~50 credits | 50% |
| Daily refresh (10 URLs) | 10 credits | 10 credits | 0% |

**Estimated monthly savings**: 70-90% reduction in Firecrawl costs

### User Experience

1. **Settings > Stores Tab**: Users manage store preferences
   - Enable/disable individual stores
   - Mark favorites with star icon
   - Add custom stores for specialty shops
   - Drag-to-reorder priority (future enhancement)

2. **Price Discovery**: System automatically uses optimal tier
   - Users don't need to understand tiers
   - Favorites are searched first
   - Results show store logos and "Fast" badge for template-based stores

3. **Custom Stores**: Users can add any store
   - Enter name and domain
   - System learns URL pattern from Search API
   - Future searches use learned pattern

## Alternatives Considered

### Keep Agent-Only Approach

- **Pros**: Simple, no new infrastructure
- **Cons**: Expensive, unsustainable for active users
- **Why rejected**: Cost is prohibitive

### Client-Side Scraping (Browser Extension)

- **Pros**: Zero API cost
- **Cons**: Privacy concerns, browser dependency, blocked by anti-bot
- **Why rejected**: Poor UX, unreliable

### Third-Party Price APIs (Keepa, Camel)

- **Pros**: Structured data, historical prices
- **Cons**: Limited to Amazon, expensive subscriptions
- **Why rejected**: Not comprehensive enough

### Static Store Database Only

- **Pros**: Cheapest possible, predictable
- **Cons**: No discovery of new stores, manual maintenance
- **Why rejected**: Too inflexible

## Consequences

### Positive

1. **Cost Reduction**: 70-90% reduction in Firecrawl API costs
2. **Speed**: Template-based searches are faster than Agent
3. **User Control**: Users choose which stores to search
4. **Learning System**: Automatically discovers and remembers new stores
5. **Fallback**: Agent API still available for complex cases

### Negative

1. **Initial Setup**: Requires seeding store database
2. **Template Maintenance**: Templates may break if stores change URLs
3. **Learning Curve**: Users need to understand store preferences

### Neutral

1. **Complexity**: More code, but well-separated concerns
2. **Testing**: More tests needed for tiered logic

## Implementation

### New Files

- `database/migrations/2026_01_04_140000_create_stores_table.php`
- `database/seeders/StoreSeeder.php`
- `app/Models/Store.php`
- `app/Models/UserStorePreference.php`
- `app/Services/Crawler/StoreDiscoveryService.php`
- `resources/js/Components/StorePreferences.tsx`
- `tests/Feature/Crawler/StoreDiscoveryServiceTest.php`
- `tests/Feature/Settings/StorePreferencesTest.php`

### Modified Files

- `app/Services/Crawler/FirecrawlService.php` - Added `searchProducts()`, `scrapeUrlsBatch()`
- `app/Jobs/AI/FirecrawlDiscoveryJob.php` - Uses `StoreDiscoveryService`
- `app/Http/Controllers/SettingController.php` - Store preference endpoints
- `routes/web.php` - New store routes
- `resources/js/Pages/Settings.tsx` - Stores tab
- `resources/js/types/index.ts` - Store types

### API Endpoints

- `GET /api/stores` - List all stores with user preferences
- `PATCH /api/stores/{id}/preference` - Update store preference
- `POST /api/stores/{id}/favorite` - Toggle favorite
- `PATCH /api/stores/priorities` - Bulk update priorities
- `POST /api/stores` - Add custom store
- `POST /api/stores/reset` - Reset to defaults

### Default Stores Seeded

- General: Amazon, Walmart, Target, eBay
- Electronics: Best Buy, Newegg, B&H Photo
- Warehouse: Costco, Sam's Club
- Home: Home Depot, Lowe's, Wayfair
- Grocery: Kroger, Publix, Safeway, Whole Foods
- Pharmacy: CVS, Walgreens
- Specialty: Chewy

## Migration Path

1. Run migration to create tables
2. Run seeder to populate default stores
3. Deploy new code (backward compatible)
4. Monitor cost reduction in Firecrawl dashboard
5. Adjust tier thresholds based on results

## Related ADRs

- ADR 009: AI Background Job System
- ADR 010: SERP API + AI Aggregation Architecture
- ADR 011: Firecrawl Web Crawler Integration

## Future Enhancements

1. **Drag-and-Drop Reordering**: Visual priority management
2. **Store Health Monitoring**: Track which templates are working
3. **Regional Store Support**: Better local store detection
4. **Price History per Store**: Track price trends by retailer
5. **Smart Template Learning**: Auto-discover URL patterns
