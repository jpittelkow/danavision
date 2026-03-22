# ADR-033: Web Crawling System

## Status

Accepted

## Date

2026-03-21

## Context

To keep vendor prices fresh without relying solely on user-initiated searches, DanaVision needs scheduled background crawling of store websites. The system must handle diverse store DOM structures, gracefully degrade when CSS selectors break, and respect rate limits.

## Decision

Implement a two-tiered scheduled pricing system: hourly API-based price checks for user items, and category-based store crawling via Crawl4AI with CSS-first extraction and LLM fallback.

### Architecture Overview

```
Tier 1: prices:check (hourly)
  → PriceTrackingService::refreshItem()
    → PriceSearchService (SerpAPI, Kroger, Walmart, Best Buy APIs)
    → Updates ItemVendorPrice + PriceHistory

Tier 2: prices:crawl-stores (category-based intervals)
  → CrawlStorePricesCommand (filters stores by category + last_crawled_at)
    → CrawlStorePriceJob (queued, per store)
      → StoreCrawlService::crawlStore()
        → CrawlAIService::scrapeWithCssExtraction() [CSS path]
        → CrawlAIService::scrapeUrl() [fallback path]
        → LLMOrchestrator::query(user: null) [extract structured data]
        → Updates ItemVendorPrice
```

### Crawl Schedule

| Category | Interval | Schedule |
|----------|----------|---------|
| Grocery | Every 6 hours | `0 */6 * * *` |
| General | Every 6 hours | `0 */6 * * *` |
| Electronics | Twice daily | 3:15 and 15:15 |
| Home Improvement | Twice daily | 4:16 and 16:16 |
| Warehouse | Twice daily | 5:17 and 17:17 |
| Pharmacy | Twice daily | 6:18 and 18:18 |
| Delivery | Twice daily | 7:19 and 19:19 |

All schedules use `withoutOverlapping(60)` to prevent parallel runs.

### Core Services

| Service | Responsibility |
|---------|---------------|
| `StoreCrawlService` | Crawl orchestration — gets products to crawl, calls CrawlAI, extracts prices via LLM, updates vendor prices |
| `CrawlAIService` | HTTP client for self-hosted Crawl4AI — basic scrape, CSS extraction, LLM extraction, health check |
| `FirecrawlService` | HTTP client for cloud Firecrawl API — URL scraping, product search with domain restriction |
| `StoreDiscoveryService` | Three-tier price discovery: store URL templates → Firecrawl search → agent-based LLM search |

### CSS Extraction vs LLM Fallback

**Primary path** (if `scrape_instructions` present on store):
1. Call `CrawlAIService::scrapeWithCssExtraction()` with CSS selectors
2. If content returned → parse with `LLMOrchestrator::query()` for structured extraction
3. If empty → fall through to basic scrape

**Fallback path**:
1. Call `CrawlAIService::scrapeUrl()` (basic markdown scrape)
2. Send content (truncated to 8000 chars) to `LLMOrchestrator::query(user: null)` with extraction prompt
3. LLM extracts: `product_name, price, in_stock, image_url, url, package_size` (JSON array)

**Key tradeoff**: CSS extraction is faster and LLM-free but fragile to DOM changes. Automatic fallback to LLM ensures robustness when selectors become stale.

### Store Scrape Instructions

Stored as JSON on `Store.scrape_instructions`:

```json
{
  "container_selector": "[data-item-id]",
  "name_selector": ".product-title",
  "price_selector": ".product-price",
  "image_selector": "img.product-image",
  "link_selector": "a.product-link",
  "in_stock_indicator": "[data-add-to-cart]",
  "package_size_selector": ".size",
  "wait_for": "[data-item-id]",
  "js_only": true
}
```

Pre-configured via migration for: Walmart, Kroger, Target.

### Job Queue Pattern

`CrawlStorePriceJob`:
- Tries: 2, timeout: 120s
- Exponential backoff: [10s, 60s]
- Soft failure: if CrawlAI unavailable → `release(60)` for retry
- Hard failure: only after max retries exhausted
- Rate limiting: 500ms sleep between requests per store
- Configurable max products per store (default: 50)

### System-Level LLM Queries

Background crawling jobs have no user context. `LLMOrchestrator::query()` was modified to accept nullable `$user` parameter — system-level queries fall back to any enabled LLM provider without user preferences.

### Settings

In `settings-schema.php` under `price_search`:

| Setting | Default | Purpose |
|---------|---------|---------|
| `store_crawl_enabled` | `false` | Master toggle for scheduled crawling |
| `store_crawl_max_products_per_store` | `50` | Limit products crawled per store per run |
| `crawl4ai_enabled` | `false` | Enable Crawl4AI integration |
| `crawl4ai_base_url` | `http://crawl4ai:11235` | Self-hosted Crawl4AI URL |
| `crawl4ai_api_token` | `null` | API token (encrypted) |
| `price_check_interval_hours` | `24` | Hours between API-based price checks |
| `firecrawl_key` | `null` | Firecrawl API key (encrypted) |

### Freshness Tracking

- `Store.last_crawled_at` — updated after successful crawl
- `ItemVendorPrice.last_checked_at` — updated per vendor price
- `CrawlStorePricesCommand` skips stores where `last_crawled_at` is within the category interval (unless `--force`)

## Consequences

### Positive

- Prices stay fresh without user action
- CSS-first extraction avoids LLM cost for well-structured stores
- Automatic LLM fallback provides graceful degradation when selectors break
- Category-based intervals match real-world price change frequency (grocery changes faster than electronics)
- SSRF validation on CrawlAI base URL via `UrlValidationService`

### Negative

- CSS selectors require maintenance when store DOMs change
- LLM fallback adds latency and cost compared to pure CSS extraction
- 500ms inter-request delay limits throughput (intentional for rate limiting)
- Nullable user in LLMOrchestrator is a leaky abstraction for background jobs

### Neutral

- Crawl4AI runs as a separate Docker service (self-hosted)
- Firecrawl is cloud-based (pay-per-use) and used for discovery, not scheduled crawling
- Migration-seeded stores (Walmart, Kroger, Target) include pre-configured scrape instructions

## Related Decisions

- [ADR-031](./031-shopping-list-price-search.md) — crawled prices feed into ItemVendorPrice and PriceHistory
- [ADR-006](./006-llm-orchestration-modes.md) — LLMOrchestrator used for content extraction (nullable user)
- [ADR-014](./014-database-settings-env-fallback.md) — crawl settings stored via SettingService
- [ADR-024](./024-security-hardening.md) — SSRF protection on CrawlAI URLs

## Notes

- Key files: `backend/app/Services/Shopping/StoreCrawlService.php`, `backend/app/Services/Crawler/CrawlAIService.php`, `backend/app/Services/Crawler/FirecrawlService.php`
- Commands: `backend/app/Console/Commands/CrawlStorePricesCommand.php`, `CheckPricesCommand.php`
- Job: `backend/app/Jobs/CrawlStorePriceJob.php`
- Schedule: `backend/routes/console.php`
- Config UI: `frontend/app/(dashboard)/configuration/price-search/page.tsx`
- Journal: [2026-03-21 Phase 6 Scheduled Crawling](../journal/2026-03-21-phase-6-scheduled-crawling.md)
