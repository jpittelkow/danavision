<?php

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use App\Services\Crawler\FirecrawlResult;
use App\Services\Crawler\StoreDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed some default stores for testing
    Store::create([
        'name' => 'Amazon',
        'slug' => 'amazon',
        'domain' => 'amazon.com',
        'search_url_template' => 'https://www.amazon.com/s?k={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 100,
    ]);

    Store::create([
        'name' => 'Walmart',
        'slug' => 'walmart',
        'domain' => 'walmart.com',
        'search_url_template' => 'https://www.walmart.com/search?q={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 95,
    ]);

    Store::create([
        'name' => 'Target',
        'slug' => 'target',
        'domain' => 'target.com',
        'search_url_template' => 'https://www.target.com/s?searchTerm={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 90,
    ]);
});

test('store discovery service can be created for user', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    $service = StoreDiscoveryService::forUser($user->id);

    expect($service)->toBeInstanceOf(StoreDiscoveryService::class);
    expect($service->isAvailable())->toBeTrue();
});

test('store discovery service is not available without api key', function () {
    $user = User::factory()->create();

    $service = StoreDiscoveryService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
});

test('store discovery service uses store url templates', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    // Mock the scrape batch API call
    Http::fake([
        'api.firecrawl.dev/v1/batch/scrape' => Http::response([
            'success' => true,
            'data' => [
                [
                    'url' => 'https://www.amazon.com/s?k=test+product',
                    'json' => [
                        'store_name' => 'Amazon',
                        'item_name' => 'Test Product',
                        'price' => 29.99,
                        'stock_status' => 'in_stock',
                    ],
                ],
                [
                    'url' => 'https://www.walmart.com/search?q=test+product',
                    'json' => [
                        'store_name' => 'Walmart',
                        'item_name' => 'Test Product',
                        'price' => 27.99,
                        'stock_status' => 'in_stock',
                    ],
                ],
            ],
        ], 200),
        // Fallback for individual scrapes
        'api.firecrawl.dev/v1/scrape' => Http::response([
            'success' => true,
            'data' => [
                'json' => [
                    'price' => 25.99,
                    'stock_status' => 'in_stock',
                ],
            ],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $result = $service->discoverPrices('test product', ['skip_discovery' => true]);

    expect($result)->toBeInstanceOf(FirecrawlResult::class);
    // Should have used batch scrape
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'batch/scrape') ||
               str_contains($request->url(), 'scrape');
    });
});

test('store discovery service returns results from user enabled stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    // Disable Walmart for this user
    $walmart = Store::where('slug', 'walmart')->first();
    UserStorePreference::create([
        'user_id' => $user->id,
        'store_id' => $walmart->id,
        'enabled' => false,
        'priority' => 0,
    ]);

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'success' => true,
            'data' => [],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $result = $service->discoverPrices('test product', ['skip_discovery' => true]);

    // The request should not include walmart
    Http::assertSent(function ($request) {
        $body = $request->body();
        return !str_contains($body, 'walmart.com');
    });
});

test('store discovery service prioritizes favorite stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    // Make Target a favorite with high priority
    $target = Store::where('slug', 'target')->first();
    UserStorePreference::create([
        'user_id' => $user->id,
        'store_id' => $target->id,
        'enabled' => true,
        'is_favorite' => true,
        'priority' => 200,
    ]);

    $stores = UserStorePreference::getAllStoresForUser($user->id);
    $firstStore = $stores->first();

    // Target should be first due to favorite + high priority
    expect($firstStore->slug)->toBe('target');
});

