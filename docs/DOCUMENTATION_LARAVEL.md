# DanaVision Laravel Backend Documentation

## Overview

The DanaVision backend is built with Laravel 11, providing controllers, business logic, and data persistence. It uses Inertia.js to serve React pages while maintaining traditional Laravel patterns.

## Directory Structure

```
backend/app/
├── Console/
│   └── Commands/           # Artisan commands
├── Http/
│   ├── Controllers/        # Request handlers
│   └── Middleware/         # Request middleware
├── Jobs/                   # Queued jobs
├── Models/                 # Eloquent models
├── Policies/               # Authorization policies
├── Providers/              # Service providers
└── Services/               # Business logic
    ├── AI/                 # AI services
    ├── Mail/               # Email services
    └── PriceApi/           # Price API services
```

## Models

### User

```php
// app/Models/User.php
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
    
    public function shoppingLists(): HasMany;
    public function searchHistory(): HasMany;
    public function aiProviders(): HasMany;
}
```

### ShoppingList

```php
// app/Models/ShoppingList.php
class ShoppingList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'notify_on_any_drop',
        'notify_on_threshold',
        'threshold_percent',
    ];
    
    public function user(): BelongsTo;
    public function items(): HasMany;
    public function shares(): HasMany;
}
```

### ListItem

```php
// app/Models/ListItem.php
class ListItem extends Model
{
    protected $fillable = [
        'shopping_list_id',
        'added_by_user_id',
        'product_name',
        'product_query',
        'product_url',
        'product_image_url',
        'sku',
        'target_price',
        'current_price',
        'previous_price',
        'lowest_price',
        'highest_price',
        'current_retailer',
        'notes',
        'priority',
        'is_purchased',
    ];
    
    public function shoppingList(): BelongsTo;
    public function vendorPrices(): HasMany;
    public function priceHistory(): HasMany;
}
```

### AIProvider

```php
// app/Models/AIProvider.php
class AIProvider extends Model
{
    const PROVIDER_CLAUDE = 'claude';
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_GEMINI = 'gemini';
    const PROVIDER_LOCAL = 'local';
    
    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'model',
        'base_url',
        'is_active',
        'is_primary',
    ];
    
    public static function getPrimaryForUser(int $userId): ?self;
    public static function getActiveForUser(int $userId): Collection;
}
```

## Controllers

### ShoppingListController

```php
// app/Http/Controllers/ShoppingListController.php
class ShoppingListController extends Controller
{
    public function index(Request $request): Response;     // Lists user's lists
    public function create(): Response;                    // Create form
    public function store(Request $request): RedirectResponse;
    public function show(ShoppingList $list): Response;    // List detail
    public function edit(ShoppingList $list): Response;
    public function update(Request $request, ShoppingList $list): RedirectResponse;
    public function destroy(ShoppingList $list): RedirectResponse;
    public function refresh(ShoppingList $list): RedirectResponse;  // Refresh prices
}
```

### SmartAddController

Handles the two-phase Smart Add flow:
- Phase 1: Product identification via AI
- Phase 2: Add to list with background price search

```php
// app/Http/Controllers/SmartAddController.php
class SmartAddController extends Controller
{
    public function index(Request $request): Response;     // Smart Add page
    public function identify(Request $request): JsonResponse;  // AI product identification
    public function addToList(Request $request): RedirectResponse;  // Add to list + dispatch job
    // Legacy endpoints (kept for backward compatibility)
    public function analyzeImage(Request $request): Response;  // AI image analysis
    public function searchText(Request $request): Response;    // Text search
}
```

#### identify Endpoint (Phase 1)

Returns up to 5 product suggestions from AI:

```php
// POST /smart-add/identify
// Request:
[
    'image' => 'nullable|string',  // Base64 encoded image
    'query' => 'nullable|string|max:500',  // Text search query
]

// Response (JSON):
[
    'results' => [
        [
            'product_name' => 'Sony WH-1000XM5 Wireless Headphones',
            'brand' => 'Sony',
            'model' => 'WH-1000XM5',
            'category' => 'Electronics',
            'upc' => '027242917576',
            'is_generic' => false,
            'unit_of_measure' => null,
            'confidence' => 95,
            'image_url' => 'https://...',
        ],
        // ... up to 5 suggestions
    ],
    'providers_used' => ['claude', 'openai'],
    'error' => null,
]
```

#### addToList Endpoint (Phase 2)

Adds item to shopping list and dispatches background price search:

```php
// POST /smart-add/add
// After successful add, SearchItemPrices job is dispatched
```

### SearchItemPrices Job

Background job that searches for prices after an item is added:

```php
// app/Jobs/SearchItemPrices.php
class SearchItemPrices implements ShouldQueue
{
    public function __construct(public int $itemId, public int $userId);
    
    public function handle(): void
    {
        // Search for prices using AIPriceSearchService
        // Update item with found prices
        // Create vendor price entries
    }
}
```

### SearchController

```php
// app/Http/Controllers/SearchController.php
class SearchController extends Controller
{
    public function index(Request $request): Response;
    public function search(Request $request): Response;        // Text search
    public function imageSearch(Request $request): Response;   // Image search
}
```

## Services

### AIService

Single AI provider service:

