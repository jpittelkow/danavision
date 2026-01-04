<?php

namespace App\Services\Search;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GooglePlacesService
 *
 * Provides integration with Google Places API for discovering
 * nearby stores based on location and category filters.
 *
 * Features:
 * - Search for stores within a radius of a given location
 * - Get detailed place information including website URLs
 * - Map store categories to Google Places types
 * - Cache results to reduce API costs
 */
class GooglePlacesService
{
    /**
     * Meters per mile for API conversion.
     */
    protected const METERS_PER_MILE = 1609.34;

    /**
     * Maximum radius supported by Google Places API (50km ~ 31 miles).
     */
    protected const MAX_RADIUS_METERS = 50000;

    /**
     * Google Places API base URL.
     */
    protected const API_BASE_URL = 'https://places.googleapis.com/v1';

    /**
     * The user ID for fetching API keys.
     */
    protected ?int $userId;

    /**
     * The Google Places API key.
     */
    protected ?string $apiKey;

    /**
     * Mapping of our store categories to Google Places types.
     *
     * @var array<string, array<string>>
     */
    protected static array $categoryTypeMap = [
        'grocery' => ['supermarket', 'grocery_or_supermarket'],
        'electronics' => ['electronics_store'],
        'pet' => ['pet_store'],
        'pharmacy' => ['pharmacy', 'drugstore'],
        'home' => ['home_goods_store', 'hardware_store', 'furniture_store'],
        'clothing' => ['clothing_store', 'shoe_store'],
        'warehouse' => ['department_store', 'discount_store'],
        'general' => ['department_store', 'shopping_mall', 'store'],
        'specialty' => ['store', 'book_store', 'gift_shop'],
    ];

    /**
     * Known warehouse club names for filtering.
     *
     * @var array<string>
     */
    protected static array $warehouseClubNames = [
        'costco',
        "sam's club",
        "bj's",
        'bjs',
    ];

    /**
     * Create a new GooglePlacesService instance.
     *
     * @param int|null $userId The user ID for API key retrieval
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->loadApiKey();
    }

    /**
     * Create an instance for a specific user.
     *
     * @param int $userId
     * @return self
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Load the API key from settings.
     */
    protected function loadApiKey(): void
    {
        $this->apiKey = Setting::get(Setting::GOOGLE_PLACES_API_KEY, $this->userId)
            ?? config('services.google_places.api_key');
    }

