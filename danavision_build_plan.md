# DanaVision Build Plan

A smart shopping and price tracking application for Dana. Create shopping lists, track prices across Amazon and retailers, get AI-powered purchase recommendations, and receive email alerts when prices drop.

## Overview

DanaVision replicates Housarr's architecture for a price comparison use case:
- **Image + text search** - Snap a photo or type to find products
- **Shopping lists** - Create and share lists with other users
- **Daily price tracking** - Automatic price updates with history
- **AI cost recommendations** - Multi-provider AI analyzes prices and suggests best deals
- **Email alerts** - Get notified when prices drop (using Housarr's email system)
- **Mobile-first** - React Native frontend (vs Housarr's React web)

---

## Technology Stack

### Backend (mirrors Housarr exactly)
| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 11.0 | API framework |
| PHP | ^8.2 | Runtime |
| Laravel Sanctum | ^4.0 | Token authentication |
| SQLite | embedded | Database |
| Pest PHP | ^3.0 | Testing |

### Frontend (adapted for mobile)
| Technology | Version | Purpose |
|------------|---------|---------|
| React Native | 0.76+ | Mobile framework |
| Expo | 52+ | Managed workflow |
| TypeScript | 5.6+ | Type safety |
| Zustand | 5.0+ | State management |
| TanStack Query | 5.60+ | Data fetching |
| NativeWind | 4.0+ | Tailwind for RN |
| React Hook Form | 7.53+ | Form handling |
| Zod | 3.23+ | Validation |

### Infrastructure
| Technology | Purpose |
|------------|---------|
| Docker | Single container for dev AND production |
| Nginx | Web server (in container) |
| PHP-FPM | PHP processor (in container) |
| Supervisor | Process management (in container) |
| SQLite | Embedded database (in container) |

> **Note**: Docker is pre-installed for development. Both dev and production use the same single-container approach - no separate docker-compose for dev vs prod.

---

## Project Structure

```
DanaVision/
├── backend/                    # Laravel API application
│   ├── app/
│   │   ├── Actions/            # Action classes
│   │   │   └── Products/
│   │   │       ├── SearchProductsAction.php
│   │   │       ├── AnalyzeImageAction.php
│   │   │       └── AnalyzePricesAction.php
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── Api/
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── DashboardController.php
│   │   │   │       ├── SearchController.php
│   │   │   │       ├── ShoppingListController.php
│   │   │   │       ├── ListItemController.php
│   │   │   │       ├── ListShareController.php
│   │   │   │       ├── PriceAlertController.php
│   │   │   │       ├── SettingController.php
│   │   │   │       └── ProfileController.php
│   │   │   ├── Middleware/
│   │   │   ├── Requests/
│   │   │   │   └── Api/
│   │   │   └── Resources/
│   │   ├── Jobs/
│   │   │   ├── RefreshListPrices.php
│   │   │   ├── DailyPriceUpdate.php
│   │   │   └── SendPriceAlertEmail.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── ShoppingList.php
│   │   │   ├── ListItem.php
│   │   │   ├── ListShare.php
│   │   │   ├── PriceHistory.php
│   │   │   ├── SearchHistory.php
│   │   │   ├── Notification.php
│   │   │   └── Setting.php
│   │   ├── Notifications/
│   │   │   ├── PriceDropAlert.php
│   │   │   ├── DailyPriceSummary.php
│   │   │   └── ListSharedWithYou.php
│   │   ├── Policies/
│   │   │   ├── ShoppingListPolicy.php
│   │   │   ├── ListItemPolicy.php
│   │   │   └── ListSharePolicy.php
│   │   ├── Providers/
│   │   └── Services/
│   │       ├── AI/
│   │       │   ├── AIService.php
│   │       │   └── Agents/
│   │       │       └── PriceRecommendationAgent.php
│   │       ├── PriceApi/
│   │       │   ├── PriceApiService.php
│   │       │   ├── PriceProviderInterface.php
│   │       │   └── Providers/
│   │       │       ├── SerpApiProvider.php
│   │       │       └── RainforestProvider.php
│   │       └── Mail/
│   │           └── MailService.php
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── tests/
│       ├── Feature/
│       │   ├── Auth/
│       │   ├── Dashboard/
│       │   ├── ShoppingLists/
│       │   ├── Search/
│       │   └── Settings/
│       └── Unit/
│           ├── Services/
│           └── Actions/
├── frontend/                   # React Native application
│   ├── src/
│   │   ├── components/
│   │   │   ├── common/
│   │   │   │   ├── Button.tsx
│   │   │   │   ├── Input.tsx
│   │   │   │   ├── Card.tsx
│   │   │   │   └── LoadingSpinner.tsx
│   │   │   ├── search/
│   │   │   │   ├── SearchBar.tsx
│   │   │   │   ├── ImageSearchButton.tsx
│   │   │   │   ├── SearchResults.tsx
│   │   │   │   └── PriceCard.tsx
│   │   │   ├── lists/
│   │   │   │   ├── ListCard.tsx
│   │   │   │   ├── ListItemCard.tsx
│   │   │   │   ├── PriceChangeIndicator.tsx
│   │   │   │   ├── ShareListModal.tsx
│   │   │   │   └── AddToListModal.tsx
│   │   │   ├── dashboard/
│   │   │   │   ├── PriceChangeSummary.tsx
│   │   │   │   ├── RecentDropsCard.tsx
│   │   │   │   └── ListOverviewCard.tsx
│   │   │   └── ai/
│   │   │       └── RecommendationCard.tsx
│   │   ├── screens/
│   │   │   ├── auth/
│   │   │   │   ├── LoginScreen.tsx
│   │   │   │   └── RegisterScreen.tsx
│   │   │   ├── DashboardScreen.tsx
│   │   │   ├── SearchScreen.tsx
│   │   │   ├── SearchResultsScreen.tsx
│   │   │   ├── ProductDetailScreen.tsx
│   │   │   ├── ListsScreen.tsx
│   │   │   ├── ListDetailScreen.tsx
│   │   │   ├── SharedWithMeScreen.tsx
│   │   │   ├── HistoryScreen.tsx
│   │   │   └── SettingsScreen.tsx
│   │   ├── navigation/
│   │   │   ├── RootNavigator.tsx
│   │   │   ├── AuthNavigator.tsx
│   │   │   └── MainNavigator.tsx
│   │   ├── services/
│   │   │   └── api.ts
│   │   ├── stores/
│   │   │   ├── authStore.ts
│   │   │   ├── searchStore.ts
│   │   │   ├── listsStore.ts
│   │   │   └── settingsStore.ts
│   │   ├── hooks/
│   │   │   ├── useSearch.ts
│   │   │   ├── useLists.ts
│   │   │   ├── useListItems.ts
│   │   │   └── useDashboard.ts
│   │   ├── types/
│   │   │   └── index.ts
│   │   └── lib/
│   │       ├── utils.ts
│   │       ├── camera.ts
│   │       └── errors.ts
│   ├── e2e/
│   │   ├── search.test.ts
│   │   ├── lists.test.ts
│   │   └── sharing.test.ts
│   └── __tests__/
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── supervisord.conf
├── docs/
│   ├── DOCUMENTATION.md
│   ├── DOCUMENTATION_LARAVEL.md
│   ├── DOCUMENTATION_REACT.md
│   ├── DOCUMENTATION_DOCKER.md
│   ├── DOCUMENTATION_TESTING.md
│   ├── CONTRIBUTING.md
│   ├── REQUIREMENTS.md
│   └── adr/
│       ├── README.md
│       ├── 001-tech-stack.md
│       ├── 002-ai-provider-abstraction.md
│       ├── 003-price-api-abstraction.md
│       ├── 004-mobile-first-architecture.md
│       ├── 005-user-based-lists.md
│       └── 006-email-notifications.md
├── docker-compose.yml
├── README.md
└── LICENSE
```

---

## Data Models

### User Model
```php
// Owner of all lists and settings
fillable: ['name', 'email', 'password', 'email_verified_at']
relationships:
  - notifications(): HasMany Notification
  - searchHistory(): HasMany SearchHistory
  - shoppingLists(): HasMany ShoppingList
  - sharedLists(): BelongsToMany ShoppingList (via list_shares)
  - settings(): HasMany Setting
```

### ShoppingList Model
```php
// A collection of products to track (e.g., "Kitchen Wishlist", "Kids School Supplies")
fillable: [
  'user_id',          // Owner
  'name',             // "Kitchen Wishlist"
  'description',      // Optional description
  'is_active',        // Active lists get daily price updates
  'notify_on_any_drop',    // Email on any price drop
  'notify_on_threshold',   // Email when drop exceeds threshold
  'threshold_percent',     // e.g., 10 = notify if drops 10%+
  'last_refreshed_at',
]
casts:
  - is_active: boolean
  - notify_on_any_drop: boolean
  - notify_on_threshold: boolean
  - threshold_percent: integer
  - last_refreshed_at: datetime
relationships:
  - user(): BelongsTo User (owner)
  - items(): HasMany ListItem
  - shares(): HasMany ListShare
  - sharedWith(): BelongsToMany User (via list_shares)
```

### ListItem Model
```php
// A product being tracked within a list
fillable: [
  'shopping_list_id',
  'added_by_user_id',    // Who added it (could be shared user)
  'product_name',
  'product_query',       // Search term or URL used
  'product_image_url',   // Cached product image
  'product_url',         // Direct link to product
  'notes',
  'target_price',        // Alert when below this
  'current_price',
  'previous_price',      // For change detection
  'lowest_price',        // All-time low tracked
  'highest_price',       // All-time high tracked
  'current_retailer',
  'in_stock',
  'priority',            // 'low', 'medium', 'high'
  'is_purchased',        // Mark as bought
  'purchased_at',
  'purchased_price',
  'last_checked_at',
]
casts:
  - target_price: decimal:2
  - current_price: decimal:2
  - previous_price: decimal:2
  - lowest_price: decimal:2
  - highest_price: decimal:2
  - purchased_price: decimal:2
  - in_stock: boolean
  - is_purchased: boolean
  - purchased_at: datetime
  - last_checked_at: datetime
relationships:
  - shoppingList(): BelongsTo ShoppingList
  - addedBy(): BelongsTo User
  - priceHistory(): HasMany PriceHistory
methods:
  - priceChange(): Returns price difference from previous
  - priceChangePercent(): Returns percentage change
  - isAtAllTimeLow(): Returns true if current equals lowest
```

### ListShare Model
```php
// Sharing a list with another user
fillable: [
  'shopping_list_id',
  'user_id',             // User being shared with
  'shared_by_user_id',   // User who shared it
  'permission',          // 'view', 'edit', 'admin'
  'accepted_at',         // Null until accepted
]
casts:
  - accepted_at: datetime
relationships:
  - shoppingList(): BelongsTo ShoppingList
  - user(): BelongsTo User
  - sharedBy(): BelongsTo User
scopes:
  - pending(): Where accepted_at is null
  - accepted(): Where accepted_at is not null
```

### PriceHistory Model
```php
// Historical price data for a list item
fillable: [
  'list_item_id',
  'price',
  'retailer',
  'url',
  'in_stock',
  'captured_at',
  'source',              // 'manual', 'daily_job', 'user_refresh'
]
casts:
  - price: decimal:2
  - in_stock: boolean
  - captured_at: datetime
relationships:
  - listItem(): BelongsTo ListItem
```

### SearchHistory Model
```php
// Tracks user searches for quick re-search
fillable: [
  'user_id',
  'query',
  'query_type',          // 'text', 'image', 'url'
  'results_count',
  'image_path',          // If image search, store the image
]
relationships:
  - user(): BelongsTo User
```

### Notification Model
```php
fillable: ['user_id', 'type', 'data', 'read_at']
casts:
  - data: array
  - read_at: datetime
types:
  - 'price_drop': Price dropped on tracked item
  - 'list_shared': Someone shared a list with you
  - 'daily_summary': Daily price summary
  - 'all_time_low': Item hit all-time low price
```

### Setting Model
```php
// User-specific settings (mirrors Housarr pattern)
fillable: ['user_id', 'key', 'value', 'is_encrypted']
static methods:
  - get(key, userId, default)
  - set(key, value, userId, encrypted)
  - getMany(keys, userId)
keys:
  # AI Settings
  - ai_provider: 'claude' | 'openai' | 'gemini' | 'local'
  - ai_model
  - anthropic_api_key (encrypted)
  - openai_api_key (encrypted)
  - gemini_api_key (encrypted)
  # Price API Settings
  - price_api_provider: 'serpapi' | 'rainforest'
  - serpapi_key (encrypted)
  - rainforest_key (encrypted)
  # Email Settings (mirrors Housarr MailService)
  - mail_driver: 'smtp' | 'mailgun' | 'sendgrid' | 'ses' | 'log'
  - mail_host, mail_port, mail_username, mail_password (encrypted)
  - mail_from_address, mail_from_name
  # Notification Preferences
  - notify_price_drops: boolean
  - notify_daily_summary: boolean
  - daily_summary_time: '09:00'
```

---

## API Routes

### Public Routes (Rate Limited: 10/minute)
```
POST /api/auth/register
POST /api/auth/login
```

### Protected Routes (Rate Limited: 60/minute)

**Auth**:
```
POST   /api/auth/logout
GET    /api/auth/user
```

**Profile**:
```
GET    /api/profile
PATCH  /api/profile
PUT    /api/profile/password
```

**Dashboard**:
```
GET    /api/dashboard                 # Price changes, drops, list summaries
GET    /api/dashboard/price-drops     # Recent price drops across all lists
GET    /api/dashboard/activity        # Recent activity feed
```

**Search**:
```
POST   /api/search                    # Text search for products
POST   /api/search/image              # Image-based product search
GET    /api/search/history            # Get search history
DELETE /api/search/history/{id}       # Delete history item
POST   /api/search/ai-recommend       # Get AI recommendation for results
```

**Shopping Lists**:
```
GET    /api/lists                     # All my lists + shared with me
POST   /api/lists                     # Create new list
GET    /api/lists/{list}              # Get list with items
PATCH  /api/lists/{list}              # Update list settings
DELETE /api/lists/{list}              # Delete list (owner only)
POST   /api/lists/{list}/refresh      # Manually refresh all prices
GET    /api/lists/{list}/price-history # Price history for all items
```

**List Items**:
```
GET    /api/lists/{list}/items        # Get items in list
POST   /api/lists/{list}/items        # Add item to list
PATCH  /api/items/{item}              # Update item
DELETE /api/items/{item}              # Remove item from list
POST   /api/items/{item}/refresh      # Refresh single item price
POST   /api/items/{item}/purchased    # Mark as purchased
GET    /api/items/{item}/history      # Get price history
```

**List Sharing**:
```
GET    /api/lists/{list}/shares       # List who has access
POST   /api/lists/{list}/shares       # Share with user (by email)
PATCH  /api/shares/{share}            # Update permission
DELETE /api/shares/{share}            # Remove access
GET    /api/shares/pending            # Lists shared with me (pending)
POST   /api/shares/{share}/accept     # Accept shared list
POST   /api/shares/{share}/decline    # Decline shared list
```

**Notifications**:
```
GET    /api/notifications
POST   /api/notifications/mark-read
DELETE /api/notifications/{id}
```

**Settings**:
```
GET    /api/settings
PATCH  /api/settings
GET    /api/settings/ai
POST   /api/settings/ai/test
GET    /api/settings/price-api
POST   /api/settings/price-api/test
GET    /api/settings/email
POST   /api/settings/email/test
```

---

## Services

### AIService (mirrors Housarr)
```php
namespace App\Services\AI;

class AIService
{
    public function __construct(?int $userId = null)
    
    public static function forUser(?int $userId): self
    
    public function isAvailable(): bool
    public function getProvider(): string
    public function getModel(): string
    
    public function complete(string $prompt, array $options = []): string
    public function completeWithError(string $prompt, array $options = []): array
    
    // Image analysis for product identification
    public function analyzeImage(string $base64Image, string $mimeType, string $prompt): string
    
    // Supported providers: claude, openai, gemini, local
}
```

### PriceApiService
```php
namespace App\Services\PriceApi;

class PriceApiService
{
    public function __construct(?int $userId = null)
    
    public static function forUser(?int $userId): self
    
    public function isAvailable(): bool
    public function getProvider(): string
    
    public function search(string $query, string $type = 'product'): PriceSearchResult
    public function searchWithCache(string $query, string $type = 'product', int $ttl = 900): PriceSearchResult
    
    // Supported providers: serpapi (Google Shopping), rainforest (Amazon)
}

interface PriceProviderInterface
{
    public function search(string $query, array $options = []): array;
    public function isConfigured(): bool;
    public function testConnection(): bool;
}
```

### MailService (mirrors Housarr)
```php
namespace App\Services\Mail;

class MailService
{
    public static function configureForUser(?int $userId): void
    public static function getDriverName(?int $userId): string
    public static function isConfigured(?int $userId): bool
    public static function testConnection(?int $userId): bool
    
    // Supported drivers: smtp, mailgun, sendgrid, ses, log
}
```

### PriceRecommendationAgent
```php
namespace App\Services\AI\Agents;

class PriceRecommendationAgent
{
    public function __construct(AIService $ai)
    
    public function analyze(array $priceResults, array $context = []): PriceRecommendation
    
    // Returns:
    // - bestValue: Best overall deal considering price + retailer reliability
    // - lowestPrice: Absolute lowest price
    // - recommendation: AI-generated buying advice
    // - insights: Array of observations about pricing patterns
    // - buyNow: Boolean - should Dana buy now or wait?
    // - waitReason: If buyNow is false, why wait?
}
```

### ProductImageAnalyzer
```php
namespace App\Services\AI\Agents;

class ProductImageAnalyzer
{
    public function __construct(AIService $ai)
    
    public function analyze(string $base64Image, string $mimeType): ProductIdentification
    
    // Returns:
    // - productName: Best guess at product name
    // - brand: Identified brand
    // - model: Model number if visible
    // - category: Product category
    // - searchTerms: Array of suggested search terms
    // - confidence: 0-100 confidence score
}
```

---

## Policies (User & Sharing Authorization)

### ShoppingListPolicy
```php
// Owner or shared user with appropriate permission
public function view(User $user, ShoppingList $list): bool
{
    return $user->id === $list->user_id 
        || $list->shares()->where('user_id', $user->id)->accepted()->exists();
}

public function update(User $user, ShoppingList $list): bool
{
    return $user->id === $list->user_id 
        || $list->shares()
            ->where('user_id', $user->id)
            ->whereIn('permission', ['edit', 'admin'])
            ->accepted()
            ->exists();
}

public function delete(User $user, ShoppingList $list): bool
{
    return $user->id === $list->user_id; // Owner only
}

public function share(User $user, ShoppingList $list): bool
{
    return $user->id === $list->user_id 
        || $list->shares()
            ->where('user_id', $user->id)
            ->where('permission', 'admin')
            ->accepted()
            ->exists();
}
```

### ListItemPolicy
```php
public function view(User $user, ListItem $item): bool
{
    return Gate::allows('view', $item->shoppingList);
}

public function create(User $user, ShoppingList $list): bool
{
    return Gate::allows('update', $list);
}

public function update(User $user, ListItem $item): bool
{
    return Gate::allows('update', $item->shoppingList);
}

public function delete(User $user, ListItem $item): bool
{
    return Gate::allows('update', $item->shoppingList);
}
```

---

## TypeScript Types (API Contract)

```typescript
// frontend/src/types/index.ts

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
}

export interface ShoppingList {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  notify_on_any_drop: boolean;
  notify_on_threshold: boolean;
  threshold_percent: number | null;
  last_refreshed_at: string | null;
  created_at: string;
  updated_at: string;
  // Computed
  items_count?: number;
  items_with_drops?: number;
  is_owner?: boolean;
  permission?: 'owner' | 'view' | 'edit' | 'admin';
}

export interface ListItem {
  id: number;
  shopping_list_id: number;
  added_by_user_id: number;
  product_name: string;
  product_query: string;
  product_image_url: string | null;
  product_url: string | null;
  notes: string | null;
  target_price: number | null;
  current_price: number | null;
  previous_price: number | null;
  lowest_price: number | null;
  highest_price: number | null;
  current_retailer: string | null;
  in_stock: boolean;
  priority: 'low' | 'medium' | 'high';
  is_purchased: boolean;
  purchased_at: string | null;
  purchased_price: number | null;
  last_checked_at: string | null;
  created_at: string;
  // Computed
  price_change?: number;
  price_change_percent?: number;
  is_at_all_time_low?: boolean;
  added_by?: User;
}

export interface ListShare {
  id: number;
  shopping_list_id: number;
  user_id: number;
  shared_by_user_id: number;
  permission: 'view' | 'edit' | 'admin';
  accepted_at: string | null;
  created_at: string;
  // Relationships
  user?: User;
  shared_by?: User;
  shopping_list?: ShoppingList;
}

export interface PriceHistory {
  id: number;
  list_item_id: number;
  price: number;
  retailer: string;
  url: string | null;
  in_stock: boolean;
  captured_at: string;
  source: 'manual' | 'daily_job' | 'user_refresh';
}

export interface PriceResult {
  retailer: string;
  retailer_logo?: string;
  price: number;
  currency: string;
  url: string;
  in_stock: boolean;
  shipping?: string;
  condition?: 'new' | 'used' | 'refurbished';
}

export interface SearchResult {
  id: number;
  query: string;
  query_type: 'text' | 'image' | 'url';
  results: PriceResult[];
  lowest_price: number;
  highest_price: number;
  searched_at: string;
  ai_recommendation?: AIRecommendation;
}

export interface AIRecommendation {
  best_value: PriceResult;
  lowest_price: PriceResult;
  recommendation: string;
  insights: string[];
  confidence: number;
  buy_now: boolean;
  wait_reason?: string;
}

export interface ProductIdentification {
  product_name: string;
  brand: string | null;
  model: string | null;
  category: string | null;
  search_terms: string[];
  confidence: number;
}

export interface DashboardData {
  lists_count: number;
  items_count: number;
  items_with_drops: number;
  total_potential_savings: number;
  recent_drops: ListItem[];
  all_time_lows: ListItem[];
  lists_summary: {
    id: number;
    name: string;
    items_count: number;
    drops_count: number;
  }[];
}

export interface Notification {
  id: number;
  type: 'price_drop' | 'list_shared' | 'daily_summary' | 'all_time_low';
  data: Record<string, any>;
  read_at: string | null;
  created_at: string;
}

export interface Settings {
  // AI
  ai_provider: 'claude' | 'openai' | 'gemini' | 'local' | null;
  ai_configured: boolean;
  // Price API
  price_api_provider: 'serpapi' | 'rainforest' | null;
  price_api_configured: boolean;
  // Email
  mail_driver: 'smtp' | 'mailgun' | 'sendgrid' | 'ses' | 'log' | null;
  mail_configured: boolean;
  // Notification preferences
  notify_price_drops: boolean;
  notify_daily_summary: boolean;
  daily_summary_time: string;
}
```

---

## REQUIREMENTS.md (Contribution Checklist)

### Quick Reference: Requirements by Task Type

| Task Type | Tests Required? | ADR Required? | Docs Required? |
|-----------|----------------|---------------|----------------|
| New API endpoint | ✅ Pest PHP feature test | If pattern change | ✅ Update types |
| New RN component | ✅ Jest + RNTL | ❌ No | ❌ No |
| New screen | ✅ Component + E2E | ❌ No | ❌ No |
| Bug fix | ✅ Regression test | ❌ No | ❌ No |
| New Zustand store | ✅ Store unit test | ❌ No | ❌ No |
| Database change | ✅ Migration + model | ✅ Always | ✅ Update docs |
| Auth/security change | ✅ Security tests | ✅ Always | ✅ Update docs |
| New integration | ✅ Integration tests | ✅ Always | ✅ Update docs |
| AI system changes | ✅ AI service tests | ✅ Always | ✅ Update docs |
| Price API changes | ✅ Provider tests | ✅ Always | ✅ Update docs |
| List sharing changes | ✅ Policy tests | ✅ Always | ✅ Update docs |

### Pre-Submission Checklist

**Security & Authorization (MANDATORY)**:
- [ ] User ownership verified (user_id checks)
- [ ] List sharing permissions enforced
- [ ] New resources have Policy with ownership + sharing checks
- [ ] Controllers call `Gate::authorize()`
- [ ] API contract synchronized (Model → Resource → TypeScript)
- [ ] No N+1 queries

**Testing (MANDATORY)**:
- [ ] Backend tests pass: `cd backend && ./vendor/bin/pest`
- [ ] Frontend tests pass: `cd frontend && npm test`
- [ ] New features have tests
- [ ] Bug fixes include regression test

**Documentation (for significant changes)**:
- [ ] ADR created for architectural decisions
- [ ] TypeScript types updated
- [ ] API docs updated

---

## ADR Template

```markdown
# ADR [NUMBER]: [TITLE]

## Status
[Proposed | Accepted | Deprecated | Superseded]

## Date
[YYYY-MM-DD]

## Context
[Describe the context and problem statement]

## Decision
[Describe the decision and rationale]

## Consequences

### Positive
- [List positive consequences]

### Negative
- [List negative consequences]

## Related Decisions
- [Links to related ADRs]
```

---

## Initial ADRs Required

1. **ADR-001: Technology Stack** - Laravel 11 + React Native + SQLite
2. **ADR-002: AI Provider Abstraction** - Multi-provider support (Claude, OpenAI, Gemini, Local)
3. **ADR-003: Price API Abstraction** - Multi-provider support (SerpApi for Google Shopping, Rainforest for Amazon)
4. **ADR-004: Mobile-First Architecture** - React Native vs React Web decision
5. **ADR-005: User-Based Lists with Sharing** - No households, user owns lists, sharing via permissions
6. **ADR-006: Email Notification System** - Mirrors Housarr MailService with multiple drivers
7. **ADR-007: Daily Price Update Strategy** - Scheduled jobs, rate limiting, priority queuing

---

## Phase 1 Cursor Prompts

### 1.1 Project Scaffolding with Docker
```
Create the DanaVision project structure with Docker-first development:

1. Create project root with:
   - docker/Dockerfile (single container: nginx + php-fpm + supervisor)
   - docker/nginx.conf and docker/default.conf
   - docker/supervisord.conf (nginx, php-fpm, scheduler)
   - docker/php.ini
   - docker-compose.yml (dev config with volume mounts)
   - .env.example

2. Create Laravel 11 backend inside backend/ directory:
   - Configure as API-only (remove web routes)
   - Install and configure Sanctum for token auth  
   - Set up SQLite as default database (at /var/www/html/database/database.sqlite)
   - Create health check endpoint at GET /api/health
   - Configure CORS for React Native (localhost, 10.0.2.2 for Android emulator)
   - Set up scheduler for daily price updates

3. Create Expo React Native project inside frontend/ directory:
   - TypeScript template
   - Configure API_BASE_URL to point to container

The container should be fully functional with:
docker compose up -d

Follow the project structure from the build plan exactly.
```

### 1.2 Core Models
```
Create the following Eloquent models for DanaVision following Housarr patterns:

1. User - owner of lists and settings
2. ShoppingList - a named collection of products to track
3. ListItem - a product within a list with price tracking
4. ListShare - sharing lists with other users (with permissions)
5. PriceHistory - historical price snapshots
6. SearchHistory - tracks user searches
7. Notification - user notifications
8. Setting - user-specific encrypted settings (copy Housarr exactly)

Include:
- All fillable fields, casts, and relationships
- Scopes where appropriate
- Factory for each model
- Policies with ownership + sharing permission checks

Reference the Data Models section of the build plan.
```

### 1.3 AIService with Image Analysis
```
Create app/Services/AI/AIService.php following Housarr's AIService exactly:

1. Support providers: claude, openai, gemini, local
2. Use Setting model for user-specific configuration
3. Include complete() and completeWithError() methods
4. Include analyzeImage() method for product photo identification
5. Support user-specific configuration (not household)
6. Include isAvailable(), getProvider(), getModel() methods

Also create app/Services/AI/Agents/ProductImageAnalyzer.php:
- Takes image, returns ProductIdentification with name, brand, model, search terms
```

### 1.4 PriceApiService
```
Create app/Services/PriceApi/PriceApiService.php following Housarr's service patterns:

1. Create PriceProviderInterface with search() and isConfigured() methods
2. Implement SerpApiProvider for Google Shopping searches
3. Implement RainforestProvider for Amazon product searches
4. Main PriceApiService orchestrates providers based on user settings
5. Include caching layer (15 min TTL default)

The search method should return standardized results:
{
  "query": string,
  "results": [
    {
      "retailer": string,
      "price": float,
      "currency": string,
      "url": string,
      "in_stock": boolean,
      "shipping": string|null
    }
  ],
  "searched_at": timestamp
}
```

### 1.5 MailService
```
Create app/Services/Mail/MailService.php mirroring Housarr's MailService:

1. Support drivers: smtp, mailgun, sendgrid, ses, log
2. Configure mailer per-user from Settings
3. Include configureForUser(), isConfigured(), testConnection() methods

Create notifications:
- PriceDropAlert - when tracked item drops in price
- DailyPriceSummary - daily digest of all price changes
- AllTimeLowAlert - when item hits all-time low
- ListSharedWithYou - when someone shares a list
```

---

## Initial ADRs Required

1. **ADR-001: Technology Stack** - Laravel 11 + React Native + SQLite
2. **ADR-002: AI Provider Abstraction** - Multi-provider support (Claude, OpenAI, Gemini, Local)
3. **ADR-003: Price API Abstraction** - Multi-provider support (SerpApi for Google Shopping, Rainforest for Amazon)
4. **ADR-004: Mobile-First Architecture** - React Native vs React Web decision
5. **ADR-005: User-Based Lists with Sharing** - No households, user owns lists, sharing via permissions
6. **ADR-006: Email Notification System** - Mirrors Housarr MailService with multiple drivers
7. **ADR-007: Daily Price Update Strategy** - Scheduled jobs, rate limiting, priority queuing

---

## Testing Requirements

### Backend (Pest PHP)
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegistrationTest.php
│   │   ├── LoginTest.php
│   │   └── LogoutTest.php
│   ├── Dashboard/
│   │   └── DashboardTest.php
│   ├── ShoppingLists/
│   │   ├── CreateListTest.php
│   │   ├── UpdateListTest.php
│   │   ├── DeleteListTest.php
│   │   ├── ListItemsTest.php
│   │   └── RefreshPricesTest.php
│   ├── Sharing/
│   │   ├── ShareListTest.php
│   │   ├── AcceptShareTest.php
│   │   └── PermissionsTest.php
│   ├── Search/
│   │   ├── TextSearchTest.php
│   │   ├── ImageSearchTest.php
│   │   └── AIRecommendationTest.php
│   └── Policies/
│       ├── ListOwnershipTest.php
│       └── SharePermissionsTest.php
└── Unit/
    ├── Services/
    │   ├── AIServiceTest.php
    │   ├── PriceApiServiceTest.php
    │   ├── MailServiceTest.php
    │   └── PriceRecommendationAgentTest.php
    ├── Jobs/
    │   ├── DailyPriceUpdateTest.php
    │   └── RefreshListPricesTest.php
    └── Models/
        ├── SettingTest.php
        ├── ShoppingListTest.php
        └── ListItemTest.php
```

### Frontend (Jest + RNTL)
```
__tests__/
├── components/
│   ├── SearchBar.test.tsx
│   ├── ImageSearchButton.test.tsx
│   ├── PriceCard.test.tsx
│   ├── ListCard.test.tsx
│   ├── ListItemCard.test.tsx
│   └── PriceChangeIndicator.test.tsx
├── screens/
│   ├── DashboardScreen.test.tsx
│   ├── ListsScreen.test.tsx
│   ├── ListDetailScreen.test.tsx
│   └── SearchScreen.test.tsx
├── stores/
│   ├── authStore.test.ts
│   ├── listsStore.test.ts
│   └── searchStore.test.ts
└── services/
    └── api.test.ts
```

---

## Environment Variables

All environment is managed via `.env` file at project root (loaded by docker-compose).

### .env.example
```bash
# App
APP_NAME=DanaVision
APP_KEY=
APP_URL=http://localhost:8000
APP_ENV=local
APP_DEBUG=true

# Database (SQLite in container)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

# Auth
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8081,10.0.2.2

# Timezone
TZ=America/Chicago
SCHEDULE_TIMEZONE=America/Chicago

# API Keys (user-specific settings stored in DB, but can set defaults here)
# SERPAPI_KEY=
# ANTHROPIC_API_KEY=
```

### Frontend (frontend/.env)
```bash
EXPO_PUBLIC_API_URL=http://localhost:8000/api
EXPO_PUBLIC_APP_NAME=DanaVision
```

### Generate APP_KEY
```bash
docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

---

## Docker Deployment

**Single container for both development and production** - matching Housarr's approach. Everything runs in one container: Nginx, PHP-FPM, SQLite, scheduler, and the React Native metro bundler connects externally.

### Development Setup

```bash
# Clone the repo
git clone https://github.com/jpittelkow/DanaVision.git
cd DanaVision

# Generate APP_KEY
docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Copy and configure environment
cp .env.example .env
# Edit .env and paste your APP_KEY

# Build and run (dev mode with hot reload for backend)
docker compose up -d

# View logs
docker compose logs -f

# Run migrations (first time)
docker compose exec danavision php artisan migrate

# Stop
docker compose down
```

**Frontend Development**: React Native runs separately via Expo on your host machine, connecting to the containerized API.

```bash
cd frontend
npm install
npm start  # Expo dev server
```

### docker-compose.yml

```yaml
services:
  danavision:
    build:
      context: .
      dockerfile: docker/Dockerfile
    image: ghcr.io/jpittelkow/danavision:latest
    container_name: danavision
    ports:
      - "8000:80"
    volumes:
      # Persistent data
      - danavision_data:/var/www/html/database
      - danavision_storage:/var/www/html/storage/app
      # Dev: mount source for hot reload (comment out for production)
      - ./backend:/var/www/html
    environment:
      - APP_NAME=DanaVision
      - APP_KEY=${APP_KEY}
      - APP_URL=http://localhost:8000
      - APP_ENV=${APP_ENV:-local}
      - APP_DEBUG=${APP_DEBUG:-true}
      - TZ=America/Chicago
      - SCHEDULE_TIMEZONE=America/Chicago
    restart: unless-stopped

volumes:
  danavision_data:
  danavision_storage:
```

### Dockerfile

```dockerfile
# docker/Dockerfile
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor.d/danavision.ini

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY backend/ .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend (for production - embedded in container)
COPY frontend/ /tmp/frontend
RUN cd /tmp/frontend && npm ci && npm run build \
    && cp -r /tmp/frontend/dist/* /var/www/html/public/ \
    && rm -rf /tmp/frontend

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Create SQLite database directory
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/database

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
```

### supervisord.conf

```ini
# docker/supervisord.conf
[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
command=/bin/sh -c "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction; sleep 60; done"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### Production Deployment

Same container, just change environment:

```bash
# Pull latest image
docker pull ghcr.io/jpittelkow/danavision:latest

# Run in production mode
docker run -d \
  --name danavision \
  -p 8000:80 \
  -v danavision_data:/var/www/html/database \
  -v danavision_storage:/var/www/html/storage/app \
  -e APP_KEY=base64:YOUR_KEY_HERE \
  -e APP_URL=https://danavision.yourdomain.com \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e TZ=America/Chicago \
  --restart unless-stopped \
  ghcr.io/jpittelkow/danavision:latest
```

### Quick Commands

```bash
# Rebuild container after code changes
docker compose build --no-cache

# Run artisan commands
docker compose exec danavision php artisan migrate
docker compose exec danavision php artisan tinker
docker compose exec danavision php artisan schedule:list

# Run tests inside container
docker compose exec danavision ./vendor/bin/pest

# View scheduler logs
docker compose logs -f danavision | grep scheduler

# Shell into container
docker compose exec danavision sh
```

---

## Background Jobs

### DailyPriceUpdate Job
```php
// Runs daily via scheduler
// 1. Get all active lists
// 2. For each list item, refresh price
// 3. Detect changes and record to PriceHistory
// 4. Send notifications for drops
// 5. Send daily summary emails if enabled
```

### RefreshListPrices Job
```php
// Triggered manually by user
// Rate limited to prevent API abuse
// Updates all items in a single list
```

---

## Dashboard Features

The dashboard shows Dana:

1. **Price Drops Today** - Items that dropped since last check
2. **All-Time Lows** - Items currently at their lowest tracked price
3. **Lists Overview** - Summary of each list with drop counts
4. **Recent Activity** - Timeline of price changes and shares
5. **Potential Savings** - Total savings if bought at current vs original prices

---

## Build Order

1. **Phase 1: Docker + Project Setup**
   - Docker configuration (Dockerfile, docker-compose, nginx, supervisor)
   - Laravel scaffolding inside container
   - Expo React Native project setup
   - Verify container runs: `docker compose up -d`

2. **Phase 2: Backend Foundation**
   - Core models + migrations
   - AIService with image analysis
   - PriceApiService + providers (SerpApi, Rainforest)
   - MailService (copy Housarr pattern)
   - Run migrations: `docker compose exec danavision php artisan migrate`

3. **Phase 3: Core API**
   - Auth endpoints (register, login, logout)
   - Shopping List CRUD
   - List Item CRUD with price tracking
   - Search endpoints (text + image)
   - Dashboard endpoint

4. **Phase 4: Sharing & Background Jobs**
   - List sharing with permissions
   - Accept/decline invitations
   - Email notifications
   - Daily price update job (via supervisor scheduler)

5. **Phase 5: React Native Screens**
   - Navigation structure
   - Auth screens
   - Dashboard with price drops
   - Lists management
   - List detail with items
   - Search (text + camera)

6. **Phase 6: Polish**
   - Settings screens
   - Sharing UI
   - Push notifications
   - Offline caching
   - E2E tests
   - Production Docker image build

---

## Notes for Cursor

When implementing, always:

1. **Docker-first development** - All backend work happens inside the container. Use `docker compose exec danavision` for artisan commands
2. **Check Housarr patterns first** - Mirror the code style and architecture
3. **Write tests alongside code** - Every feature needs tests (run inside container)
4. **Maintain user ownership** - All queries scoped to user or shared lists
5. **Keep API contract synchronized** - Model → Resource → TypeScript
6. **Create ADRs for decisions** - Document why, not just what
7. **This is for Dana** - Make it intuitive and delightful to use! 💜

### Common Docker Commands
```bash
# Start development
docker compose up -d

# View logs
docker compose logs -f

# Run artisan
docker compose exec danavision php artisan <command>

# Run tests
docker compose exec danavision ./vendor/bin/pest

# Rebuild after Dockerfile changes
docker compose build --no-cache && docker compose up -d

# Shell access
docker compose exec danavision sh
```
