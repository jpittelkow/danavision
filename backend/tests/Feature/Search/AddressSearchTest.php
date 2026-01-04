<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->create();
    Cache::flush();
    RateLimiter::clear('address_search:' . $this->user->id);
    RateLimiter::clear('address_reverse:' . $this->user->id);

    // Set up Google API key for the user
    Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-google-api-key', $this->user->id);
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

it('returns error when google api key not configured', function () {
    $this->actingAs($this->user);

    // Remove the API key
    Setting::where('user_id', $this->user->id)
        ->where('key', Setting::GOOGLE_PLACES_API_KEY)
        ->delete();

    $response = $this->get('/api/address/search?q=123 Main St');

    $response->assertStatus(400);
    $response->assertJson([
        'error' => 'Google API key not configured. Please add it in Settings.',
        'results' => [],
    ]);
});

it('returns address suggestions from google places api', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response([
            'status' => 'OK',
            'predictions' => [
                [
                    'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
                    'description' => '123 Main St, Springfield, IL 62701, USA',
                ],
            ],
        ], 200),
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => [
                'formatted_address' => '123 Main St, Springfield, IL 62701, USA',
                'geometry' => [
                    'location' => [
                        'lat' => 39.7817,
                        'lng' => -89.6501,
                    ],
                ],
                'address_components' => [
                    ['types' => ['street_number'], 'long_name' => '123'],
                    ['types' => ['route'], 'long_name' => 'Main St'],
                    ['types' => ['locality'], 'long_name' => 'Springfield'],
                    ['types' => ['administrative_area_level_1'], 'short_name' => 'IL'],
                    ['types' => ['postal_code'], 'long_name' => '62701'],
                    ['types' => ['country'], 'long_name' => 'United States'],
                ],
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
    expect($data['results'][0]['city'])->toBe('Springfield');
    expect($data['results'][0]['state'])->toBe('IL');
});

it('caches address search results', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response([
            'status' => 'OK',
            'predictions' => [
                [
                    'place_id' => 'test-place-id',
                    'description' => '123 Main St, Springfield, IL 62701, USA',
                ],
            ],
        ], 200),
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => [
                'formatted_address' => '123 Main St, Springfield, IL 62701, USA',
                'geometry' => ['location' => ['lat' => 39.7817, 'lng' => -89.6501]],
                'address_components' => [],
            ],
        ], 200),
    ]);

    // First request
    $this->get('/api/address/search?q=123 Main St Springfield');

    // Second request should use cache (no additional HTTP calls needed)
    $response = $this->get('/api/address/search?q=123 Main St Springfield');

    $response->assertOk();

    // Should have made calls for autocomplete + details on first request only
    // Second request uses cache, so total is 2 (autocomplete + details)
    Http::assertSentCount(2);
});

it('rate limits address searches', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'predictions' => []], 200),
    ]);

    // Make 10 requests (the limit)
    for ($i = 0; $i < 10; $i++) {
        $response = $this->get('/api/address/search?q=search' . $i);
    }

    // 11th request should be rate limited
    $response = $this->get('/api/address/search?q=extra search');
    $response->assertStatus(429);
});

it('handles google api errors gracefully', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/*' => Http::response('Server Error', 500),
    ]);

    $response = $this->get('/api/address/search?q=123 Main St');

    $response->assertStatus(503);
    $response->assertJson([
        'error' => 'Address search temporarily unavailable.',
        'results' => [],
    ]);
});

it('handles google api request denied', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'REQUEST_DENIED',
            'error_message' => 'API key is invalid',
        ], 200),
    ]);

    $response = $this->get('/api/address/search?q=123 Main St');

    $response->assertStatus(400);
    $response->assertJson([
        'error' => 'Google API error. Please check your API key configuration.',
        'results' => [],
    ]);
});

it('handles zero results from google', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'ZERO_RESULTS',
            'predictions' => [],
        ], 200),
    ]);

    $response = $this->get('/api/address/search?q=nonexistent address xyz');

    $response->assertOk();
    $response->assertJson([
        'results' => [],
    ]);
});

it('reverse geocodes coordinates using google api', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
            'status' => 'OK',
            'results' => [
                [
                    'formatted_address' => '123 Main St, Springfield, IL 62701, USA',
                    'geometry' => [
                        'location' => [
                            'lat' => 39.7817,
                            'lng' => -89.6501,
                        ],
                    ],
                    'address_components' => [
                        ['types' => ['street_number'], 'long_name' => '123'],
                        ['types' => ['route'], 'long_name' => 'Main St'],
                        ['types' => ['locality'], 'long_name' => 'Springfield'],
                        ['types' => ['administrative_area_level_1'], 'short_name' => 'IL'],
                        ['types' => ['postal_code'], 'long_name' => '62701'],
                        ['types' => ['country'], 'long_name' => 'United States'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/api/address/reverse?lat=39.7817&lon=-89.6501');

    $response->assertOk();
    $response->assertJsonStructure([
        'result' => [
            'display_name',
            'latitude',
            'longitude',
            'street',
            'city',
            'state',
            'postcode',
            'country',
        ],
    ]);

    $data = $response->json();
    expect($data['result']['city'])->toBe('Springfield');
    expect($data['result']['state'])->toBe('IL');
});

it('requires authentication for reverse geocoding', function () {
    $response = $this->get('/api/address/reverse?lat=39.7817&lon=-89.6501');

    $response->assertRedirect('/login');
});

it('validates coordinates for reverse geocoding', function () {
    $this->actingAs($this->user);

    // Invalid latitude
    $response = $this->getJson('/api/address/reverse?lat=100&lon=-89.6501');
    $response->assertStatus(422);

    // Invalid longitude
    $response = $this->getJson('/api/address/reverse?lat=39.7817&lon=200');
    $response->assertStatus(422);
});

it('handles reverse geocoding no results', function () {
    $this->actingAs($this->user);

    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'ZERO_RESULTS',
            'results' => [],
        ], 200),
    ]);

    $response = $this->get('/api/address/reverse?lat=0&lon=0');

    $response->assertStatus(404);
    $response->assertJson([
        'error' => 'No address found for these coordinates.',
    ]);
});
