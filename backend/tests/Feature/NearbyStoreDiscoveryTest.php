<?php

use App\Models\AIJob;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use App\Services\Search\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Google Places Service', function () {
    test('service is not available without API key', function () {
        $service = GooglePlacesService::forUser($this->user->id);
        expect($service->isAvailable())->toBeFalse();
    });

    test('service is available with API key', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);
        $service = GooglePlacesService::forUser($this->user->id);
        expect($service->isAvailable())->toBeTrue();
    });

    test('service returns available categories', function () {
        $categories = GooglePlacesService::getCategories();
        
        expect($categories)->toHaveKeys([
            'grocery',
            'electronics',
            'pet',
            'pharmacy',
            'home',
            'clothing',
            'warehouse',
            'general',
            'specialty',
        ]);
    });

    test('service can extract domain from URL', function () {
        expect(GooglePlacesService::extractDomain('https://www.walmart.com/store'))->toBe('walmart.com');
        expect(GooglePlacesService::extractDomain('https://target.com/product'))->toBe('target.com');
        expect(GooglePlacesService::extractDomain('http://www.amazon.com'))->toBe('amazon.com');
        expect(GooglePlacesService::extractDomain(null))->toBeNull();
        expect(GooglePlacesService::extractDomain('invalid'))->toBeNull();
    });

    test('service searches nearby stores', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);

        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response([
                'places' => [
                    [
                        'id' => 'place_123',
                        'displayName' => ['text' => 'Test Grocery Store'],
                        'formattedAddress' => '123 Main St, City, State 12345',
                        'location' => ['latitude' => 40.7128, 'longitude' => -74.0060],
                        'websiteUri' => 'https://testgrocery.com',
                        'types' => ['supermarket'],
                    ],
                ],
            ], 200),
        ]);

        $service = GooglePlacesService::forUser($this->user->id);
        $result = $service->searchNearbyStores(40.7128, -74.0060, 10, ['grocery']);

        expect($result['success'])->toBeTrue();
        expect($result['stores'])->toHaveCount(1);
        expect($result['stores'][0]['name'])->toBe('Test Grocery Store');
    });
});

