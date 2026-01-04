# ADR 013: Nearby Store Discovery

## Status

Accepted

## Context

DanaVision users need an easy way to add local stores to their Store Registry for price tracking. Manually adding stores is time-consuming, and users may not know all the stores in their area. We needed a feature to automatically discover nearby stores and configure them for price searches.

## Decision

We implement a **Nearby Store Discovery** feature that:

1. Uses **Google Places API** to find stores within a user-specified radius
2. Automatically extracts website URLs from place data
3. Uses **Firecrawl** with AI to detect search URL templates for auto-configuration
4. Creates Store records with automatic configuration for price discovery

### Architecture

```
User → NearbyStoreDiscovery UI
         ↓
    NearbyStoreController
         ↓
    NearbyStoreDiscoveryJob (Background)
         ↓
    ┌────┴────┐
    ↓         ↓
GooglePlaces  StoreAutoConfigService
Service       (Firecrawl + AI)
    ↓         ↓
    └────┬────┘
         ↓
    Store Registry (DB)
```

### Components

1. **GooglePlacesService** (`app/Services/Search/GooglePlacesService.php`)
   - Searches for nearby stores using Google Places API (New)
   - Maps store categories to Google Places types
   - Calculates distances using Haversine formula
   - Caches results to reduce API costs

2. **StoreAutoConfigService** (`app/Services/Crawler/StoreAutoConfigService.php`)
   - Detects search URL templates from store websites
   - Uses Firecrawl to crawl and analyze page structure
   - Uses AI to identify search patterns
   - Falls back to common URL patterns if AI fails

3. **NearbyStoreDiscoveryJob** (`app/Jobs/AI/NearbyStoreDiscoveryJob.php`)
   - Orchestrates the discovery process as a background job
   - Creates Store records with Google Place IDs for deduplication
   - Creates UserStorePreference records (enabled, favorited by default)
   - Reports progress through the existing AI Job system

4. **NearbyStoreController** (`app/Http/Controllers/NearbyStoreController.php`)
   - API endpoints for discovery, preview, and status polling
   - Validates availability of required API keys

5. **NearbyStoreDiscovery.tsx** (`resources/js/Components/NearbyStoreDiscovery.tsx`)
   - Dialog UI with radius selection (1-50 miles)
   - Category checkboxes (grocery, electronics, pet, pharmacy, etc.)
   - Preview functionality before committing
   - Progress tracking with log display

### Database Changes

Added to `stores` table:
- `google_place_id` - For deduplication
- `latitude`, `longitude` - For distance calculations
- `address`, `phone` - From Google Places data
- `auto_configured` - Track AI-configured stores

Added new store category:
- `pet` - For pet stores

### API Endpoints

- `GET /api/stores/nearby/availability` - Check if feature is available
- `GET /api/stores/nearby/categories` - Get available store categories
- `POST /api/stores/nearby/preview` - Preview stores without adding
- `POST /api/stores/nearby/discover` - Start discovery job
- `GET /api/stores/nearby/{jobId}` - Poll discovery status
- `POST /api/stores/nearby/{jobId}/cancel` - Cancel discovery

### Store Categories Mapping

| DanaVision Category | Google Places Types |
|---------------------|---------------------|
| grocery | supermarket, grocery_or_supermarket |
| electronics | electronics_store |
| pet | pet_store |
| pharmacy | pharmacy, drugstore |
| home | home_goods_store, hardware_store |
| clothing | clothing_store, shoe_store |
| warehouse | department_store (with name filtering) |

## Consequences

### Positive

1. **User Experience**: One-click discovery of nearby stores reduces manual configuration
2. **Accuracy**: Google Places provides verified business data
3. **Auto-Configuration**: Firecrawl/AI reduces manual URL template setup
4. **Cost Efficiency**: Tiered approach - preview before committing API credits
5. **Deduplication**: Google Place IDs prevent duplicate stores

### Negative

1. **API Costs**: Requires Google Places API key (has free tier)
2. **External Dependency**: Relies on Google Places API availability
3. **Auto-Config Limitations**: AI may not detect all search patterns
4. **Rate Limits**: Google Places API has quotas

### Risks Mitigated

1. **Cost Control**: Preview shows estimated Firecrawl credits before discovery
2. **Deduplication**: Checks both `google_place_id` and domain before creating stores
3. **Fallback**: Manual configuration still available if auto-config fails
4. **Caching**: Results cached to reduce API calls

## Configuration Required

Users need to configure in Settings > Config:
1. **Google Places API Key** - From Google Cloud Console
2. **Home Address** - For default location (Settings > General)
3. **Firecrawl API Key** - For auto-configuration (optional but recommended)

## Related ADRs

- ADR 012: Store Registry Architecture
- ADR 011: Firecrawl Price Discovery

## References

- [Google Places API (New) Documentation](https://developers.google.com/maps/documentation/places/web-service/op-overview)
- [Firecrawl Documentation](https://docs.firecrawl.dev/)
