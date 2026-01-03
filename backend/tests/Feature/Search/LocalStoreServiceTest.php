<?php

use App\Models\LocalStoreCache;
use App\Models\Setting;
use App\Models\User;
use App\Services\Search\LocalStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('local store service can be created for user', function () {
    $user = User::factory()->create();

    $service = LocalStoreService::forUser($user->id);

    expect($service)->toBeInstanceOf(LocalStoreService::class);
});

test('local store service is not available without zip code', function () {
    $user = User::factory()->create();

    $service = LocalStoreService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
});

test('local store service is available with zip code', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    $service = LocalStoreService::forUser($user->id);

    expect($service->isAvailable())->toBeTrue();
    expect($service->getHomeZipCode())->toBe('90210');
});

test('local store service returns empty array without zip code', function () {
    $user = User::factory()->create();

    $service = LocalStoreService::forUser($user->id);
    $stores = $service->getLocalStores();

    expect($stores)->toBeEmpty();
});

test('local store service discovers and caches stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    // Mock web search to return empty (will use fallback stores)
    Http::fake([
        'serpapi.com/*' => Http::response([], 500),
    ]);

    $service = LocalStoreService::forUser($user->id);
    $stores = $service->discoverLocalStores();

    // Should have fallback stores
    expect($stores)->not->toBeEmpty();

    // Should be cached in database
    $cachedCount = LocalStoreCache::where('user_id', $user->id)
        ->where('zip_code', '90210')
        ->count();
    expect($cachedCount)->toBeGreaterThan(0);
});

test('local store service returns cached stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    // Pre-populate cache
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Cached Store',
        'store_type' => 'supermarket',
        'address' => '123 Main St',
        'discovered_at' => now(),
    ]);

    $service = LocalStoreService::forUser($user->id);
    $stores = $service->getLocalStores();

    expect($stores)->toHaveCount(1);
    expect($stores[0]['store_name'])->toBe('Cached Store');
});

test('local store service returns grocery stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    // Pre-populate cache with different store types
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Grocery Store',
        'store_type' => 'supermarket',
        'discovered_at' => now(),
    ]);
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Electronics Store',
        'store_type' => 'electronics',
        'discovered_at' => now(),
    ]);

    $service = LocalStoreService::forUser($user->id);
    $groceryStores = $service->getLocalGroceryStores();

    expect($groceryStores)->toHaveCount(1);
    expect($groceryStores[0]['store_name'])->toBe('Grocery Store');
});

test('local store service gets store names for search queries', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    // Pre-populate cache
    for ($i = 1; $i <= 10; $i++) {
        LocalStoreCache::create([
            'user_id' => $user->id,
            'zip_code' => '90210',
            'store_name' => "Store {$i}",
            'store_type' => 'supermarket',
            'discovered_at' => now(),
        ]);
    }

    $service = LocalStoreService::forUser($user->id);
    $names = $service->getLocalStoreNames(limit: 5);

    expect($names)->toHaveCount(5);
});

test('local store service can clear cache', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    // Pre-populate cache
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Test Store',
        'store_type' => 'supermarket',
        'discovered_at' => now(),
    ]);

    $service = LocalStoreService::forUser($user->id);
    $service->clearCache();

    $count = LocalStoreCache::where('user_id', $user->id)->count();
    expect($count)->toBe(0);
});

test('local store service can refresh cache', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    // Pre-populate cache with old data
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Old Store',
        'store_type' => 'supermarket',
        'discovered_at' => now()->subDays(30), // Old cache
    ]);

    // Mock web search to return empty (will use fallback stores)
    Http::fake([
        'serpapi.com/*' => Http::response([], 500),
    ]);

    $service = LocalStoreService::forUser($user->id);
    $stores = $service->refreshStoreCache();

    // Old cache should be replaced
    $oldStore = LocalStoreCache::where('user_id', $user->id)
        ->where('store_name', 'Old Store')
        ->first();
    expect($oldStore)->toBeNull();
});

test('local store service returns stores for product type', function () {
    $user = User::factory()->create();
    Setting::set(Setting::HOME_ZIP_CODE, '90210', $user->id);

    // Pre-populate cache with different store types
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Supermarket',
        'store_type' => 'supermarket',
        'discovered_at' => now(),
    ]);
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Best Buy',
        'store_type' => 'electronics',
        'discovered_at' => now(),
    ]);
    LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'CVS',
        'store_type' => 'pharmacy',
        'discovered_at' => now(),
    ]);

    $service = LocalStoreService::forUser($user->id);

    $groceryStores = $service->getStoresForProductType('produce');
    expect(collect($groceryStores)->pluck('store_name'))->toContain('Supermarket');

    $electronicsStores = $service->getStoresForProductType('electronics');
    expect(collect($electronicsStores)->pluck('store_name'))->toContain('Best Buy');

    $pharmacyStores = $service->getStoresForProductType('pharmacy');
    expect(collect($pharmacyStores)->pluck('store_name'))->toContain('CVS');
});

test('local store cache model has correct fillable attributes', function () {
    $cache = new LocalStoreCache();
    
    expect($cache->getFillable())->toContain('user_id');
    expect($cache->getFillable())->toContain('zip_code');
    expect($cache->getFillable())->toContain('store_name');
    expect($cache->getFillable())->toContain('store_type');
    expect($cache->getFillable())->toContain('address');
    expect($cache->getFillable())->toContain('discovered_at');
});

test('local store cache model is stale after 7 days', function () {
    $user = User::factory()->create();
    
    $freshCache = LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Fresh Store',
        'store_type' => 'supermarket',
        'discovered_at' => now(),
    ]);

    $staleCache = LocalStoreCache::create([
        'user_id' => $user->id,
        'zip_code' => '90210',
        'store_name' => 'Stale Store',
        'store_type' => 'supermarket',
        'discovered_at' => now()->subDays(8),
    ]);

    expect($freshCache->isStale())->toBeFalse();
    expect($staleCache->isStale())->toBeTrue();
});
