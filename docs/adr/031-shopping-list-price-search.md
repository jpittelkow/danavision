# ADR-031: Shopping List & Price Search Architecture

## Status

Accepted

## Date

2026-03-12

## Context

DanaVision's core domain is helping users manage shopping lists, track prices across vendors, and find the best deals. The system needs to support multi-vendor price comparison, unit price normalization, store preference management, list sharing, and AI-powered deal scanning — all scoped per user.

## Decision

Implement a multi-layered, user-scoped shopping architecture with clear separation between core shopping, price discovery, store management, collaborative sharing, and deal tracking.

### Architecture Overview

```
User
├── ShoppingList (many)
│   ├── ListItem (many)
│   │   ├── ItemVendorPrice (many, one per store)
│   │   └── PriceHistory (append-only timeline)
│   └── ListShare (many, email-based invitations)
├── UserStorePreference (many, per-store overrides)
├── DealScan (many, image-based coupon scanning)
│   └── ScannedDeal (many, extracted deals)
└── SearchHistory (many, price search audit trail)
```

### Service Layer

| Service | Layer | Responsibility |
|---------|-------|---------------|
| `ShoppingListService` | Shopping | List CRUD, ownership, shared list retrieval, price drop detection |
| `ListItemService` | Shopping | Item CRUD, purchase tracking, single-source price updates |
| `PriceTrackingService` | Shopping | Price refresh orchestration, notification dispatch, vendor price aggregation |
| `StoreService` | Shopping | Store CRUD, user preferences, nearby discovery, suppression |
| `StoreChainService` | Shopping | Auto-link subsidiaries to parent chains |
| `ListAnalysisService` | Shopping | Per-store cost analysis, split-shopping optimization |
| `StoreCrawlService` | Shopping | Scheduled store crawling orchestration |
| `PriceSearchService` | PriceSearch | Multi-provider search, LLM structuring, vendor filtering |
| `VendorNameResolver` | PriceSearch | Vendor string → Store ID resolution (slug, domain, fuzzy, chain) |
| `UnitPriceNormalizer` | PriceSearch | Standardize prices to base units (lb, gal, ct) for comparison |
| `PriceApiService` | PriceSearch | SerpAPI, CrawlAI, and store-specific API delegation |
| `ListSharingService` | Sharing | Share invitations, accept/decline, permission checking, expiry |
| `DealScannerService` | Deals | Image scanning via vision AI, deal extraction, queue management |
| `DealPricingService` | Deals | Compute effective prices with deal discounts applied |
| `GooglePlacesService` | Location | Nearby store discovery, address autocomplete |

### API Endpoints