describe('Nearby Store Discovery API', function () {
    test('availability check works without API key', function () {
        $response = $this->getJson('/api/stores/nearby/availability');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'available' => false,
                'has_google_places_key' => false,
            ]);
    });

    test('availability check works with API key', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);
        Setting::set(Setting::HOME_LATITUDE, '40.7128', $this->user->id);
        Setting::set(Setting::HOME_LONGITUDE, '-74.0060', $this->user->id);

        $response = $this->getJson('/api/stores/nearby/availability');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
                'has_google_places_key' => true,
                'has_location' => true,
            ]);
    });

    test('categories endpoint returns available categories', function () {
        $response = $this->getJson('/api/stores/nearby/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'categories',
            ]);
    });

    test('discover returns error without location', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);

        $response = $this->postJson('/api/stores/nearby/discover', [
            'radius_miles' => 10,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    });

    test('discover returns error without API key', function () {
        Setting::set(Setting::HOME_LATITUDE, '40.7128', $this->user->id);
        Setting::set(Setting::HOME_LONGITUDE, '-74.0060', $this->user->id);

        $response = $this->postJson('/api/stores/nearby/discover', [
            'radius_miles' => 10,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    });

    test('discover creates job with valid request', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);
        Setting::set(Setting::HOME_LATITUDE, '40.7128', $this->user->id);
        Setting::set(Setting::HOME_LONGITUDE, '-74.0060', $this->user->id);

        Http::fake([
            'places.googleapis.com/*' => Http::response(['places' => []], 200),
        ]);

        $response = $this->postJson('/api/stores/nearby/discover', [
            'radius_miles' => 10,
            'categories' => ['grocery', 'pharmacy'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Store discovery started',
            ])
            ->assertJsonStructure(['job_id']);

        // Verify job was created (may be pending or completed depending on queue driver)
        $job = AIJob::where('user_id', $this->user->id)
            ->where('type', AIJob::TYPE_NEARBY_STORE_DISCOVERY)
            ->first();
        
        expect($job)->not->toBeNull();
        expect($job->status)->toBeIn([AIJob::STATUS_PENDING, AIJob::STATUS_COMPLETED]);
    });

    test('preview returns nearby stores', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-api-key', $this->user->id);

        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response([
                'places' => [
                    [
                        'id' => 'place_abc',
                        'displayName' => ['text' => 'Test Store'],
                        'formattedAddress' => '123 Test St',
                        'location' => ['latitude' => 40.7128, 'longitude' => -74.0060],
                        'types' => ['store'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stores/nearby/preview', [
            'radius_miles' => 5,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'stores',
                'store_count',
            ]);
    });
});

describe('Store Model with Google Places fields', function () {
    test('store can be found by google place id', function () {
        $store = Store::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'domain' => 'test.com',
            'google_place_id' => 'place_123456',
            'is_active' => true,
        ]);

        $found = Store::findByGooglePlaceId('place_123456');
        expect($found)->not->toBeNull();
        expect($found->id)->toBe($store->id);

        $notFound = Store::findByGooglePlaceId('nonexistent');
        expect($notFound)->toBeNull();
    });

    test('store can calculate distance to coordinates', function () {
        $store = Store::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'domain' => 'test.com',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        // Same location should be 0 distance
        $distance = $store->distanceTo(40.7128, -74.0060);
        expect($distance)->toBeLessThan(0.01);

        // Different location should have non-zero distance
        $distance = $store->distanceTo(40.7580, -73.9855); // Times Square
        expect($distance)->toBeGreaterThan(0);
        expect($distance)->toBeLessThan(10); // Should be less than 10 miles
    });

    test('store without coordinates returns null distance', function () {
        $store = Store::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'domain' => 'test.com',
            'is_active' => true,
        ]);

        $distance = $store->distanceTo(40.7128, -74.0060);
        expect($distance)->toBeNull();
    });

    test('auto configured scope works', function () {
        Store::create([
            'name' => 'Auto Store',
            'slug' => 'auto-store',
            'domain' => 'auto.com',
            'auto_configured' => true,
            'is_active' => true,
        ]);

        Store::create([
            'name' => 'Manual Store',
            'slug' => 'manual-store',
            'domain' => 'manual.com',
            'auto_configured' => false,
            'is_active' => true,
        ]);

        expect(Store::autoConfigured()->count())->toBe(1);
        expect(Store::autoConfigured()->first()->name)->toBe('Auto Store');
    });

    test('store has pet category', function () {
        $store = Store::create([
            'name' => 'Pet Store',
            'slug' => 'pet-store',
            'domain' => 'petstore.com',
            'category' => Store::CATEGORY_PET,
            'is_active' => true,
        ]);

        expect($store->category)->toBe('pet');
        expect(Store::category('pet')->count())->toBe(1);
    });
});

describe('Settings with Google Places API Key', function () {
    test('google places api key constant exists', function () {
        expect(Setting::GOOGLE_PLACES_API_KEY)->toBe('google_places_api_key');
    });

    test('google places api key is encrypted', function () {
        expect(Setting::shouldBeEncrypted(Setting::GOOGLE_PLACES_API_KEY))->toBeTrue();
    });

    test('google places api key can be set and retrieved', function () {
        Setting::set(Setting::GOOGLE_PLACES_API_KEY, 'test-key-12345', $this->user->id);

        $retrieved = Setting::get(Setting::GOOGLE_PLACES_API_KEY, $this->user->id);
        expect($retrieved)->toBe('test-key-12345');
    });
});

describe('AIJob with nearby store discovery type', function () {
    test('nearby store discovery type exists', function () {
        expect(AIJob::TYPE_NEARBY_STORE_DISCOVERY)->toBe('nearby_store_discovery');
    });

    test('nearby store discovery has label', function () {
        expect(AIJob::TYPE_LABELS[AIJob::TYPE_NEARBY_STORE_DISCOVERY])->toBe('Nearby Store Discovery');
    });

    test('nearby store discovery job can be created', function () {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_NEARBY_STORE_DISCOVERY,
            inputData: [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'radius_miles' => 10,
                'categories' => ['grocery', 'pharmacy'],
            ],
        );

        expect($job->type)->toBe(AIJob::TYPE_NEARBY_STORE_DISCOVERY);
        expect($job->status)->toBe(AIJob::STATUS_PENDING);
        expect($job->input_summary)->toContain('10 mile radius');
    });
});

