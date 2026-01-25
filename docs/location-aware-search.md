# Location-Aware Search Documentation

DanaVision supports location-aware price discovery for stores that show local stock availability and/or location-specific pricing.

## Overview

Many retailers vary their information by location:

- **Local Stock**: Show whether an item is in stock at nearby stores
- **Local Pricing**: Prices differ by store location (common in grocery)
- **Store-Specific Results**: Search results filtered to a particular store

## How It Works

### Location Context

When searching for prices, DanaVision builds a location context from:

1. **User's Address** (from Settings > General)
   - ZIP code
   - Latitude/Longitude

2. **Store-Specific Location ID** (from Settings > Stores)
   - Store number
   - Location code

### URL Generation

The location context is substituted into URL templates:

```
Template: https://www.kroger.com/search?query={query}&locationId={store_id}
Context:  { query: "milk", store_id: "00123" }
Result:   https://www.kroger.com/search?query=milk&locationId=00123
```

### Placeholder Reference

| Placeholder | Source | Description |
|-------------|--------|-------------|
| `{query}` | Search input | Product search query |
| `{store_id}` | User Store Preferences | Store-specific location ID |
| `{zip}` | User Settings | ZIP code from address |
| `{lat}` | User Settings | Latitude from address |
| `{lng}` | User Settings | Longitude from address |

## Stores with Local Stock/Pricing

### Grocery Stores (Local Stock + Local Price)

These stores show both local availability AND location-specific pricing:

| Store | Local Stock | Local Price | Location Type |
|-------|-------------|-------------|---------------|
| Kroger | ✅ | ✅ | Store ID |
| Albertsons | ✅ | ✅ | Store ID |
| Safeway | ✅ | ✅ | Store ID |
| Publix | ✅ | ✅ | Store ID |
| H-E-B | ✅ | ✅ | Store ID |
| Meijer | ✅ | ✅ | Store ID |
| Sprouts | ✅ | ✅ | Store ID |
| Food Lion | ✅ | ✅ | Store ID |
| Giant Food | ✅ | ✅ | Store ID |
| Stop & Shop | ✅ | ✅ | Store ID |
| Wegmans | ✅ | ✅ | Store ID |
| Whole Foods | ✅ | ✅ | Prime ZIP |

### Warehouse Clubs (Local Stock + Local Price)

| Store | Local Stock | Local Price | Location Type |
|-------|-------------|-------------|---------------|
| Costco | ✅ | ✅ | Warehouse ID |
| Sam's Club | ✅ | ✅ | Club ID |
| BJ's | ✅ | ✅ | Club ID |

### Home Improvement (Local Stock + Local Price)

| Store | Local Stock | Local Price | Location Type |
|-------|-------------|-------------|---------------|
| Home Depot | ✅ | ✅ | Store # |
| Lowe's | ✅ | ✅ | Store # |
| Menards | ✅ | ✅ | Store # |
| Ace Hardware | ✅ | ✅ | Store # (franchise) |

### General Retailers (Local Stock, National Price)

These stores show local availability but use national pricing:

| Store | Local Stock | Local Price | Notes |
|-------|-------------|-------------|-------|
| Walmart | ✅ | ❌ | National pricing |
| Target | ✅ | ❌ | National pricing |
| Best Buy | ✅ | ❌ | National pricing |
| Micro Center | ✅ | ✅ | Store-specific deals |
| GameStop | ✅ | ❌ | National pricing |
| Kohl's | ✅ | ❌ | National pricing |
| JCPenney | ✅ | ❌ | National pricing |

### Discount/Off-Price (Varies by Store)

| Store | Local Stock | Local Price | Notes |
|-------|-------------|-------------|-------|
| TJ Maxx | ✅ | ✅ | Inventory highly variable |
| Ross | ✅ | ✅ | Inventory highly variable |

### Pharmacy (Local Stock, National Price)

| Store | Local Stock | Local Price | Notes |
|-------|-------------|-------------|-------|
| CVS | ✅ | ❌ | National pricing |
| Walgreens | ✅ | ❌ | National pricing |
| Rite Aid | ✅ | ❌ | National pricing |

### Auto Parts (Local Stock, Varies)

| Store | Local Stock | Local Price | Notes |
|-------|-------------|-------------|-------|
| AutoZone | ✅ | ❌ | National pricing |
| O'Reilly | ✅ | ❌ | National pricing |
| Advance Auto | ✅ | ❌ | National pricing |
| NAPA | ✅ | ✅ | Franchise pricing |

### Online-Only (No Local)

| Store | Notes |
|-------|-------|
| Amazon | Online only, delivery based |
| Newegg | Online only |
| B&H Photo | Online only (NYC store) |
| Chewy | Online only |
| Wayfair | Online only |

## Configuring Location ID

### Finding Your Store's Location ID

1. **Visit the store's website**
2. **Set your location** (usually via ZIP code or "Find Store")
3. **Note the store number** from:
   - URL parameters (e.g., `storeId=00123`)
   - Store details page
   - Receipt from previous purchase

