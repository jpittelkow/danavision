<?php

use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

test('streaming search requires authentication', function () {
    $response = $this->get('/smart-add/stream-search?query=test');

    $response->assertStatus(302); // Redirect to login
});

test('streaming search requires query parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/smart-add/stream-search');

    $response->assertStatus(302); // Validation redirect
});

test('streaming search returns SSE content type', function () {
    $user = User::factory()->create();

    // Mock the price API to return empty results
    Http::fake([
        '*' => Http::response(['shopping_results' => []], 200),
    ]);

    // Configure price API for user
    Setting::set(Setting::PRICE_API_PROVIDER, 'serpapi', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-key', $user->id);

    $response = $this->actingAs($user)->get('/smart-add/stream-search?query=test+product');

    $response->assertStatus(200);
    // Case-insensitive check for content type
    $contentType = strtolower($response->headers->get('Content-Type') ?? '');
    expect($contentType)->toContain('text/event-stream');
});

test('streaming search returns correct headers for SSE', function () {
    $user = User::factory()->create();

    Http::fake([
        '*' => Http::response(['shopping_results' => []], 200),
    ]);

    Setting::set(Setting::PRICE_API_PROVIDER, 'serpapi', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-key', $user->id);

    $response = $this->actingAs($user)->get('/smart-add/stream-search?query=test');

    $response->assertStatus(200);
    
    // Verify SSE-specific headers
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('Connection'))->toBe('keep-alive');
});

test('streaming search endpoint is accessible with valid user and query', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://serpapi.com/*' => Http::response([
            'shopping_results' => [
                [
                    'title' => 'Test Product',
                    'source' => 'Amazon',
                    'link' => 'https://amazon.com/test',
                    'extracted_price' => 99.99,
                    'thumbnail' => 'https://example.com/image.jpg',
                ],
            ],
        ], 200),
    ]);

    Setting::set(Setting::PRICE_API_PROVIDER, 'serpapi', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-key', $user->id);

    $response = $this->actingAs($user)->get('/smart-add/stream-search?query=test');

    // SSE endpoints should return 200 OK
    $response->assertStatus(200);
    
    // Note: Laravel's test framework doesn't fully capture streamed SSE responses
    // The actual streaming behavior is verified through E2E tests
});

test('streaming search works with multiple products', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://serpapi.com/*' => Http::response([
            'shopping_results' => [
                [
                    'title' => 'Product 1',
                    'source' => 'Store1',
                    'link' => 'https://store1.com/p1',
                    'extracted_price' => 49.99,
                ],
                [
                    'title' => 'Product 2',
                    'source' => 'Store2',
                    'link' => 'https://store2.com/p2',
                    'extracted_price' => 59.99,
                ],
            ],
        ], 200),
    ]);

    Setting::set(Setting::PRICE_API_PROVIDER, 'serpapi', $user->id);
    Setting::set(Setting::SERPAPI_KEY, 'test-key', $user->id);

    $response = $this->actingAs($user)->get('/smart-add/stream-search?query=test');

    // Endpoint should be accessible
    $response->assertStatus(200);
});
