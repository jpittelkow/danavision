<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AddressController
 *
 * Provides address autocomplete and reverse geocoding using Google APIs.
 * Uses the Google Places API key stored in user settings.
 */
class AddressController extends Controller
{
    /**
     * Google Places Autocomplete API URL.
     */
    protected const PLACES_AUTOCOMPLETE_URL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    /**
     * Google Place Details API URL.
     */
    protected const PLACE_DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    /**
     * Google Geocoding API URL.
     */
    protected const GEOCODING_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Search for addresses using Google Places Autocomplete.
     * Implements rate limiting and caching for efficiency.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $query = $validated['q'];
        $userId = $request->user()->id;

        // Get the API key
        $apiKey = $this->getApiKey($userId);
        if (!$apiKey) {
            return response()->json([
                'error' => 'Google API key not configured. Please add it in Settings.',
                'results' => [],
            ], 400);
        }

        // Rate limit: 10 requests per second per user (Google's limit is higher)
        $rateLimitKey = 'address_search:' . $userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return response()->json([
                'error' => 'Too many requests. Please wait a moment.',
                'results' => [],
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 1);

        // Check cache first (cache by query, case-insensitive)
        $cacheKey = 'google_address:' . md5(strtolower($query) . ':' . $userId);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'results' => $cached,
            ]);
        }

        try {
            // Step 1: Get autocomplete predictions
            $autocompleteResponse = Http::timeout(5)->get(self::PLACES_AUTOCOMPLETE_URL, [
                'input' => $query,
                'key' => $apiKey,
                'types' => 'address',
                'components' => 'country:us', // Limit to US addresses
                'language' => 'en',
            ]);

            if (!$autocompleteResponse->successful()) {
                Log::warning('Google Places Autocomplete API error', [
                    'status' => $autocompleteResponse->status(),
                    'query' => $query,
                ]);

                return response()->json([
                    'error' => 'Address search temporarily unavailable.',
                    'results' => [],
                ], 503);
            }

            $autocompleteData = $autocompleteResponse->json();

            if ($autocompleteData['status'] === 'REQUEST_DENIED') {
                Log::error('Google Places API request denied', [
                    'error' => $autocompleteData['error_message'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'error' => 'Google API error. Please check your API key configuration.',
                    'results' => [],
                ], 400);
            }

            if ($autocompleteData['status'] !== 'OK' && $autocompleteData['status'] !== 'ZERO_RESULTS') {
                Log::warning('Google Places Autocomplete unexpected status', [
                    'status' => $autocompleteData['status'],
                    'query' => $query,
                ]);

                return response()->json([
                    'error' => 'Address search failed.',
                    'results' => [],
                ], 500);
            }

            $predictions = $autocompleteData['predictions'] ?? [];

            if (empty($predictions)) {
                // Cache empty results for a shorter time
                Cache::put($cacheKey, [], 300);

                return response()->json([
                    'results' => [],
                ]);
            }

            // Step 2: Get details for each prediction to get coordinates
            $results = [];
            foreach (array_slice($predictions, 0, 5) as $prediction) {
                $details = $this->getPlaceDetails($prediction['place_id'], $apiKey);
                if ($details) {
                    $results[] = $details;
                }
            }

            // Cache for 1 hour
            Cache::put($cacheKey, $results, 3600);

            return response()->json([
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Address search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Address search failed. Please try again.',
                'results' => [],
            ], 500);
        }
    }

    /**
     * Get place details including coordinates.
     *
     * @param string $placeId
     * @param string $apiKey
     * @return array|null
     */
    protected function getPlaceDetails(string $placeId, string $apiKey): ?array
    {
        // Check cache for place details
        $cacheKey = 'google_place_details:' . $placeId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get(self::PLACE_DETAILS_URL, [
                'place_id' => $placeId,
                'key' => $apiKey,
                'fields' => 'formatted_address,geometry,address_components,name',
                'language' => 'en',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return null;
            }

            $result = $data['result'] ?? [];
            $location = $result['geometry']['location'] ?? [];
            $components = $result['address_components'] ?? [];

            $addressData = $this->parseAddressComponents($components);

            $formattedResult = [
                'display_name' => $result['formatted_address'] ?? '',
                'latitude' => (float) ($location['lat'] ?? 0),
                'longitude' => (float) ($location['lng'] ?? 0),
                'street' => $addressData['street'],
                'city' => $addressData['city'],
                'state' => $addressData['state'],
                'postcode' => $addressData['postcode'],
                'country' => $addressData['country'],
                'type' => 'address',
            ];

            // Cache for 24 hours
            Cache::put($cacheKey, $formattedResult, 86400);

            return $formattedResult;

        } catch (\Exception $e) {
            Log::warning('Failed to get place details', [
                'place_id' => $placeId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse Google address components into our format.
     *
     * @param array $components
     * @return array
     */
    protected function parseAddressComponents(array $components): array
    {
        $result = [
            'street_number' => '',
            'route' => '',
            'street' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
        ];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];

            if (in_array('street_number', $types)) {
                $result['street_number'] = $component['long_name'] ?? '';
            } elseif (in_array('route', $types)) {
                $result['route'] = $component['long_name'] ?? '';
            } elseif (in_array('locality', $types)) {
                $result['city'] = $component['long_name'] ?? '';
            } elseif (in_array('sublocality_level_1', $types) && empty($result['city'])) {
                // Fallback for areas without a city (e.g., Brooklyn, NY)
                $result['city'] = $component['long_name'] ?? '';
            } elseif (in_array('administrative_area_level_1', $types)) {
                $result['state'] = $component['short_name'] ?? '';
            } elseif (in_array('postal_code', $types)) {
                $result['postcode'] = $component['long_name'] ?? '';
            } elseif (in_array('country', $types)) {
                $result['country'] = $component['long_name'] ?? '';
            }
        }

        // Build street address
        $streetParts = [];
        if (!empty($result['street_number'])) {
            $streetParts[] = $result['street_number'];
        }
        if (!empty($result['route'])) {
            $streetParts[] = $result['route'];
        }
        $result['street'] = implode(' ', $streetParts);

        return $result;
    }

    /**
     * Reverse geocode coordinates to an address using Google Geocoding API.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $userId = $request->user()->id;

        // Get the API key
        $apiKey = $this->getApiKey($userId);
        if (!$apiKey) {
            return response()->json([
                'error' => 'Google API key not configured. Please add it in Settings.',
                'result' => null,
            ], 400);
        }

        // Rate limit
        $rateLimitKey = 'address_reverse:' . $userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return response()->json([
                'error' => 'Too many requests. Please wait a moment.',
                'result' => null,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 1);

        // Check cache (round to 4 decimal places for cache key)
        $lat = round($validated['lat'], 4);
        $lon = round($validated['lon'], 4);
        $cacheKey = 'google_reverse:' . md5("{$lat},{$lon}");

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'result' => $cached,
            ]);
        }

        try {
            $response = Http::timeout(5)->get(self::GEOCODING_URL, [
                'latlng' => "{$validated['lat']},{$validated['lon']}",
                'key' => $apiKey,
                'language' => 'en',
                'result_type' => 'street_address|route|premise',
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Reverse geocoding temporarily unavailable.',
                    'result' => null,
                ], 503);
            }

            $data = $response->json();

            if ($data['status'] === 'REQUEST_DENIED') {
                Log::error('Google Geocoding API request denied', [
                    'error' => $data['error_message'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'error' => 'Google API error. Please check your API key configuration.',
                    'result' => null,
                ], 400);
            }

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return response()->json([
                    'error' => 'No address found for these coordinates.',
                    'result' => null,
                ], 404);
            }

            // Use the first result (most accurate)
            $firstResult = $data['results'][0];
            $components = $firstResult['address_components'] ?? [];
            $addressData = $this->parseAddressComponents($components);
            $location = $firstResult['geometry']['location'] ?? [];

            $result = [
                'display_name' => $firstResult['formatted_address'] ?? '',
                'latitude' => (float) ($location['lat'] ?? $validated['lat']),
                'longitude' => (float) ($location['lng'] ?? $validated['lon']),
                'street' => $addressData['street'],
                'city' => $addressData['city'],
                'state' => $addressData['state'],
                'postcode' => $addressData['postcode'],
                'country' => $addressData['country'],
            ];

            // Cache for 24 hours
            Cache::put($cacheKey, $result, 86400);

            return response()->json([
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed', [
                'lat' => $validated['lat'],
                'lon' => $validated['lon'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Reverse geocoding failed. Please try again.',
                'result' => null,
            ], 500);
        }
    }

    /**
     * Get the Google API key for a user.
     *
     * @param int $userId
     * @return string|null
     */
    protected function getApiKey(int $userId): ?string
    {
        // First try user's configured key
        $userKey = Setting::get(Setting::GOOGLE_PLACES_API_KEY, $userId);
        if (!empty($userKey)) {
            return $userKey;
        }

        // Fallback to application config
        return config('services.google_places.api_key');
    }
}
