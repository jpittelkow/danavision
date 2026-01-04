<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Crawler\FirecrawlResult;
use App\Services\Crawler\FirecrawlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('firecrawl service can be created for user', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);

    $service = FirecrawlService::forUser($user->id);

    expect($service)->toBeInstanceOf(FirecrawlService::class);
    expect($service->isAvailable())->toBeTrue();
});

test('firecrawl service is not available without api key', function () {
    $user = User::factory()->create();

    $service = FirecrawlService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
});

test('firecrawl result has correct structure', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            [
                'store_name' => 'Amazon',
                'item' => 'Test Product',
                'price' => 29.99,
                'stock_status' => 'in_stock',
                'unit_of_measure' => 'each',
                'product_url' => 'https://amazon.com/test',
            ],
        ],
        source: 'initial_discovery',
        error: null,
        metadata: ['total_results' => 1],
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->hasError())->toBeFalse();
    expect($result->hasResults())->toBeTrue();
    expect($result->count())->toBe(1);
    expect($result->getLowestPrice())->toBe(29.99);
    expect($result->source)->toBe('initial_discovery');
});

test('firecrawl result can get lowest price', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            ['store_name' => 'Store A', 'price' => 49.99],
            ['store_name' => 'Store B', 'price' => 29.99],
            ['store_name' => 'Store C', 'price' => 39.99],
        ],
        source: 'discovery',
    );

    expect($result->getLowestPrice())->toBe(29.99);
});

test('firecrawl result can get highest price', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            ['store_name' => 'Store A', 'price' => 49.99],
            ['store_name' => 'Store B', 'price' => 29.99],
            ['store_name' => 'Store C', 'price' => 39.99],
        ],
        source: 'discovery',
    );

    expect($result->getHighestPrice())->toBe(49.99);
});

test('firecrawl result can get best deal', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            ['store_name' => 'Store A', 'price' => 49.99, 'stock_status' => 'in_stock'],
            ['store_name' => 'Store B', 'price' => 29.99, 'stock_status' => 'in_stock'],
            ['store_name' => 'Store C', 'price' => 39.99, 'stock_status' => 'out_of_stock'],
        ],
        source: 'discovery',
    );

    $bestDeal = $result->getBestDeal();
    
    expect($bestDeal)->not->toBeNull();
    expect($bestDeal['store_name'])->toBe('Store B');
    expect($bestDeal['price'])->toBe(29.99);
});

test('firecrawl result can filter in stock items', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            ['store_name' => 'Store A', 'price' => 49.99, 'stock_status' => 'in_stock'],
            ['store_name' => 'Store B', 'price' => 29.99, 'stock_status' => 'out_of_stock'],
            ['store_name' => 'Store C', 'price' => 39.99, 'stock_status' => 'in_stock'],
        ],
        source: 'discovery',
    );

    $inStock = $result->getInStockResults();
    
    expect($inStock)->toHaveCount(2);
});

test('firecrawl result serializes to array correctly', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [
            ['store_name' => 'Amazon', 'price' => 29.99],
        ],
        source: 'initial_discovery',
        error: null,
        metadata: ['key' => 'value'],
    );

    $array = $result->toArray();

    expect($array)->toHaveKeys([
        'success',
        'results',
        'source',
        'error',
        'metadata',
        'count',
        'lowest_price',
        'highest_price',
    ]);
    expect($array['success'])->toBeTrue();
    expect($array['count'])->toBe(1);
});

test('firecrawl result handles empty results', function () {
    $result = new FirecrawlResult(
        success: true,
        results: [],
        source: 'discovery',
    );

    expect($result->hasResults())->toBeFalse();
    expect($result->count())->toBe(0);
    expect($result->getLowestPrice())->toBeNull();
    expect($result->getHighestPrice())->toBeNull();
    expect($result->getBestDeal())->toBeNull();
});

