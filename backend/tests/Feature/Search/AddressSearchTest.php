<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->create();
    Cache::flush();
    RateLimiter::clear('address_search:' . $this->user->id);
});

it('requires authentication for address search', function () {
    $response = $this->get('/api/address/search?q=test');
    
    $response->assertRedirect('/login');
});

it('validates query length', function () {
    $this->actingAs($this->user);

    // Too short - Laravel redirects back with session errors for web routes
    $response = $this->getJson('/api/address/search?q=ab');
    $response->assertStatus(422);
});

it('returns address suggestions from nominatim', function () {
    $this->actingAs($this->user);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => '123 Main St, Springfield, IL 62701, USA',
                'lat' => '39.7817',
                'lon' => '-89.6501',
                'address' => [
                    'house_number' => '123',
                    'road' => 'Main St',
                    'city' => 'Springfield',
                    'state' => 'Illinois',
                    'postcode' => '62701',
                    'country' => 'United States',
                ],
                'type' => 'house',
            ],
        ], 200),
    ]);

    $response = $this->get('/api/address/search?q=123 Main St Springfield');

    $response->assertOk();
    $response->assertJsonStructure([
        'results' => [
            '*' => [
                'display_name',
                'latitude',
                'longitude',
                'street',
                'city',
                'state',
                'postcode',
                'country',
            ],
        ],
    ]);

    $data = $response->json();
    expect($data['results'])->toHaveCount(1);
    expect($data['results'][0]['postcode'])->toBe('62701');
});

it('caches address search results', function () {
    $this->actingAs($this->user);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => '123 Main St, Springfield, IL 62701, USA',
                'lat' => '39.7817',
                'lon' => '-89.6501',
                'address' => [],
                'type' => 'house',
            ],
        ], 200),
    ]);

    // First request
    $this->get('/api/address/search?q=123 Main St Springfield');
    
    // Reset rate limiter for second request
    RateLimiter::clear('address_search:' . $this->user->id);
    
    // Second request should use cache
    $response = $this->get('/api/address/search?q=123 Main St Springfield');

    $response->assertOk();
    
    // Should only have made one HTTP request due to caching
    Http::assertSentCount(1);
});

it('rate limits address searches', function () {
    $this->actingAs($this->user);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    // First request - should succeed
    $response1 = $this->get('/api/address/search?q=first search');
    $response1->assertOk();

    // Immediate second request - should be rate limited
    $response2 = $this->get('/api/address/search?q=second search');
    $response2->assertStatus(429);
});

it('handles nominatim api errors gracefully', function () {
    $this->actingAs($this->user);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response('Server Error', 500),
    ]);

    $response = $this->get('/api/address/search?q=123 Main St');

    $response->assertStatus(503);
    $response->assertJson([
        'error' => 'Address search temporarily unavailable.',
        'results' => [],
    ]);
});

it('filters out results without valid coordinates', function () {
    $this->actingAs($this->user);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Valid Address',
                'lat' => '39.7817',
                'lon' => '-89.6501',
                'address' => [],
                'type' => 'house',
            ],
            [
                'display_name' => 'Invalid Address',
                'lat' => '0',
                'lon' => '0',
                'address' => [],
                'type' => 'unknown',
            ],
        ], 200),
    ]);

    $response = $this->get('/api/address/search?q=test address');

    $response->assertOk();
    $data = $response->json();
    
    // Only the valid address should be returned
    expect($data['results'])->toHaveCount(1);
    expect($data['results'][0]['display_name'])->toBe('Valid Address');
});
