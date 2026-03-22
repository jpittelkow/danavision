# Shopping List Analysis — Phase 6: Scheduled Crawling

## Overview

Phase 6 is the final phase of the Shopping List Analysis feature. It adds scheduled background crawling so store prices stay fresh without manual user-triggered refreshes.

## Architecture

Two complementary scheduled commands handle price freshness:

### `prices:check` (hourly)
- Finds items not refreshed within the configured interval (default: 24h)
- Uses **all configured providers** via `PriceApiService`: SerpAPI, Kroger API, and CrawlAI
- Calls `PriceTrackingService::refreshItem()` per item
- Sends notifications for price drops and all-time lows

### `prices:crawl-stores` (category-based intervals)
- Dispatches `CrawlStorePriceJob` for each active store with a `search_url_template`
- **CrawlAI only** — scrapes store websites directly via `StoreCrawlService`
- Category-based intervals:
  - Grocery / General: every 6 hours
  - Warehouse: every 8 hours
  - Electronics / Pharmacy / Home-improvement / Delivery: every 12 hours
- Checks `last_crawled_at` to skip stores not yet due (unless `--force`)

### Crawl Pipeline

```
CrawlStorePricesCommand
  → CrawlStorePriceJob (queued, 2 retries, exponential backoff)
    → StoreCrawlService.crawlStore()
      → getProductsToCrawl() — distinct unpurchased items at store
      → scrapeAndExtract() — CSS selectors first, LLM fallback
        → CrawlAIService.scrapeWithCssExtraction() or scrapeUrl()
        → extractPricesFromContent() via LLMOrchestrator
      → updateVendorPriceFromCrawl() — updates ItemVendorPrice
      → 500ms rate limit between requests
      → store.last_crawled_at = now()
```

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `price_search.store_crawl_enabled` | `false` | Master toggle for scheduled crawling |
| `price_search.store_crawl_max_products_per_store` | `50` | Max products to check per store per crawl |
| `price_search.crawl4ai_enabled` | `false` | Enable CrawlAI service |
| `price_search.crawl4ai_base_url` | `http://crawl4ai:11235` | CrawlAI endpoint |
| `price_search.crawl4ai_api_token` | `null` | Optional auth token |
| `price_search.price_check_interval_hours` | `24` | Hours between item refreshes |

## Docker

CrawlAI runs as a separate container behind the `crawl4ai` Docker Compose profile:

```bash
docker compose --profile crawl4ai up -d
```

Available in both `docker-compose.yml` (dev) and `docker-compose.prod.yml` (production).

## Key Files

| Component | Path |
|-----------|------|
| Crawl command | `backend/app/Console/Commands/CrawlStorePricesCommand.php` |
| Price check command | `backend/app/Console/Commands/CheckPricesCommand.php` |
| Crawl job | `backend/app/Jobs/CrawlStorePriceJob.php` |
| Store crawl service | `backend/app/Services/Shopping/StoreCrawlService.php` |
| CrawlAI service | `backend/app/Services/Crawler/CrawlAIService.php` |
| Firecrawl service | `backend/app/Services/Crawler/FirecrawlService.php` |
| Store discovery | `backend/app/Services/Crawler/StoreDiscoveryService.php` |
| Schedule | `backend/routes/console.php` |
| Store model | `backend/app/Models/Store.php` |
| Config UI | `frontend/app/(dashboard)/configuration/price-search/page.tsx` |

## Completed Phases

1. Frontend-Backend Mismatch Fixes
2. Backend Services Completion (PriceSearch, PriceTracking, Store, ListSharing)
3. Store Management & Discovery
4. Notification Integration
5. Dashboard Shopping Widgets
6. **Scheduled Crawling** (this phase)
