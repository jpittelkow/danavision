# ADR 011: Firecrawl Web Crawler Integration for Price Discovery

## Status

Accepted

## Date

2026-01-04

## Context

DanaVision relies on accurate, real-time pricing data. The existing SERP API integration (ADR 010) works well but has limitations:

1. **Coverage**: SERP API primarily indexes major retailers, missing local stores and specialty shops
2. **Depth**: Shopping results provide summary data; detailed product pages have more information
3. **Cost**: SERP API charges per request, which can become expensive for frequent price checks
4. **Real-time Updates**: No mechanism to refresh prices from known product URLs

Users requested:
- Better support for local shopping
- More comprehensive price discovery
- Direct links to product pages
- Scheduled price refresh from known sources

## Decision

We integrated **Firecrawl.dev** as an intelligent web crawler for price discovery and updates, complementing the existing SERP API.

### Architecture

```
Product Added
       ↓
Firecrawl Agent API (Discovery)
       ↓ (finds all stores with product)
Save to item_vendor_prices
       ↓
AI Analysis (optional)
       ↓
Display to User

Daily Schedule
       ↓
Firecrawl Scrape API (Refresh)
       ↓ (updates prices from known URLs)
Update item_vendor_prices
       ↓
Notify on price drops
```

### Firecrawl API Usage

We use two Firecrawl endpoints:

1. **Extract API** (`/v1/extract`): Intelligent discovery with web search
   - Used for initial product price discovery
   - Used for weekly new vendor discovery
   - Takes natural language prompt and JSON schema
   - Enabled with `enableWebSearch: true` to find products across the web
   - Returns structured price data from multiple sites
   - More widely available than the newer Agent API

2. **Scrape API** (`/v1/scrape`): Targeted extraction
   - Used for daily price refreshes
   - Scrapes known product URLs
   - More cost-effective than re-discovery
   - Returns updated prices with stock status

### Firecrawl vs SERP API

| Feature | SERP API | Firecrawl |
|---------|----------|-----------|
| Coverage | Major retailers | Any website |
| Real-time | Cached results | Live scraping |
| Local stores | Limited | Excellent |
| Cost model | Per search | Per page/credits |
| URL scraping | No | Yes |
| Scheduling | Manual | Built-in support |

### Priority Logic

When a product is added:
1. **If Firecrawl API configured**: Use Firecrawl for discovery
2. **Otherwise**: Fall back to SERP API

Both can coexist; Firecrawl is preferred when available.

### Job System Integration

Firecrawl jobs integrate with the existing AIJob system:

- `TYPE_FIRECRAWL_DISCOVERY`: Initial/weekly price discovery
- `TYPE_FIRECRAWL_REFRESH`: Daily URL-based price updates

Jobs show in the user's "Jobs" tab with real-time status.

### Schedule

| Task | Frequency | Method |
|------|-----------|--------|
| Initial Discovery | On product add | Agent API |
| Daily Refresh | User-configured time | Scrape API |
| Weekly Discovery | Sundays 4:00 AM | Agent API |

### Data Model

New fields added to `item_vendor_prices`:
- `last_firecrawl_at`: When Firecrawl last updated this entry
- `firecrawl_source`: Source type (initial_discovery, daily_refresh, weekly_discovery)

### UX Enhancements

1. **Real-time Status**: PriceUpdateStatus component shows:
   - Spinner when price job is queued/running
   - Checkmark with timestamp when prices are current
   - Error state with retry option on failure

2. **Direct Product Links**: Product images and names link to retailer pages

3. **Job Visibility**: All Firecrawl jobs appear in the Jobs section

## Alternatives Considered

### Puppeteer/Playwright Self-Hosted

- **Pros**: Full control, no API costs
- **Cons**: Complex infrastructure, anti-bot challenges, maintenance
- **Why rejected**: Firecrawl handles complexity; we focus on business logic

### Multiple Price APIs (Keepa, CamelCamelCamel)

- **Pros**: Structured data, historical prices
- **Cons**: Amazon-only, limited coverage, expensive
- **Why rejected**: Not comprehensive enough

### Browser Extension Scraping

- **Pros**: User context, no server costs
- **Cons**: Requires extension, user participation
- **Why rejected**: Poor UX, not automated

## Consequences

### Positive

1. **Comprehensive Coverage**: Can discover prices from any website
2. **Local Shopping**: Better support for local store searches
3. **Fresh Data**: Daily refreshes from known URLs
4. **Direct Links**: Users can click through to actual product pages
5. **Structured Data**: JSON schema ensures consistent output
6. **Fallback**: SERP API remains available if Firecrawl unavailable

### Negative

1. **Cost**: Firecrawl API has usage-based pricing
2. **Rate Limits**: May need throttling for large catalogs
3. **Reliability**: Depends on target site structure
4. **Latency**: Agent API can be slow (10-60 seconds)

## Implementation

### New Files

- `app/Services/Crawler/FirecrawlService.php`: API client
- `app/Services/Crawler/FirecrawlResult.php`: Result value object
- `app/Jobs/AI/FirecrawlDiscoveryJob.php`: Discovery job
- `app/Jobs/AI/FirecrawlRefreshJob.php`: Refresh job
- `resources/js/Components/PriceUpdateStatus.tsx`: Status indicator

### Modified Files

- `app/Models/Setting.php`: Added FIRECRAWL_API_KEY constant
- `app/Models/AIJob.php`: Added Firecrawl job types
- `app/Http/Controllers/SmartAddController.php`: Firecrawl-first dispatch
- `app/Http/Controllers/SettingController.php`: Firecrawl key handling
- `routes/console.php`: Firecrawl schedules
- `resources/js/Pages/Settings.tsx`: Firecrawl configuration UI

### Configuration

Users configure Firecrawl in Settings:
1. Navigate to Settings → Configurations
2. Enter Firecrawl API key from firecrawl.dev
3. Save settings

Firecrawl free tier includes 500 credits, suitable for testing.

## Related ADRs

- ADR 009: AI Background Job System
- ADR 010: SERP API + AI Aggregation Architecture

## Future Enhancements

1. **Batch Operations**: Combine multiple URLs per scrape request
2. **Caching**: Cache recent scrape results to reduce costs
3. **Fallback Chain**: Firecrawl → SERP → Cached data
4. **Price Alerts**: More sophisticated price drop notifications
