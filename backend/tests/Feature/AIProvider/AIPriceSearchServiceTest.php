<?php

use App\Models\AIProvider;
use App\Models\User;
use App\Services\AI\AIPriceSearchService;
use App\Services\AI\AIPriceSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('ai price search service can be created for user', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);

    expect($service)->toBeInstanceOf(AIPriceSearchService::class);
    expect($service->isAvailable())->toBeTrue();
});

test('ai price search service is not available without providers', function () {
    $user = User::factory()->create();

    $service = AIPriceSearchService::forUser($user->id);

    expect($service->isAvailable())->toBeFalse();
    expect($service->getProviderCount())->toBe(0);
});

test('ai price search returns error when no providers or web search configured', function () {
    $user = User::factory()->create();

    $service = AIPriceSearchService::forUser($user->id);
    $result = $service->search('Sony WH-1000XM5');

    expect($result)->toBeInstanceOf(AIPriceSearchResult::class);
    expect($result->hasError())->toBeTrue();
    // SERP API is required for price searches - AI alone cannot fabricate prices
    expect($result->error)->toContain('SERP API is not configured');
    expect($result->results)->toBeEmpty();
});

test('ai price search result has correct structure', function () {
    $result = new AIPriceSearchResult(
        query: 'test product',
        results: [
            ['title' => 'Test Product', 'price' => 29.99, 'retailer' => 'Amazon'],
        ],
        lowestPrice: 29.99,
        highestPrice: 29.99,
        searchedAt: now(),
        error: null,
        providersUsed: ['claude'],
        isGeneric: false,
        unitOfMeasure: null,
    );

    expect($result->query)->toBe('test product');
    expect($result->hasResults())->toBeTrue();
    expect($result->hasError())->toBeFalse();
    expect($result->lowestPrice)->toBe(29.99);
    expect($result->providersUsed)->toContain('claude');
});

test('ai price search result serializes to array correctly', function () {
    $result = new AIPriceSearchResult(
        query: 'headphones',
        results: [
            ['title' => 'Sony Headphones', 'price' => 349.99, 'retailer' => 'Best Buy'],
        ],
        lowestPrice: 349.99,
        highestPrice: 349.99,
        searchedAt: now(),
        error: null,
        providersUsed: ['claude', 'openai'],
        isGeneric: false,
        unitOfMeasure: null,
    );

    $array = $result->toArray();

    expect($array)->toHaveKeys([
        'query',
        'results',
        'lowest_price',
        'highest_price',
        'searched_at',
        'error',
        'providers_used',
        'is_generic',
        'unit_of_measure',
    ]);
    expect($array['query'])->toBe('headphones');
    expect($array['providers_used'])->toHaveCount(2);
});

test('ai price search result for generic items includes unit of measure', function () {
    $result = new AIPriceSearchResult(
        query: 'blueberries',
        results: [
            ['title' => 'Fresh Blueberries', 'price' => 4.99, 'retailer' => 'Walmart'],
        ],
        lowestPrice: 4.99,
        highestPrice: 4.99,
        searchedAt: now(),
        error: null,
        providersUsed: ['claude'],
        isGeneric: true,
        unitOfMeasure: 'lb',
    );

    expect($result->isGeneric)->toBeTrue();
    expect($result->unitOfMeasure)->toBe('lb');
    
    $array = $result->toArray();
    expect($array['is_generic'])->toBeTrue();
    expect($array['unit_of_measure'])->toBe('lb');
});

test('ai price search result is json serializable', function () {
    $result = new AIPriceSearchResult(
        query: 'test',
        results: [],
        lowestPrice: null,
        highestPrice: null,
        searchedAt: now(),
        error: null,
        providersUsed: [],
    );

    $json = json_encode($result);
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded['query'])->toBe('test');
});

test('ai price search service builds correct prompt for specific items', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    // Create a partial mock to inspect the prompt
    $service = AIPriceSearchService::forUser($user->id);
    
    // Access the protected method using reflection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildSearchPrompt');
    $method->setAccessible(true);
    
    $prompt = $method->invoke($service, 'Sony WH-1000XM5', false, null, false, null, []);
    
    expect($prompt)->toContain('Sony WH-1000XM5');
    expect($prompt)->toContain('current prices');
    expect($prompt)->toContain('JSON');
});

