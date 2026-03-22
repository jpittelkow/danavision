# V1 → V2 Shopping Feature Parity - March 12, 2026

## Overview

Brought DanaVision v2's shopping features to full parity with v1. V2 had frontend pages ~100% complete but backend services were 20-60% complete with numerous mismatches and missing integrations.

## Implementation Approach

8-phase plan executed in a single pass:
1. Fix frontend-backend mismatches (validation, field names, response keys)
2. Complete backend services (PriceSearchService, PriceTrackingService, SmartAddService, ListSharingService)
3. Store management & discovery (CRUD, nearby stores, vendor suppression, location settings)
4. Notification integration (price drop, all-time low, share invitation notifications)
5. Dashboard shopping widgets (stat cards, recent drops, 7-day chart, store leaderboard)
6. AI enhancements (custom prompts, smart fill)
7. Web crawling (deferred to roadmap — Firecrawl vs CrawlAI evaluation needed)
8. Missing pages & polish (all items page, image search on search page, price check scheduling)

## Key Decisions

- **AI Providers**: System-level only (v2 model). No per-user provider config.
- **Firecrawl/Crawling**: Deferred. SerpAPI is the primary price search provider.
- **Field name bridging**: Used Laravel `$appends` accessors on ListItem model rather than renaming DB columns or changing all frontend code.
- **Price history**: Single source of truth via `ListItemService::updatePrice()` — removed duplicate creation from `PriceTrackingService::updateVendorPrices()`.

## Changes Made

### Backend Controllers
- **ListItemController**: Added `index()` for all-items endpoint with comprehensive filtering/sorting. Fixed 4 response key bugs. Added smart fill endpoint.
- **ListShareController**: Changed from `user_id` to email-based sharing. Fixed 3 response key bugs.
- **ProductSearchController**: Added `history()` method and search history route.
- **StoreController**: Rewritten with 17 endpoints (CRUD, nearby discovery, suppress/restore, priorities, parent/subsidiary linking).
- **DashboardController**: Added `shoppingStats()` with 11 aggregate metrics.
- **AIPromptController**: New controller for user custom AI prompt management.

### Backend Services
- **PriceSearchService**: Multi-provider search with LLM aggregation, vendor suppression filtering, shop_local support. Added `parseLlmResponse()` with validation and `sortByRelevanceAndPrice()` helpers.
- **PriceTrackingService**: Full refresh pipeline (AIJob → PriceSearchService → vendor prices → item update → notifications). Fixed race condition with `firstOrNew` pattern, N+1 with eager loading.
- **StoreService**: Full CRUD, nearby discovery via GooglePlacesService, suppress/restore, priorities.
- **ListSharingService**: Email-based invitation flow with notification integration.

### Backend Models
- **ListItem**: Added `$appends` for `retailer`, `image_url`, `url` accessors bridging to `current_retailer`, `product_image_url`, `product_url`.
- Fixed priority migration from `integer` to `string('priority')->default('medium')`.

### Backend Notifications
- Created `PriceDropNotification`, `AllTimeLowNotification`, `ListShareNotification`, `SmartAddCompleteNotification` with proper `TYPE`, `CHANNELS`, `send()`, `toArray()` pattern.
- Seeded 16 notification templates (4 types × 4 channel groups).

### Frontend Pages
- **All Items page** (`items/page.tsx`): Grid view with filters (list, status, price status), sortable columns, pagination.
- **Search page**: Added image search mode with drag-and-drop upload, text/image tab toggle.
- **Stores config page** (`configuration/stores/page.tsx`): Tabbed interface for all/favorites/suppressed stores.
- **Dashboard**: Added Shopping section with stat cards (price drops, savings, all-time lows, below target), recent drops list, 7-day activity area chart, store leaderboard bar chart.

### Scheduled Tasks
- **CheckPricesCommand**: `prices:check` Artisan command for scheduled price refreshes.
- Registered hourly in `console.php` with `withoutOverlapping(30)`.

## Bugs Found & Fixed

14+ bugs discovered and resolved. Key ones:
- 7 critical response key mismatches (`['item' =>]`/`['share' =>]` vs expected `['data' =>]`)
- Missing `/search-history` route
- Frontend/backend field name mismatches (solved with `$appends`)
- Priority field type mismatch (integer vs string enum)
- Duplicate PriceHistory records from two creation paths
- Race condition on vendor price upsert (concurrent refreshes)
- N+1 queries in list refresh
- LLM response parsing lacking array-of-arrays validation

All logged in `docs/plans/bug-tracker.md`.

## Related Files

- Plan: `.claude/plans/bright-chasing-puppy.md`
- Bug tracker: `docs/plans/bug-tracker.md`
- Roadmap updates: `docs/roadmaps.md` (crawling deferred items)
