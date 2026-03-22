# 2026-03-21: Phase 6 — Scheduled Crawling

## What

Completed Phase 6 (final phase) of the Shopping List Analysis feature — scheduled background crawling for automatic price freshness.

## Context

Phases 1–5 built the full shopping list analysis stack: price search (SerpAPI, Kroger, CrawlAI), store management, sharing, notifications, and dashboard widgets. Phase 6 wires up the scheduled crawling infrastructure so prices stay fresh without user action.

## What Was Done

### Backend Core (already existed from prior work)
- `CrawlStorePricesCommand` — dispatches crawl jobs per store with category intervals
- `CrawlStorePriceJob` — queued job with retry/backoff
- `StoreCrawlService` — orchestrates scraping with CSS-first / LLM fallback
- `CrawlAIService` — HTTP client for Crawl4AI
- Schedule entries in `console.php`
- Migrations: `last_crawled_at`, `scrape_instructions` seeding

### Bugs Found and Fixed
- **SettingService call signature**: `CrawlStorePricesCommand` and `StoreCrawlService` used dot-notation `get('price_search.store_crawl_enabled')` instead of the correct 3-arg `get('price_search', 'store_crawl_enabled')`. Fixed both.
- **LLMOrchestrator nullable user**: `StoreCrawlService` passes `user: null` for system-level crawling, but `LLMOrchestrator::query()` required non-nullable `User`. Made `$user` nullable across `query()`, `singleQuery()`, `aggregationQuery()`, `councilQuery()`, `getEnabledProviders()`, and `logRequest()`. System-level queries now fall back to any enabled provider.

### New Work
- **Frontend**: Added "Scheduled Crawling" card to Price Search config Behavior tab (`store_crawl_enabled` toggle, `store_crawl_max_products_per_store` input)
- **Tooltips**: Added tooltip content for both new settings
- **Docker prod**: Added `crawl4ai` service to `docker-compose.prod.yml` behind `crawl4ai` profile
- **Env vars**: Added CRAWL4AI and STORE_CRAWL vars to `backend/.env.example`
- **Jobs whitelist**: Registered `prices:crawl-stores` and `prices:check` in `ScheduledTaskService` for admin manual triggering
- **Help content**: Added "Price Search & Crawling" article to help center
- **Tests**: 25 tests across 4 files (CrawlAIServiceTest, CrawlStorePriceJobTest, StoreCrawlServiceTest, CrawlStorePricesCommandTest)
- **Documentation**: Plan doc (`docs/plans/generic-crafting-squid.md`), this journal entry

## Lessons Learned

- Seeded data from migrations can pollute tests. Use `Model::query()->delete()` in `beforeEach` when exact counts matter.
- System-level service calls (no user context) are a recurring pattern that needs explicit support — type-hinting `User` as non-nullable blocks background job usage.

## Related

- [Shopping List Analysis Plan](../plans/generic-crafting-squid.md)
- [V1-V2 Feature Parity Journal](2026-03-12-v1-v2-feature-parity.md)
