<?php

namespace App\Services\Location;

use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private const PLACES_BASE_URL = 'https://maps.googleapis.com/maps/api/place';

    private const GEOCODE_BASE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    private readonly ?string $apiKey;

    public function __construct(
        private readonly SettingService $settingService,
    ) {
        $this->apiKey = $this->settingService->get('price_search', 'google_places_key');
    }

    /**
     * Search for nearby stores/places.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $radiusMiles Search radius in miles (converted to meters for API)
     * @param string|null $type Optional Google Places type filter (e.g., 'grocery_or_supermarket', 'store')
     * @return array Array of nearby places with details
     */
    public function searchNearby(float $lat, float $lng, int $radiusMiles = 25, ?string $type = null): array
    {
        if (!$this->isAvailable()) {
            Log::warning('GooglePlacesService: API key not configured');
            return [];
        }

        try {
            $radiusMeters = (int) ($radiusMiles * 1609.34);

            $params = [
                'location' => "{$lat},{$lng}",
                'radius' => min($radiusMeters, 50000), // API max is 50km
                'key' => $this->apiKey,
            ];

            if ($type !== null) {
                $params['type'] = $type;
            }

            $response = Http::timeout(15)->get(
                self::PLACES_BASE_URL . '/nearbysearch/json',
                $params,
            );

            if (!$response->successful()) {
                Log::error('GooglePlacesService: nearby search failed', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' && ($data['status'] ?? '') !== 'ZERO_RESULTS') {
                Log::error('GooglePlacesService: API error', [
                    'status' => $data['status'] ?? 'Unknown',
                    'error_message' => $data['error_message'] ?? null,
                ]);
                return [];
            }

            return $this->formatPlacesResults($data['results'] ?? []);
        } catch (\Exception $e) {
            Log::error('GooglePlacesService: nearby search error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get detailed information about a specific place.
     *
     * @param string $placeId The Google Place ID
     * @return array Place details including address, phone, hours, etc.
     */
    public function getPlaceDetails(string $placeId): array
    {
        if (!$this->isAvailable()) {
            Log::warning('GooglePlacesService: API key not configured');
            return [];
        }

        try {
            $response = Http::timeout(15)->get(
                self::PLACES_BASE_URL . '/details/json',
                [
                    'place_id' => $placeId,
                    'fields' => implode(',', [
                        'name',
                        'formatted_address',
                        'formatted_phone_number',
                        'opening_hours',
                        'website',
                        'url',
                        'rating',
                        'user_ratings_total',
                        'geometry',
                        'types',
                        'business_status',
                        'price_level',
                    ]),
                    'key' => $this->apiKey,
                ],
            );

            if (!$response->successful()) {
                Log::error('GooglePlacesService: place details request failed', [
                    'place_id' => $placeId,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                Log::error('GooglePlacesService: place details API error', [
                    'status' => $data['status'] ?? 'Unknown',
                ]);
                return [];
            }

            $result = $data['result'] ?? [];

            return [
                'place_id' => $placeId,
                'name' => $result['name'] ?? '',
                'address' => $result['formatted_address'] ?? '',
                'phone' => $result['formatted_phone_number'] ?? null,
                'website' => $result['website'] ?? null,
                'google_maps_url' => $result['url'] ?? null,
                'rating' => $result['rating'] ?? null,
                'reviews_count' => $result['user_ratings_total'] ?? null,
                'business_status' => $result['business_status'] ?? null,
                'price_level' => $result['price_level'] ?? null,
                'types' => $result['types'] ?? [],
                'location' => [
                    'lat' => $result['geometry']['location']['lat'] ?? null,
                    'lng' => $result['geometry']['location']['lng'] ?? null,
                ],
                'opening_hours' => [
                    'open_now' => $result['opening_hours']['open_now'] ?? null,
                    'weekday_text' => $result['opening_hours']['weekday_text'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('GooglePlacesService: place details error', [
                'place_id' => $placeId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Search for addresses using Places Autocomplete.
     *
     * @param string $query The address search query
     * @return array Array of address suggestions
     */
    public function searchAddress(string $query): array
    {
        if (!$this->isAvailable()) {
            Log::warning('GooglePlacesService: API key not configured');
            return [];
        }

        try {
            $response = Http::timeout(10)->get(
                self::PLACES_BASE_URL . '/autocomplete/json',
                [
                    'input' => $query,
                    'types' => 'address',
                    'key' => $this->apiKey,
                ],
            );

            if (!$response->successful()) {
                Log::error('GooglePlacesService: address search failed', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' && ($data['status'] ?? '') !== 'ZERO_RESULTS') {
                return [];
            }

            $results = [];
            foreach ($data['predictions'] ?? [] as $prediction) {
                $results[] = [
                    'place_id' => $prediction['place_id'] ?? '',
                    'description' => $prediction['description'] ?? '',
                    'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                    'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? '',
                    'types' => $prediction['types'] ?? [],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('GooglePlacesService: address search error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Reverse geocode coordinates to an address string.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return string|null The formatted address, or null if not found
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        if (!$this->isAvailable()) {
            Log::warning('GooglePlacesService: API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(10)->get(
                self::GEOCODE_BASE_URL,
                [
                    'latlng' => "{$lat},{$lng}",
                    'key' => $this->apiKey,
                ],
            );

            if (!$response->successful()) {
                Log::error('GooglePlacesService: reverse geocode failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                return null;
            }

            $results = $data['results'] ?? [];

            if (empty($results)) {
                return null;
            }

            // Return the first (most specific) result's formatted address
            return $results[0]['formatted_address'] ?? null;
        } catch (\Exception $e) {
            Log::error('GooglePlacesService: reverse geocode error', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if the Google Places API is available (API key configured).
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Format raw Places API results into a consistent structure.
     */
    private function formatPlacesResults(array $rawResults): array
    {
        $results = [];

        foreach ($rawResults as $place) {
            $results[] = [
                'place_id' => $place['place_id'] ?? '',
                'name' => $place['name'] ?? '',
                'address' => $place['vicinity'] ?? $place['formatted_address'] ?? '',
                'location' => [
                    'lat' => $place['geometry']['location']['lat'] ?? null,
                    'lng' => $place['geometry']['location']['lng'] ?? null,
                ],
                'rating' => $place['rating'] ?? null,
                'reviews_count' => $place['user_ratings_total'] ?? null,
                'price_level' => $place['price_level'] ?? null,
                'types' => $place['types'] ?? [],
                'open_now' => $place['opening_hours']['open_now'] ?? null,
                'business_status' => $place['business_status'] ?? null,
                'icon' => $place['icon'] ?? null,
            ];
        }

        return $results;
    }
}