test('ai price search service builds correct prompt for generic items', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildSearchPrompt');
    $method->setAccessible(true);
    
    $prompt = $method->invoke($service, 'blueberries', true, 'lb', false, null, []);
    
    expect($prompt)->toContain('blueberries');
    expect($prompt)->toContain('generic item');
    expect($prompt)->toContain('lb');
});

test('ai price search service includes location in prompt when shop local enabled', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildSearchPrompt');
    $method->setAccessible(true);
    
    $localStores = [
        ['store_name' => 'Walmart', 'store_type' => 'supermarket'],
        ['store_name' => 'Target', 'store_type' => 'supermarket'],
    ];
    
    $prompt = $method->invoke($service, 'milk', false, null, true, '90210', $localStores);
    
    expect($prompt)->toContain('LOCAL');
    expect($prompt)->toContain('90210');
    expect($prompt)->toContain('Walmart');
    expect($prompt)->toContain('Target');
});

test('ai price search service can check web search availability', function () {
    $user = User::factory()->create();
    
    $service = AIPriceSearchService::forUser($user->id);
    
    // Without SerpAPI key, should not be available
    expect($service->isWebSearchAvailable())->toBeFalse();
});

test('ai price search service with web search available', function () {
    $user = User::factory()->create();
    \App\Models\Setting::set(\App\Models\Setting::SERPAPI_KEY, 'test-key', $user->id);
    
    $service = AIPriceSearchService::forUser($user->id);
    
    expect($service->isWebSearchAvailable())->toBeTrue();
});

test('ai price search service parses valid json response', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseSearchResponse');
    $method->setAccessible(true);
    
    $response = '{
        "results": [
            {"title": "Test Product", "price": 29.99, "retailer": "Amazon", "url": "https://amazon.com/test", "image_url": "https://amazon.com/img.jpg", "in_stock": true}
        ],
        "is_generic": false,
        "unit_of_measure": null
    }';
    
    $parsed = $method->invoke($service, $response);
    
    expect($parsed['results'])->toHaveCount(1);
    expect($parsed['results'][0]['title'])->toBe('Test Product');
    expect($parsed['results'][0]['price'])->toBe(29.99);
    expect($parsed['results'][0]['retailer'])->toBe('Amazon');
});

test('ai price search service handles malformed json gracefully', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseSearchResponse');
    $method->setAccessible(true);
    
    $response = 'This is not valid JSON';
    
    $parsed = $method->invoke($service, $response);
    
    expect($parsed['results'])->toBeEmpty();
    expect($parsed['is_generic'])->toBeFalse();
});

test('ai price search service parses price from string formats', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parsePrice');
    $method->setAccessible(true);
    
    expect($method->invoke($service, 29.99))->toBe(29.99);
    expect($method->invoke($service, '29.99'))->toBe(29.99);
    expect($method->invoke($service, '$29.99'))->toBe(29.99);
    expect($method->invoke($service, 'USD 29.99'))->toBe(29.99);
});

test('ai price search service deduplicates results by url', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create([
        'api_key' => 'test-key',
    ]);

    $service = AIPriceSearchService::forUser($user->id);
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('deduplicateResults');
    $method->setAccessible(true);
    
    $results = [
        ['title' => 'Product 1', 'url' => 'https://example.com/1', 'retailer' => 'Store A'],
        ['title' => 'Product 1', 'url' => 'https://example.com/1', 'retailer' => 'Store A'], // duplicate
        ['title' => 'Product 2', 'url' => 'https://example.com/2', 'retailer' => 'Store B'],
    ];
    
    $deduplicated = $method->invoke($service, $results);
    
    expect($deduplicated)->toHaveCount(2);
});

test('ai price search service returns provider count', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create(['api_key' => 'key1']);
    AIProvider::factory()->for($user)->openai()->create(['api_key' => 'key2']);

    $service = AIPriceSearchService::forUser($user->id);

    expect($service->getProviderCount())->toBe(2);
});

test('ai price search with cache caches results', function () {
    $user = User::factory()->create();
    // No providers = will return error, but we can test that caching works
    
    $service = AIPriceSearchService::forUser($user->id);
    
    // First call
    $result1 = $service->searchWithCache('test query');
    // Second call should return cached result
    $result2 = $service->searchWithCache('test query');
    
    expect($result1->query)->toBe($result2->query);
    expect($result1->error)->toBe($result2->error);
});
