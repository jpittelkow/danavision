# Store Registry Documentation

The Store Registry is a system for managing retail stores in DanaVision. It enables cost-effective price discovery by maintaining pre-configured search URL templates for known retailers.

## Overview

The Store Registry provides:

- **Pre-configured stores**: 70 major US retailers with verified search URL templates (automatically seeded)
- **Custom stores**: Users can add their own stores
- **Store preferences**: Users can enable/disable stores and set favorites
- **Store suppression**: Users can suppress stores they don't want to see
- **Location-aware search**: Support for stores with local pricing/stock
- **Parent/subsidiary relationships**: Link stores that share search infrastructure

## Default Stores

All known stores are **automatically added to the database** when the application is seeded. This means:

1. New users immediately have access to 70 pre-configured stores
2. All stores have verified search URL templates
3. Users can **suppress** any stores they don't want via Settings > Stores
4. Suppressed stores are hidden from the store list but can be restored anytime

### Seeding Stores

To seed the default stores (done automatically on fresh install):

```bash
docker compose exec danavision php artisan db:seed --class=StoreSeeder
```

### Store Categories

| Category | Example Stores |
|----------|---------------|
| General | Amazon, Walmart, Target, Dollar General |
| Electronics | Best Buy, Newegg, Micro Center, GameStop |
| Warehouse | Costco, Sam's Club, BJ's |
| Grocery | Kroger, Albertsons, Publix, H-E-B, Meijer |
| Home/Hardware | Home Depot, Lowe's, Menards, IKEA |
| Pharmacy | CVS, Walgreens, Rite Aid |
| Pet | Petco, PetSmart, Chewy |
| Clothing | Kohl's, Macy's, Nordstrom, TJ Maxx |
| Specialty | Ulta, Sephora, REI, AutoZone |

## Adding New Stores to Known Templates

### Location in Code

Known store templates are defined in:

```
backend/app/Services/Crawler/StoreAutoConfigService.php
```

In the `KNOWN_STORE_TEMPLATES` constant.

### Template Format

Each store entry follows this structure:

```php
'domain.com' => [
    'template' => 'https://www.domain.com/search?q={query}',
    'local_stock' => true,   // Does the store show local stock availability?
    'local_price' => false,  // Does pricing vary by location?
],
```

### Placeholders

Templates support these placeholders:

| Placeholder | Description | Example |
|-------------|-------------|---------|
| `{query}` | **Required.** The search query (URL-encoded) | `laptop` → `laptop` |
| `{store_id}` | Store-specific location ID | `00123` |
| `{zip}` | User's ZIP code from settings | `53202` |
| `{lat}` | User's latitude | `43.0389` |
| `{lng}` | User's longitude | `-87.9065` |

### Example Templates

```php
// Basic search (most common)
'amazon.com' => [
    'template' => 'https://www.amazon.com/s?k={query}',
    'local_stock' => false,
    'local_price' => false,
],

// Store with local inventory
'homedepot.com' => [
    'template' => 'https://www.homedepot.com/s/{query}',
    'local_stock' => true,
    'local_price' => true,
],

// Store with ZIP-based filtering
'microcenter.com' => [
    'template' => 'https://www.microcenter.com/search/search_results.aspx?N=&Ntt={query}&zipcode={zip}',
    'local_stock' => true,
    'local_price' => true,
],
```

### Adding a New Store

1. **Find the search URL pattern**:
   - Go to the store's website
   - Search for a test product (e.g., "laptop")
   - Copy the resulting URL
   - Identify the query parameter (e.g., `q=laptop`)

2. **Create the template**:
   - Replace the search term with `{query}`
   - Remove unnecessary parameters (tracking, session IDs)
   - Test by replacing `{query}` with a product name

3. **Add to the correct category**:

```php
// Find the appropriate category section
// ============================================
// ELECTRONICS
// ============================================
'newegg.com' => [
    'template' => 'https://www.newegg.com/p/pl?d={query}',
    'local_stock' => false,
    'local_price' => false,
],
// Add your new store here
'yourstore.com' => [
    'template' => 'https://www.yourstore.com/search?q={query}',
    'local_stock' => false,
    'local_price' => false,
],
```

4. **Test the template**:

```bash
# In the application, or manually test:
curl "https://www.yourstore.com/search?q=laptop"
# Should return a page with product listings
```

## Subsidiary Relationships

Some stores share the same backend and search infrastructure. For example, Kroger owns Metro Market, Pick 'n Save, and many others.

### Known Chains

Defined in `KNOWN_CHAINS` constant:

```php
protected const KNOWN_CHAINS = [
    'kroger' => [
        'domain' => 'kroger.com',
        'template' => 'https://www.kroger.com/search?query={query}&searchType=default_search',
        'subsidiaries' => [
            'metro market',
            'pick n save',
            "mariano's",
            'fred meyer',
            'ralphs',
            // ... more subsidiaries
        ],
        'location_type' => 'store_id',
    ],
];
```

### How Subsidiaries Work

1. When a store is added (e.g., "Metro Market"), the system checks if it's a known subsidiary
2. If matched, the store is linked to its parent (Kroger)
3. The parent's search URL template is used for price discovery
4. Users can set a `location_id` for store-specific pricing

### Database Schema

```sql
-- stores table
parent_store_id BIGINT UNSIGNED NULL REFERENCES stores(id)

-- user_store_preferences table
location_id VARCHAR(100) NULL
```

## User Store Preferences

Users can customize their store experience:

### Enable/Disable Stores

- Disabled stores are not searched during price discovery
- Default: Major retailers enabled

### Favorite Stores

- Favorites are searched first (higher priority)
- Shown at top of results
- Indicated with star icon

### Local Stores

- Marked as "local" for users who shop there regularly
- Useful for grocery and warehouse stores

### Location ID

For stores with local pricing (Kroger, etc.), users can set their specific store:

1. Go to Settings > Stores
2. Click Edit on the store
3. Enter Location/Store ID
4. Save

The location ID is substituted into `{store_id}` placeholder.

## API Endpoints

### List Stores

```
GET /api/stores
```

Returns all stores with user preferences.

### Update Store Preference

```
PATCH /api/stores/{id}/preference
```

Body:

```json
{
    "enabled": true,
    "priority": 1
}
```

### Toggle Favorite

```
POST /api/stores/{id}/favorite
```

### Set Location ID

```
POST /api/stores/{id}/location
```

Body:

```json
{
    "location_id": "00123"
}
```

### Add Custom Store

```
POST /api/stores
```

Body:

```json
{
    "name": "My Store",
    "domain": "mystore.com",
    "category": "specialty",
    "search_url_template": "https://mystore.com/search?q={query}"
}
```

### Find Search URL

```
POST /api/stores/{id}/find-search-url
```

Triggers automatic detection of search URL template.

### Find Search URL with Agent

```
POST /api/stores/{id}/find-search-url-agent
```

Uses expensive Firecrawl Agent for complex stores.

## Store Categories

| Category | Icon | Examples |
|----------|------|----------|
| `general` | 🛒 | Walmart, Target, Amazon |
| `electronics` | 📺 | Best Buy, Newegg |
| `grocery` | 📦 | Kroger, Publix |
| `home` | 🏠 | Home Depot, Lowe's |
| `clothing` | 👕 | Kohl's, Macy's |
| `pharmacy` | 💊 | CVS, Walgreens |
| `warehouse` | 📦 | Costco, Sam's Club |
| `pet` | 🐕 | Petco, PetSmart |
| `specialty` | ✨ | Custom/other stores |

## Suppressing Stores

Users can suppress stores they don't want to see:

1. Click the X on the store row, or
2. Click Suppress in the edit dialog

Suppressed stores are hidden from the list but can be restored.

## Best Practices

### Template Creation

1. **Keep templates simple**: Remove tracking parameters
2. **Test thoroughly**: Verify the template returns products
3. **Use HTTPS**: Prefer secure URLs
4. **Check for variations**: Some stores have different URL formats

### Store Metadata

1. **local_stock**: Set to `true` if the store shows "In Stock at [location]"
2. **local_price**: Set to `true` if prices vary by store location

### Maintenance

1. **Monitor template health**: Check periodically if templates still work
2. **Update broken templates**: Stores may change their URL structure
3. **Add new stores**: Popular stores should be added to known templates