**Shopping Lists** (RESTful):
| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/lists` | Index (owned + accepted shared) |
| `POST` | `/api/lists` | Create list |
| `GET` | `/api/lists/{id}` | Show with items + vendor prices |
| `PUT` | `/api/lists/{id}` | Update |
| `DELETE` | `/api/lists/{id}` | Destroy |
| `POST` | `/api/lists/{id}/refresh` | Async price refresh |
| `POST` | `/api/lists/{id}/analyze` | On-demand store comparison |
| `GET` | `/api/lists/{id}/analysis` | Fetch cached analysis |

**Items**: `/api/items` (CRUD + `/refresh`, `/purchased`, `/history`)
**Shares**: `/api/lists/{id}/shares` (CRUD + `/accept`, `/decline`, `/pending`)
**Stores**: `/api/stores` (CRUD + `/favorite`, `/suppress`, `/restore`, `/nearby`, `/priorities`)
**Price Search**: `/api/product-search` (text), `/api/product-search/image` (vision)
**Deals**: `/api/deals` (CRUD + `/scan`, `/queue`, `/accept`, `/dismiss`)

### Key Design Decisions

**1. Single Source of Truth for Prices**
- `PriceTrackingService::refreshItem()` creates one `AIJob` per item
- Upserts `ItemVendorPrice` records for all vendors
- Calls `ListItemService::updatePrice()` once for the best in-stock price → creates single `PriceHistory` entry

**2. Vendor Resolution Strategy** (multi-step matching)
1. Exact slug match → `Store.slug = lowercased vendor name`
2. Domain extraction → parse URL, match against `Store.domain`
3. Fuzzy name match → exact case-insensitive, prefix, contains
4. Chain matching → `StoreChainService` (e.g., "Walmart Neighborhood Market" → Walmart parent)
5. All lookups cached (1-hour TTL)

**3. Unit Price Normalization**
- Regex extraction first (fast, no API cost) for patterns like "2 lb", "64 fl oz", "12 pack"
- LLM fallback for complex product names (optional, on demand)
- Conversion table: normalize all to base units (lb, gal, ct)
- Stored as `DECIMAL(10,4)` for precision

**4. User Preference Layering**
- `UserStorePreference` table: per-user enable/disable, priority order, favorite flag
- `Setting` table (shopping group): `suppressed_vendors` array for search filtering
- `StoreService::getActiveStores()` merges global defaults + user overrides

**5. List Sharing** (email-based, permission levels)
- Permissions: `view` < `edit` < `admin` (checked via `ListShare::hasPermission()`)
- Expiry support: `ListShare.expires_at` with auto-cleanup query filters
- Status computed: pending → accepted → declined or expired

**6. Deal Deduplication** (content-hash based)
- `SHA256(product_name | store_name | discount_type | discount_value | sale_price | valid_from | valid_to)`
- Skip if hash exists and status in [pending, active]

**7. Store Chain Hierarchy** (self-referential)
- `Store.parent_store_id` (nullable) for subsidiaries
- `StoreChainService::autoLinkSubsidiary()` auto-links on nearby store discovery
- Enables rollup queries across all subsidiary prices

**8. Price Notifications** (opt-in rules)
- `notify_on_any_drop` (all drops) or `notify_on_threshold` + threshold percentage
- All-time low: `current_price < lowest_price` (separate notification)
- Dispatched via `NotificationOrchestrator` (all channels)

## Consequences

### Positive

- Clean separation between shopping domain, price discovery, and store management
- Multi-provider price search with LLM aggregation for accuracy
- User preference layering avoids one-size-fits-all vendor results
- Unit price normalization enables apples-to-apples comparison across package sizes
- Content-hash deduplication prevents duplicate deals from repeated scans

### Negative

- Vendor name resolution is heuristic-based — edge cases may require manual store mapping
- LLM fallback for unit price extraction adds latency and API cost
- Multiple async jobs per price refresh can be expensive at scale

### Neutral

- Field name bridging via Laravel `$appends` accessors (DB columns differ from API response keys)
- Analysis results cached on list model to avoid repeated computation

## Related Decisions

- [ADR-006](./006-llm-orchestration-modes.md) — LLM used for price aggregation and unit price extraction
- [ADR-014](./014-database-settings-env-fallback.md) — shopping settings stored via SettingService
- [ADR-028](./028-webhook-system.md) — price drop events can trigger webhooks

## Notes

- Key files: `backend/app/Services/Shopping/`, `backend/app/Services/PriceSearch/`, `backend/app/Services/Deals/`, `backend/app/Services/Sharing/`
- Models: `ShoppingList`, `ListItem`, `ItemVendorPrice`, `PriceHistory`, `Store`, `ListShare`, `DealScan`, `ScannedDeal`
- Controllers: `ShoppingListController`, `ListItemController`, `StoreController`, `ProductSearchController`, `DealScanController`, `ListShareController`
- Frontend pages: `/lists`, `/items`, `/search`, `/deals`, `/smart-add`, `/configuration/stores`, `/configuration/price-search`
- Journal: [2026-03-12 v1-v2 Feature Parity](../journal/2026-03-12-v1-v2-feature-parity.md)