test('store discovery service merges tier 2 results when needed', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    // Return few results from tier 1 to trigger tier 2
    Http::fake([
        'api.firecrawl.dev/v1/batch/scrape' => Http::response([
            'success' => true,
            'data' => [
                [
                    'url' => 'https://www.amazon.com/s?k=test',
                    'json' => [
                        'price' => 29.99,
                        'store_name' => 'Amazon',
                    ],
                ],
            ],
        ], 200),
        'api.firecrawl.dev/v1/scrape' => Http::response([
            'success' => true,
            'data' => [
                'json' => [
                    'price' => 25.99,
                    'store_name' => 'Test Store',
                ],
            ],
        ], 200),
        'api.firecrawl.dev/v1/search' => Http::response([
            'success' => true,
            'data' => [
                [
                    'url' => 'https://newstore.com/product',
                    'title' => 'Test Product',
                    'json' => [
                        'price' => 24.99,
                        'store_name' => 'New Store',
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $service->setMinResultsThreshold(5); // Set high threshold to trigger tier 2
    $result = $service->discoverPrices('test product');

    // Should have called search API for tier 2
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'search');
    });
});

test('store can generate search url from template', function () {
    $store = Store::where('slug', 'amazon')->first();

    $url = $store->generateSearchUrl('sony headphones');

    expect($url)->toBe('https://www.amazon.com/s?k=sony+headphones');
});

test('store can match url to domain', function () {
    $store = Store::where('slug', 'amazon')->first();

    expect($store->matchesUrl('https://www.amazon.com/dp/B09XS7JWHH'))->toBeTrue();
    expect($store->matchesUrl('https://amazon.com/product/123'))->toBeTrue();
    expect($store->matchesUrl('https://walmart.com/item/456'))->toBeFalse();
});

test('store can be found by domain', function () {
    $store = Store::findByDomain('amazon.com');

    expect($store)->not->toBeNull();
    expect($store->name)->toBe('Amazon');
});

test('store can be found by url', function () {
    $store = Store::findByUrl('https://www.amazon.com/dp/B09XS7JWHH');

    expect($store)->not->toBeNull();
    expect($store->name)->toBe('Amazon');
});

test('store is enabled for user by default', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    expect($store->isEnabledForUser($user->id))->toBeTrue();
});

test('user store preference can disable store', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    UserStorePreference::setPreference($user->id, $store->id, ['enabled' => false]);

    expect($store->isEnabledForUser($user->id))->toBeFalse();
});

test('user store preference can be toggled as favorite', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    // Initially not favorite
    $preference = UserStorePreference::toggleFavorite($user->id, $store->id);
    expect($preference->is_favorite)->toBeTrue();

    // Toggle again
    $preference = UserStorePreference::toggleFavorite($user->id, $store->id);
    expect($preference->is_favorite)->toBeFalse();
});

test('user store preferences can be updated in bulk', function () {
    $user = User::factory()->create();
    $amazon = Store::where('slug', 'amazon')->first();
    $walmart = Store::where('slug', 'walmart')->first();
    $target = Store::where('slug', 'target')->first();

    // Set order: Target, Walmart, Amazon
    UserStorePreference::updatePriorities($user->id, [
        $target->id,
        $walmart->id,
        $amazon->id,
    ]);

    $amazonPref = UserStorePreference::where('user_id', $user->id)
        ->where('store_id', $amazon->id)
        ->first();
    $targetPref = UserStorePreference::where('user_id', $user->id)
        ->where('store_id', $target->id)
        ->first();

    // Target should have higher priority than Amazon
    expect($targetPref->priority)->toBeGreaterThan($amazonPref->priority);
});

test('store discovery service gets correct store stats', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    // Add a preference
    $amazon = Store::where('slug', 'amazon')->first();
    UserStorePreference::setPreference($user->id, $amazon->id, ['is_favorite' => true]);

    $service = StoreDiscoveryService::forUser($user->id);
    $stats = $service->getStoreStats();

    expect($stats)->toHaveKeys([
        'total_stores',
        'active_stores',
        'default_stores',
        'stores_with_templates',
        'user_preferences',
    ]);
    expect($stats['total_stores'])->toBe(3);
    expect($stats['stores_with_templates'])->toBe(3);
    expect($stats['user_preferences'])->toBe(1);
});

test('store scopes work correctly', function () {
    // Add an inactive store
    Store::create([
        'name' => 'Inactive Store',
        'slug' => 'inactive',
        'domain' => 'inactive.com',
        'is_default' => false,
        'is_active' => false,
        'is_local' => true,
        'category' => 'grocery',
    ]);

    expect(Store::active()->count())->toBe(3);
    expect(Store::default()->count())->toBe(3);
    expect(Store::local()->count())->toBe(1);
    expect(Store::category('general')->count())->toBe(3);
    expect(Store::category('grocery')->count())->toBe(1);
});