test('firecrawl result handles error state', function () {
    $result = new FirecrawlResult(
        success: false,
        results: [],
        source: 'discovery',
        error: 'API rate limit exceeded',
    );

    expect($result->isSuccess())->toBeFalse();
    expect($result->hasError())->toBeTrue();
    expect($result->error)->toBe('API rate limit exceeded');
});

test('firecrawl service builds discovery prompt correctly', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    $service = FirecrawlService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildDiscoveryPrompt');
    $method->setAccessible(true);
    
    $prompt = $method->invoke($service, 'Sony WH-1000XM5', []);
    
    expect($prompt)->toContain('Sony WH-1000XM5');
    expect($prompt)->toContain('current prices');
    expect($prompt)->toContain('stock');
});

test('firecrawl service builds local search prompt when shop_local enabled', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    Setting::set(Setting::HOME_ADDRESS, '123 Main St, Beverly Hills, CA 90210', $user->id);
    
    $service = FirecrawlService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildDiscoveryPrompt');
    $method->setAccessible(true);
    
    $prompt = $method->invoke($service, 'milk', ['shop_local' => true]);
    
    expect($prompt)->toContain('local');
    expect($prompt)->toContain('123 Main St');
});

test('firecrawl service normalizes stock status', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    $service = FirecrawlService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeStockStatus');
    $method->setAccessible(true);
    
    expect($method->invoke($service, 'in stock'))->toBe('in_stock');
    expect($method->invoke($service, 'In Stock'))->toBe('in_stock');
    expect($method->invoke($service, 'available'))->toBe('in_stock');
    expect($method->invoke($service, 'out of stock'))->toBe('out_of_stock');
    expect($method->invoke($service, 'sold out'))->toBe('out_of_stock');
    expect($method->invoke($service, 'limited'))->toBe('limited_stock');
    expect($method->invoke($service, 'low stock'))->toBe('limited_stock');
    expect($method->invoke($service, 'few left'))->toBe('limited_stock');
});

test('firecrawl service extracts store name from url', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    $service = FirecrawlService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('extractStoreFromUrl');
    $method->setAccessible(true);
    
    expect($method->invoke($service, 'https://www.amazon.com/product/123'))->toBe('Amazon');
    expect($method->invoke($service, 'https://walmart.com/item/456'))->toBe('Walmart');
    expect($method->invoke($service, 'https://www.bestbuy.com/site/789'))->toBe('Best Buy');
});

test('firecrawl service makes discovery api call with correct structure', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'store_name' => 'Amazon',
                    'item' => 'Test Product',
                    'price' => 29.99,
                    'stock_status' => 'in_stock',
                    'unit_of_measure' => 'each',
                    'product_url' => 'https://amazon.com/test',
                ],
            ],
        ], 200),
    ]);
    
    $service = FirecrawlService::forUser($user->id);
    $result = $service->discoverProductPrices('Test Product');
    
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'firecrawl.dev') &&
               $request->hasHeader('Authorization');
    });
    
    expect($result)->toBeInstanceOf(FirecrawlResult::class);
});

test('firecrawl service handles api error gracefully', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'success' => false,
            'error' => 'Rate limit exceeded',
        ], 429),
    ]);
    
    $service = FirecrawlService::forUser($user->id);
    $result = $service->discoverProductPrices('Test Product');
    
    expect($result->isSuccess())->toBeFalse();
    expect($result->hasError())->toBeTrue();
});

test('firecrawl service scrapes urls with correct structure', function () {
    $user = User::factory()->create();
    Setting::set(Setting::FIRECRAWL_API_KEY, 'fc-test-key', $user->id);
    
    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'price' => 25.99,
                    'stock_status' => 'in_stock',
                    'product_url' => 'https://amazon.com/test',
                ],
            ],
        ], 200),
    ]);
    
    $service = FirecrawlService::forUser($user->id);
    $result = $service->scrapeProductUrls(['https://amazon.com/test', 'https://walmart.com/item']);
    
    expect($result)->toBeInstanceOf(FirecrawlResult::class);
});
