<?php

use App\Models\AIProvider;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use App\Services\Crawler\CrawlResult;
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

/**
 * Helper to set up a user with AI provider configured.
 */
function setupUserWithAI(): User
{
    $user = User::factory()->create();
    
    // Create an AI provider for the user
    AIProvider::create([
        'user_id' => $user->id,
        'provider' => AIProvider::PROVIDER_OPENAI,
        'api_key' => encrypt('test-api-key'),
        'model' => 'gpt-4o-mini',
        'is_active' => true,
        'is_primary' => true,
    ]);
    
    return $user;
}

test('store discovery service can be created for user', function () {
    $user = setupUserWithAI();

    // Mock Crawl4AI health check
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);

    expect($service)->toBeInstanceOf(StoreDiscoveryService::class);
    expect($service->isAvailable())->toBeTrue();
});

test('store discovery service is not available without ai provider', function () {
    $user = User::factory()->create();

    // Mock Crawl4AI health check
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);

    // Not available because no AI provider configured
    expect($service->isAvailable())->toBeFalse();
});

test('store discovery service is not available when crawl4ai is down', function () {
    $user = setupUserWithAI();

    // Mock Crawl4AI health check failure
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(null, 500),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
});

test('store discovery service uses crawl4ai for scraping', function () {
    $user = setupUserWithAI();

    // Mock Crawl4AI and AI API
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
        '127.0.0.1:5000/batch' => Http::response([
            'results' => [
                [
                    'success' => true,
                    'markdown' => '# Amazon Search Results\n\nTest Product - $29.99 - In Stock',
                ],
                [
                    'success' => true,
                    'markdown' => '# Walmart Search Results\n\nTest Product - $27.99 - In Stock',
                ],
                [
                    'success' => true,
                    'markdown' => '# Target Search Results\n\nTest Product - $28.99 - In Stock',
                ],
            ],
        ], 200),
        // Mock OpenAI API for price extraction
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"item_name": "Test Product", "price": 29.99, "stock_status": "in_stock"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $result = $service->discoverPrices('test product', ['skip_discovery' => true]);

    expect($result)->toBeInstanceOf(CrawlResult::class);
    
    // Should have called Crawl4AI batch endpoint
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '127.0.0.1:5000/batch');
    });
});

test('store discovery service returns results from user enabled stores', function () {
    $user = setupUserWithAI();

    // Disable Walmart for this user
    $walmart = Store::where('slug', 'walmart')->first();
    UserStorePreference::create([
        'user_id' => $user->id,
        'store_id' => $walmart->id,
        'enabled' => false,
        'priority' => 0,
    ]);

    Http::fake([
        '127.0.0.1:5000/*' => Http::response([
            'results' => [],
        ], 200),
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"price": null}']]],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $result = $service->discoverPrices('test product', ['skip_discovery' => true]);

    // Verify walmart was not in the batch request
    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), 'batch')) {
            return true;
        }
        $body = json_decode($request->body(), true);
        $urls = $body['urls'] ?? [];
        foreach ($urls as $url) {
            if (str_contains($url, 'walmart.com')) {
                return false;
            }
        }
        return true;
    });
});

test('store discovery service prioritizes favorite stores', function () {
    $user = setupUserWithAI();

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

test('store discovery service triggers tier 2 when few results', function () {
    $user = setupUserWithAI();

    // Tier 1: 3 stores, all fail -> triggers tier 2
    // Tier 2 search: 4 retailers, 1 success with markdown
    // Tier 2 product: 1 product page, success
    // AI: 1st = product URLs, 2nd = price from product page
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
        '127.0.0.1:5000/batch' => Http::sequence()
            ->push(Http::response([
                'results' => [
                    ['success' => false, 'error' => 'Page not found'],
                    ['success' => false, 'error' => 'Page not found'],
                    ['success' => false, 'error' => 'Page not found'],
                ],
            ], 200))
            ->push(Http::response([
                'results' => [
                    ['success' => true, 'markdown' => 'Amazon search results. Product: Test Product link /dp/ABC123'],
                    ['success' => false, 'error' => 'Timeout'],
                    ['success' => false, 'error' => 'Timeout'],
                    ['success' => false, 'error' => 'Timeout'],
                ],
            ], 200))
            ->push(Http::response([
                'results' => [
                    ['success' => true, 'markdown' => 'Test Product - $24.99 - In Stock'],
                ],
            ], 200)),
        'api.openai.com/*' => Http::sequence()
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"products":[{"name":"Test Product","url":"https://www.amazon.com/dp/ABC123"}]}']]],
            ], 200))
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"item_name":"Test Product","price":24.99,"stock_status":"in_stock"}']]],
            ], 200)),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $service->setMinResultsThreshold(5);
    $result = $service->discoverPrices('test product');

    expect($result->hasResults())->toBeTrue();
    expect($result->results[0]['product_url'] ?? null)->toBe('https://www.amazon.com/dp/ABC123');
    Http::assertSent(fn ($r) => str_contains($r->url(), '127.0.0.1:5000/batch'));
});