```php
// app/Services/AI/AIService.php
class AIService
{
    public static function forUser(int $userId): ?self;
    public function isAvailable(): bool;
    public function complete(string $prompt, array $options = []): string;
    public function analyzeImage(string $base64Image, string $mimeType, string $prompt): string;
    public function testConnection(): array;
}
```

### MultiAIService

Multi-provider aggregation service:

```php
// app/Services/AI/MultiAIService.php
class MultiAIService
{
    public static function forUser(int $userId): self;
    public function isAvailable(): bool;
    public function getProviderCount(): int;
    public function processWithAllProviders(string $prompt, array $options = []): array;
    public function analyzeImageWithAllProviders(string $base64Image, string $mimeType, string $prompt): array;
}
```

### PriceApiService

Price lookup service:

```php
// app/Services/PriceApi/PriceApiService.php
class PriceApiService
{
    public static function forUser(?int $userId): self;
    public function isAvailable(): bool;
    public function search(string $query, string $type = 'product'): PriceSearchResult;
    public function testConnection(): bool;
}
```

## Policies

### ShoppingListPolicy

```php
// app/Policies/ShoppingListPolicy.php
class ShoppingListPolicy
{
    public function view(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function update(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function delete(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }
}
```

### ListItemPolicy

```php
// app/Policies/ListItemPolicy.php
class ListItemPolicy
{
    public function view(User $user, ListItem $item): bool
    {
        return $user->id === $item->shoppingList->user_id;
    }

    public function update(User $user, ListItem $item): bool
    {
        return $user->id === $item->shoppingList->user_id;
    }
}
```

## Routes

### Web Routes (routes/web.php)

```php
// Authentication
Route::get('login', [AuthController::class, 'showLogin']);
Route::post('login', [AuthController::class, 'login']);
Route::get('register', [AuthController::class, 'showRegister']);
Route::post('register', [AuthController::class, 'register']);
Route::post('logout', [AuthController::class, 'logout']);

// Protected routes
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Smart Add - Product Identification Flow
    // Phase 1: Identify products (via image or text)
    // Phase 2: Add to list (price search runs as background job)
    Route::get('smart-add', [SmartAddController::class, 'index']);
    Route::post('smart-add/identify', [SmartAddController::class, 'identify']);
    Route::post('smart-add/add', [SmartAddController::class, 'addToList']);
    // Legacy endpoints (kept for backward compatibility)
    Route::post('smart-add/analyze', [SmartAddController::class, 'analyzeImage']);
    Route::post('smart-add/search', [SmartAddController::class, 'searchText']);

    // Shopping Lists
    Route::resource('lists', ShoppingListController::class);
    Route::post('lists/{list}/refresh', [ShoppingListController::class, 'refresh']);

    // List Items
    Route::post('lists/{list}/items', [ListItemController::class, 'store']);
    Route::get('items/{item}', [ListItemController::class, 'show']);
    Route::patch('items/{item}', [ListItemController::class, 'update']);
    Route::delete('items/{item}', [ListItemController::class, 'destroy']);
    Route::post('items/{item}/refresh', [ListItemController::class, 'refresh']);
    Route::post('items/{item}/purchased', [ListItemController::class, 'markPurchased']);

    // Search
    Route::get('search', [SearchController::class, 'index']);
    Route::post('search', [SearchController::class, 'search']);
    Route::post('search/image', [SearchController::class, 'imageSearch']);

    // Settings
    Route::get('settings', [SettingController::class, 'index']);
    Route::patch('settings', [SettingController::class, 'update']);
});
```

## Database Migrations

### Key Tables

| Table | Description |
|-------|-------------|
| users | User accounts |
| shopping_lists | User's shopping lists |
| list_items | Items in shopping lists |
| item_vendor_prices | Price data per vendor |
| price_histories | Historical price records |
| ai_providers | User's AI provider configurations |
| settings | User settings (key-value) |
| search_histories | User search history |

### Example Migration

```php
// database/migrations/2024_01_01_000002_create_shopping_lists_table.php
Schema::create('shopping_lists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('notify_on_any_drop')->default(false);
    $table->boolean('notify_on_threshold')->default(false);
    $table->integer('threshold_percent')->nullable();
    $table->timestamps();
});
```

## Validation

### Request Validation

```php
// In controller
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:1000'],
]);

$list = ShoppingList::create([
    'user_id' => $request->user()->id,
    ...$validated,
]);
```

## Configuration

### Environment Variables

| Variable | Description |
|----------|-------------|
| APP_KEY | Encryption key |
| APP_URL | Application URL |
| DB_CONNECTION | Database driver (sqlite) |
| DB_DATABASE | Database file path |

### Config Files

| File | Purpose |
|------|---------|
| config/app.php | Application settings |
| config/auth.php | Authentication config |
| config/database.php | Database connections |
| config/sanctum.php | Sanctum settings |
| config/services.php | Third-party services |

## Artisan Commands

```bash
# Run migrations
php artisan migrate

# Create new migration
php artisan make:migration create_table_name_table

# Create model with migration
php artisan make:model ModelName -m

# Create controller
php artisan make:controller ControllerName

# Create policy
php artisan make:policy PolicyName --model=ModelName

# Clear caches
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/ShoppingListTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

See [DOCUMENTATION_TESTING.md](DOCUMENTATION_TESTING.md) for complete testing guide.