describe('Add Selected Stores API', function () {
    test('user can add selected stores from preview', function () {
        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => [
                [
                    'place_id' => 'place_abc123',
                    'name' => 'New Grocery Store',
                    'address' => '123 Main St, City, State 12345',
                    'category' => 'grocery',
                    'latitude' => 40.7128,
                    'longitude' => -74.0060,
                    'website' => 'https://newgrocery.com',
                    'phone' => '555-123-4567',
                ],
                [
                    'place_id' => 'place_def456',
                    'name' => 'Pet Supply Store',
                    'address' => '456 Oak Ave, City, State 12345',
                    'category' => 'pet',
                    'latitude' => 40.7130,
                    'longitude' => -74.0055,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_requested' => 2,
                    'added' => 2,
                    'skipped' => 0,
                    'errors' => 0,
                ],
            ]);

        // Verify stores were created
        $this->assertDatabaseHas('stores', [
            'google_place_id' => 'place_abc123',
            'name' => 'New Grocery Store',
            'is_local' => true,
        ]);

        $this->assertDatabaseHas('stores', [
            'google_place_id' => 'place_def456',
            'name' => 'Pet Supply Store',
            'is_local' => true,
        ]);

        // Verify user preferences were created
        $store = Store::where('google_place_id', 'place_abc123')->first();
        $this->assertDatabaseHas('user_store_preferences', [
            'user_id' => $this->user->id,
            'store_id' => $store->id,
            'enabled' => true,
            'is_favorite' => true,
        ]);
    });

    test('adding store with existing place id skips and creates preference', function () {
        // Create a store with a place ID
        $existingStore = Store::create([
            'name' => 'Existing Store',
            'slug' => 'existing-store',
            'domain' => 'existing.com',
            'google_place_id' => 'place_existing',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => [
                [
                    'place_id' => 'place_existing',
                    'name' => 'Existing Store',
                    'address' => '789 Elm St',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'summary' => [
                    'added' => 0,
                    'skipped' => 1,
                ],
            ]);

        // Verify preference was created for existing store
        $this->assertDatabaseHas('user_store_preferences', [
            'user_id' => $this->user->id,
            'store_id' => $existingStore->id,
        ]);
    });

    test('adding store with existing domain updates it with place data', function () {
        // Create a store with just a domain
        $existingStore = Store::create([
            'name' => 'Domain Store',
            'slug' => 'domain-store',
            'domain' => 'domainstore.com',
            'is_active' => true,
            'is_local' => false,
        ]);

        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => [
                [
                    'place_id' => 'place_new123',
                    'name' => 'Domain Store Location',
                    'address' => '999 Pine St',
                    'website' => 'https://www.domainstore.com/location',
                    'latitude' => 40.7128,
                    'longitude' => -74.0060,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'summary' => [
                    'skipped' => 1, // Skipped because domain exists
                ],
            ]);

        // Verify store was updated with place data
        $existingStore->refresh();
        expect($existingStore->google_place_id)->toBe('place_new123');
        expect($existingStore->latitude)->not->toBeNull();
        expect($existingStore->is_local)->toBeTrue();
    });

    test('add selected stores validates required fields', function () {
        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => [
                [
                    // Missing place_id and name
                    'address' => '123 Main St',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stores.0.place_id', 'stores.0.name']);
    });

    test('add selected stores requires at least one store', function () {
        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stores']);
    });

    test('add selected stores limits to 50 stores', function () {
        $stores = [];
        for ($i = 0; $i < 51; $i++) {
            $stores[] = [
                'place_id' => "place_{$i}",
                'name' => "Store {$i}",
            ];
        }

        $response = $this->postJson('/api/stores/nearby/add-selected', [
            'stores' => $stores,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stores']);
    });
});