test('tier 2 two-step flow always sets product_url for refresh', function () {
    $user = setupUserWithAI();

    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
        '127.0.0.1:5000/batch' => Http::sequence()
            ->push(Http::response(['results' => [['success' => false], ['success' => false], ['success' => false]]], 200))
            ->push(Http::response([
                'results' => [
                    ['success' => true, 'markdown' => 'Target search. Item /p/12345'],
                    ['success' => false], ['success' => false], ['success' => false],
                ],
            ], 200))
            ->push(Http::response([
                'results' => [['success' => true, 'markdown' => 'Product page $99.99']],
            ], 200)),
        'api.openai.com/*' => Http::sequence()
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"products":[{"name":"Item","url":"https://www.target.com/p/12345"}]}']]],
            ], 200))
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"item_name":"Item","price":99.99,"stock_status":"in_stock"}']]],
            ], 200)),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $service->setMinResultsThreshold(10);
    $result = $service->discoverPrices('test');

    expect($result->hasResults())->toBeTrue();
    expect($result->results[0])->toHaveKey('product_url');
    expect($result->results[0]['product_url'])->toBe('https://www.target.com/p/12345');
});

test('tier 2 returns empty when no product URLs extracted from search', function () {
    $user = setupUserWithAI();

    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
        '127.0.0.1:5000/batch' => Http::sequence()
            ->push(Http::response(['results' => [['success' => false], ['success' => false], ['success' => false]]], 200))
            ->push(Http::response([
                'results' => [
                    ['success' => true, 'markdown' => 'No relevant products here.'],
                    ['success' => true, 'markdown' => 'Also nothing.'],
                    ['success' => false], ['success' => false],
                ],
            ], 200)),
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"products":[]}']]],
        ], 200),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $service->setMinResultsThreshold(10);
    $result = $service->discoverPrices('obscure product');

    expect($result->hasResults())->toBeFalse();
    expect($result->results)->toHaveCount(0);
});

test('resolveProductUrl makes relative product URLs absolute', function () {
    $user = setupUserWithAI();

    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
        '127.0.0.1:5000/batch' => Http::sequence()
            ->push(Http::response(['results' => [['success' => false], ['success' => false], ['success' => false]]], 200))
            ->push(Http::response([
                'results' => [
                    ['success' => true, 'markdown' => 'Amazon /dp/B0REL'],
                    ['success' => false], ['success' => false], ['success' => false],
                ],
            ], 200))
            ->push(Http::response([
                'results' => [['success' => true, 'markdown' => 'Price $49.00']],
            ], 200)),
        'api.openai.com/*' => Http::sequence()
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"products":[{"name":"X","url":"/dp/B0REL"}]}']]],
            ], 200))
            ->push(Http::response([
                'choices' => [['message' => ['content' => '{"item_name":"X","price":49,"stock_status":"in_stock"}']]],
            ], 200)),
    ]);

    $service = StoreDiscoveryService::forUser($user->id);
    $service->setMinResultsThreshold(10);
    $result = $service->discoverPrices('x');

    expect($result->hasResults())->toBeTrue();
    expect($result->results[0]['product_url'])->toBe('https://www.amazon.com/dp/B0REL');
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
    $user = setupUserWithAI();

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