### Setting Location ID in DanaVision

1. Go to **Settings > Stores**
2. Click **Edit** on the store
3. Enter **Location/Store ID**
4. Click **Save Changes**

### Example: Kroger Store ID

1. Go to kroger.com
2. Click "Change Store" (top right)
3. Select your preferred store
4. Note the store number (e.g., "Store #123")
5. Enter "123" as the Location ID in DanaVision

## Parent/Subsidiary Stores

Many grocery chains are owned by larger companies:

### Kroger Family

All use Kroger's search with store-specific location:

- Kroger
- Metro Market
- Pick 'n Save
- Mariano's
- Fred Meyer
- Ralphs
- King Soopers
- Fry's
- Smith's
- QFC
- Harris Teeter
- And more...

### Albertsons Family

All use Albertsons' search:

- Albertsons
- Safeway
- Vons
- Jewel-Osco
- ACME
- Shaw's
- Star Market

### Ahold Delhaize Family

- Stop & Shop
- Giant
- Food Lion
- Hannaford

## Technical Implementation

### Store Model

```php
// app/Models/Store.php

public function generateSearchUrl(string $query, array $context = []): ?string
{
    $template = $this->search_url_template ?? $this->parent?->search_url_template;
    
    if (empty($template)) {
        return null;
    }

    $encodedQuery = urlencode($query);
    $replacements = [
        '{query}' => $encodedQuery,
        '{zip}' => $context['zip'] ?? '',
        '{store_id}' => $context['store_id'] ?? '',
        '{lat}' => $context['lat'] ?? '',
        '{lng}' => $context['lng'] ?? '',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}
```

### Context Building

```php
// app/Services/Crawler/StoreDiscoveryService.php

protected function getUserLocationContext(): array
{
    $context = [];
    
    // Get ZIP from user settings
    $zipSetting = Setting::where('user_id', $this->userId)
        ->where('key', Setting::ZIP)
        ->first();
    if ($zipSetting) {
        $context['zip'] = $zipSetting->value;
    }
    
    // Get coordinates from user settings
    $latSetting = Setting::where('user_id', $this->userId)
        ->where('key', Setting::LATITUDE)
        ->first();
    $lngSetting = Setting::where('user_id', $this->userId)
        ->where('key', Setting::LONGITUDE)
        ->first();
    
    if ($latSetting) {
        $context['lat'] = $latSetting->value;
    }
    if ($lngSetting) {
        $context['lng'] = $lngSetting->value;
    }
    
    return $context;
}
```

### Price Discovery Flow

```
1. User searches for "milk"
2. System loads user's location context (zip: 53202)
3. System loads user's store preferences (Kroger location_id: 00123)
4. For Kroger:
   - Template: https://www.kroger.com/search?query={query}&locationId={store_id}
   - Context: { query: "milk", store_id: "00123", zip: "53202" }
   - URL: https://www.kroger.com/search?query=milk&locationId=00123
5. Crawl4AI scrapes the URL, AI extracts price data
6. Results show prices at user's preferred Kroger location
```

## User Experience

### Benefits of Location Configuration

1. **Accurate Stock**: See if item is available at YOUR store
2. **Local Prices**: Grocery prices specific to your store
3. **Relevant Results**: No seeing items from stores 100 miles away

### When to Set Location IDs

- **Always** for grocery stores (prices vary significantly)
- **Recommended** for warehouse clubs
- **Optional** for big box retailers (national pricing)
- **Skip** for online-only stores

## Troubleshooting

### Prices Don't Match Store

1. Verify your Location ID is correct
2. Check if the store uses ZIP-based or store-number-based location
3. Some stores require login for member pricing (Costco, Sam's)

### "Store Not Found" Errors

1. The store may have changed their location system
2. Try removing the location ID to use default
3. Report the issue so we can update the template

### Missing Local Stock Info

1. Some stores don't expose stock via search results
2. Stock info may require visiting the product page
3. Consider this store as "online check only"

## API Reference

### Set Location ID

```
POST /api/stores/{id}/location
Content-Type: application/json

{
    "location_id": "00123"
}
```

### Get User Location Context

The location context is automatically included when:

- Calling `StoreDiscoveryService->discoverPrices()`
- Running `FirecrawlDiscoveryJob` (now uses Crawl4AI internally)

### Store Response Fields

```json
{
    "id": 1,
    "name": "Kroger",
    "domain": "kroger.com",
    "search_url_template": "https://www.kroger.com/search?query={query}",
    "location_id": "00123",
    "parent_store_id": null,
    "effective_search_url_template": "https://www.kroger.com/search?query={query}"
}
```

## Related Documentation

- [Store Registry](./store-registry.md) - How to add and manage stores
- [ADR 012: Store Registry Architecture](./adr/012-store-registry-architecture.md)
- [ADR 014: Tiered Search URL Detection](./adr/014-tiered-search-url-detection.md)