    /**
     * Check if the service is available (API key is configured).
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search for nearby stores within a radius.
     *
     * @param float $latitude Center point latitude
     * @param float $longitude Center point longitude
     * @param float $radiusMiles Search radius in miles
     * @param array<string> $categories Store categories to search for
     * @return array{success: bool, stores: array, error?: string}
     */
    public function searchNearbyStores(
        float $latitude,
        float $longitude,
        float $radiusMiles = 10,
        array $categories = []
    ): array {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'stores' => [],
                'error' => 'Google Places API key not configured',
            ];
        }

        // Convert miles to meters
        $radiusMeters = min($radiusMiles * self::METERS_PER_MILE, self::MAX_RADIUS_METERS);

        // Get Google Places types for the requested categories
        $types = $this->getTypesForCategories($categories);

        Log::info('GooglePlacesService: Searching nearby stores', [
            'user_id' => $this->userId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_miles' => $radiusMiles,
            'radius_meters' => $radiusMeters,
            'categories' => $categories,
            'types' => $types,
        ]);

        $allStores = [];

        // Search for each type to get comprehensive results
        foreach ($types as $type) {
            $stores = $this->searchByType($latitude, $longitude, $radiusMeters, $type);
            $allStores = array_merge($allStores, $stores);
        }

        // Deduplicate by place_id
        $uniqueStores = [];
        $seenPlaceIds = [];
        foreach ($allStores as $store) {
            if (!in_array($store['place_id'], $seenPlaceIds)) {
                $seenPlaceIds[] = $store['place_id'];
                $uniqueStores[] = $store;
            }
        }

        // Filter warehouse clubs if warehouse category is requested
        if (in_array('warehouse', $categories)) {
            $uniqueStores = $this->filterWarehouseClubs($uniqueStores);
        }

        // Sort by distance
        usort($uniqueStores, fn($a, $b) => ($a['distance_miles'] ?? 0) <=> ($b['distance_miles'] ?? 0));

        Log::info('GooglePlacesService: Search completed', [
            'stores_found' => count($uniqueStores),
        ]);

        return [
            'success' => true,
            'stores' => $uniqueStores,
        ];
    }

    /**
     * Search for places by type using Nearby Search.
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusMeters
     * @param string $type
     * @return array
     */
    protected function searchByType(float $latitude, float $longitude, float $radiusMeters, string $type): array
    {
        // Round coordinates to 4 decimal places (~11m precision) for consistent cache keys
        $cacheKey = sprintf("google_places:%.4f:%.4f:%.0f:%s", $latitude, $longitude, $radiusMeters, $type);

        return Cache::remember($cacheKey, 3600, function () use ($latitude, $longitude, $radiusMeters, $type) {
            try {
                // Using the new Places API (Nearby Search)
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.types,places.websiteUri,places.nationalPhoneNumber,places.regularOpeningHours,places.rating,places.userRatingCount',
                ])->post(self::API_BASE_URL . '/places:searchNearby', [
                    'includedTypes' => [$type],
                    'maxResultCount' => 20,
                    'locationRestriction' => [
                        'circle' => [
                            'center' => [
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                            ],
                            'radius' => $radiusMeters,
                        ],
                    ],
                ]);

                if (!$response->successful()) {
                    Log::warning('GooglePlacesService: API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'type' => $type,
                    ]);
                    return [];
                }

                $data = $response->json();
                $stores = [];

                foreach ($data['places'] ?? [] as $place) {
                    $stores[] = $this->formatPlaceResult($place, $latitude, $longitude);
                }

                return $stores;

            } catch (\Exception $e) {
                Log::error('GooglePlacesService: Error searching places', [
                    'error' => $e->getMessage(),
                    'type' => $type,
                ]);
                return [];
            }
        });
    }

    /**
     * Format a place result into our standard structure.
     *
     * @param array $place
     * @param float $originLat
     * @param float $originLng
     * @return array
     */
    protected function formatPlaceResult(array $place, float $originLat, float $originLng): array
    {
        $placeLat = $place['location']['latitude'] ?? 0;
        $placeLng = $place['location']['longitude'] ?? 0;

        $distanceMiles = $this->calculateDistance($originLat, $originLng, $placeLat, $placeLng);

        return [
            'place_id' => $place['id'] ?? '',
            'name' => $place['displayName']['text'] ?? 'Unknown',
            'address' => $place['formattedAddress'] ?? '',
            'latitude' => $placeLat,
            'longitude' => $placeLng,
            'website' => $place['websiteUri'] ?? null,
            'phone' => $place['nationalPhoneNumber'] ?? null,
            'types' => $place['types'] ?? [],
            'rating' => $place['rating'] ?? null,
            'review_count' => $place['userRatingCount'] ?? null,
            'distance_miles' => round($distanceMiles, 2),
            'category' => $this->categorizePlace($place['types'] ?? [], $place['displayName']['text'] ?? ''),
        ];
    }

    /**
     * Get detailed information about a specific place.
     *
     * @param string $placeId The Google Place ID
     * @return array{success: bool, place?: array, error?: string}
     */
    public function getPlaceDetails(string $placeId): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Google Places API key not configured',
            ];
        }

        $cacheKey = "google_place_details:{$placeId}";

        return Cache::remember($cacheKey, 86400, function () use ($placeId) {
            try {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'id,displayName,formattedAddress,location,types,websiteUri,nationalPhoneNumber,internationalPhoneNumber,regularOpeningHours,rating,userRatingCount',
                ])->get(self::API_BASE_URL . "/places/{$placeId}");

                if (!$response->successful()) {
                    return [
                        'success' => false,
                        'error' => 'Failed to fetch place details',
                    ];
                }

                $place = $response->json();

                return [
                    'success' => true,
                    'place' => [
                        'place_id' => $place['id'] ?? $placeId,
                        'name' => $place['displayName']['text'] ?? 'Unknown',
                        'address' => $place['formattedAddress'] ?? '',
                        'latitude' => $place['location']['latitude'] ?? null,
                        'longitude' => $place['location']['longitude'] ?? null,
                        'website' => $place['websiteUri'] ?? null,
                        'phone' => $place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? null,
                        'types' => $place['types'] ?? [],
                        'rating' => $place['rating'] ?? null,
                        'review_count' => $place['userRatingCount'] ?? null,
                        'category' => $this->categorizePlace($place['types'] ?? [], $place['displayName']['text'] ?? ''),
                    ],
                ];

            } catch (\Exception $e) {
                Log::error('GooglePlacesService: Error fetching place details', [
                    'place_id' => $placeId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Get Google Places types for the given categories.
     *
     * @param array<string> $categories
     * @return array<string>
     */
    protected function getTypesForCategories(array $categories): array
    {
        if (empty($categories)) {
            // Default to common retail types
            return ['supermarket', 'department_store', 'electronics_store', 'pharmacy'];
        }

        $types = [];
        foreach ($categories as $category) {
            if (isset(self::$categoryTypeMap[$category])) {
                $types = array_merge($types, self::$categoryTypeMap[$category]);
            }
        }

        return array_unique($types);
    }

    /**
     * Categorize a place based on its types and name.
     *
     * @param array<string> $types
     * @param string $name
     * @return string
     */
    protected function categorizePlace(array $types, string $name): string
    {
        $nameLower = strtolower($name);

        // Check for warehouse clubs by name
        foreach (self::$warehouseClubNames as $warehouseName) {
            if (str_contains($nameLower, $warehouseName)) {
                return 'warehouse';
            }
        }

        // Check types
        foreach ($types as $type) {
            foreach (self::$categoryTypeMap as $category => $categoryTypes) {
                if (in_array($type, $categoryTypes)) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    /**
     * Filter results to only include warehouse clubs when that category is selected.
     *
     * @param array $stores
     * @return array
     */
    protected function filterWarehouseClubs(array $stores): array
    {
        $warehouseStores = [];
        $otherStores = [];

        foreach ($stores as $store) {
            $nameLower = strtolower($store['name']);
            $isWarehouse = false;

            foreach (self::$warehouseClubNames as $warehouseName) {
                if (str_contains($nameLower, $warehouseName)) {
                    $isWarehouse = true;
                    break;
                }
            }

            if ($isWarehouse) {
                $store['category'] = 'warehouse';
                $warehouseStores[] = $store;
            } else {
                $otherStores[] = $store;
            }
        }

        // Prioritize warehouse clubs
        return array_merge($warehouseStores, $otherStores);
    }

    /**
     * Calculate distance between two coordinates in miles using Haversine formula.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float
     */
    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3959;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadiusMiles;
    }

    /**
     * Get all available store categories.
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            'grocery' => 'Grocery Stores',
            'electronics' => 'Electronics',
            'pet' => 'Pet Stores',
            'pharmacy' => 'Pharmacies',
            'home' => 'Home & Hardware',
            'clothing' => 'Clothing & Apparel',
            'warehouse' => 'Warehouse Clubs',
            'general' => 'General Retail',
            'specialty' => 'Specialty Stores',
        ];
    }

    /**
     * Extract domain from a website URL.
     *
     * @param string|null $websiteUrl
     * @return string|null
     */
    public static function extractDomain(?string $websiteUrl): ?string
    {
        if (!$websiteUrl) {
            return null;
        }

        $host = parse_url($websiteUrl, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        // Remove www. prefix
        return preg_replace('/^www\./', '', strtolower($host));
    }
}
