<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Search\WebSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('web search service can be created for user', function () {
    $user = User::factory()->create();

    $service = WebSearchService::forUser($user->id);

    expect($service)->toBeInstanceOf(WebSearchService::class);
});

test('web search service is not available without api key', function () {
    $user = User::factory()->create();

    $service = WebSearchService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
});

test('web search service is available with serpapi key', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    $service = WebSearchService::forUser($user->id);

    expect($service->isAvailable())->toBeTrue();
});

test('web search service returns empty array when not available', function () {
    $user = User::factory()->create();

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test product');

    expect($results)->toBeEmpty();
});

test('web search service can search prices with mocked api', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response([
            'shopping_results' => [
                [
                    'title' => 'Test Product',
                    'price' => '$29.99',
                    'extracted_price' => 29.99,
                    'source' => 'Amazon',
                    'link' => 'https://amazon.com/test',
                    'thumbnail' => 'https://amazon.com/img.jpg',
                ],
                [
                    'title' => 'Test Product 2',
                    'price' => '$34.99',
                    'extracted_price' => 34.99,
                    'source' => 'Walmart',
                    'link' => 'https://walmart.com/test',
                ],
            ],
        ], 200),
    ]);

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test product');

    expect($results)->toHaveCount(2);
    expect($results[0]['title'])->toBe('Test Product');
    expect($results[0]['price'])->toBe(29.99);
    expect($results[0]['retailer'])->toBe('Amazon');
    expect($results[0]['source'])->toBe('serpapi_shopping');
});

test('web search service handles api errors gracefully', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response([], 500),
    ]);

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test product');

    expect($results)->toBeEmpty();
});

test('web search service sorts results by price', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response([
            'shopping_results' => [
                ['title' => 'Expensive', 'extracted_price' => 99.99, 'source' => 'Store A', 'link' => 'https://a.com'],
                ['title' => 'Cheap', 'extracted_price' => 19.99, 'source' => 'Store B', 'link' => 'https://b.com'],
                ['title' => 'Medium', 'extracted_price' => 49.99, 'source' => 'Store C', 'link' => 'https://c.com'],
            ],
        ], 200),
    ]);

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test product');

    expect($results)->toHaveCount(3);
    expect($results[0]['price'])->toBe(19.99);
    expect($results[1]['price'])->toBe(49.99);
    expect($results[2]['price'])->toBe(99.99);
});

test('web search service includes location for local searches', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response(['shopping_results' => []], 200),
    ]);

    $service = WebSearchService::forUser($user->id);
    $service->searchPrices('test product', [
        'shop_local' => true,
        'zip_code' => '90210',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'location=90210');
    });
});

test('web search service searches local stores', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    // When Google Maps API fails, it falls back to common stores
    Http::fake([
        'serpapi.com/*' => Http::response([], 500),
    ]);

    $service = WebSearchService::forUser($user->id);
    $stores = $service->searchLocalStores('90210', 'grocery');

    // Should get fallback common stores
    expect($stores)->not->toBeEmpty();
    expect(collect($stores)->pluck('store_name'))->toContain('Walmart');
    expect(collect($stores)->pluck('store_name'))->toContain('Target');
});

test('web search service caches price results', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    $callCount = 0;
    Http::fake(function () use (&$callCount) {
        $callCount++;
        return Http::response([
            'shopping_results' => [
                ['title' => 'Test', 'extracted_price' => 29.99, 'source' => 'Store', 'link' => 'https://test.com'],
            ],
        ], 200);
    });

    $service = WebSearchService::forUser($user->id);
    
    // First call
    $results1 = $service->searchPricesWithCache('test product');
    // Second call should use cache
    $results2 = $service->searchPricesWithCache('test product');

    // API should only be called once
    expect($callCount)->toBe(1);
    expect($results1)->toEqual($results2);
});

test('web search service parses various price formats', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response([
            'shopping_results' => [
                ['title' => 'Test 1', 'price' => '$29.99', 'extracted_price' => 29.99, 'source' => 'Store', 'link' => 'https://test.com/1'],
                ['title' => 'Test 2', 'price' => '29.99 USD', 'source' => 'Store', 'link' => 'https://test.com/2'],
                ['title' => 'Test 3', 'price' => 'USD $29.99', 'source' => 'Store', 'link' => 'https://test.com/3'],
            ],
        ], 200),
    ]);

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test');

    // All should parse to 29.99
    foreach ($results as $result) {
        expect($result['price'])->toBe(29.99);
    }
});

test('web search service skips items with zero or missing prices', function () {
    $user = User::factory()->create();
    Setting::set(Setting::SERPAPI_KEY, 'test-api-key', $user->id);

    Http::fake([
        'serpapi.com/*' => Http::response([
            'shopping_results' => [
                ['title' => 'Valid', 'extracted_price' => 29.99, 'source' => 'Store', 'link' => 'https://test.com/1'],
                ['title' => 'Zero Price', 'extracted_price' => 0, 'source' => 'Store', 'link' => 'https://test.com/2'],
                ['title' => 'No Price', 'source' => 'Store', 'link' => 'https://test.com/3'],
            ],
        ], 200),
    ]);

    $service = WebSearchService::forUser($user->id);
    $results = $service->searchPrices('test');

    expect($results)->toHaveCount(1);
    expect($results[0]['title'])->toBe('Valid');
});
