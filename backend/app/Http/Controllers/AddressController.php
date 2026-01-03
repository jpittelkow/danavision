<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AddressController extends Controller
{
    /**
     * OpenStreetMap Nominatim base URL.
     */
    protected const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Search for addresses using OpenStreetMap Nominatim.
     * Implements rate limiting and caching to comply with Nominatim usage policy.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $query = $validated['q'];
        $userId = $request->user()->id;

        // Rate limit: 1 request per second per user (Nominatim policy)
        $rateLimitKey = 'address_search:' . $userId;
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'error' => 'Too many requests. Please wait a moment.',
                'results' => [],
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 1); // 1 second decay

        // Check cache first (cache by query, case-insensitive)
        $cacheKey = 'nominatim:' . md5(strtolower($query));
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'results' => $cached,
            ]);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'DanaVision/1.0 (price-tracker-app)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->timeout(5)
            ->get(self::NOMINATIM_URL, [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 5,
                'countrycodes' => 'us', // Limit to US addresses
            ]);

            if (!$response->successful()) {
                Log::warning('Nominatim API error', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);
                
                return response()->json([
                    'error' => 'Address search temporarily unavailable.',
                    'results' => [],
                ], 503);
            }

            $data = $response->json();
            
            // Transform results to a simpler format
            $results = collect($data)->map(function ($item) {
                $address = $item['address'] ?? [];
                
                return [
                    'display_name' => $item['display_name'] ?? '',
                    'latitude' => (float) ($item['lat'] ?? 0),
                    'longitude' => (float) ($item['lon'] ?? 0),
                    'street' => $this->buildStreetAddress($address),
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? '',
                    'state' => $address['state'] ?? '',
                    'postcode' => $address['postcode'] ?? '',
                    'country' => $address['country'] ?? '',
                    'type' => $item['type'] ?? 'unknown',
                ];
            })->filter(function ($item) {
                // Filter out results without valid coordinates
                return $item['latitude'] !== 0.0 && $item['longitude'] !== 0.0;
            })->values()->toArray();

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
     * Build a street address string from address components.
     */
    protected function buildStreetAddress(array $address): string
    {
        $parts = [];
        
        if (!empty($address['house_number'])) {
            $parts[] = $address['house_number'];
        }
        
        if (!empty($address['road'])) {
            $parts[] = $address['road'];
        }
        
        return implode(' ', $parts);
    }

    /**
     * Reverse geocode coordinates to an address.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $userId = $request->user()->id;

        // Rate limit
        $rateLimitKey = 'address_reverse:' . $userId;
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'error' => 'Too many requests. Please wait a moment.',
                'result' => null,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 1);

        // Check cache
        $cacheKey = 'nominatim_reverse:' . md5($validated['lat'] . ',' . $validated['lon']);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'result' => $cached,
            ]);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'DanaVision/1.0 (price-tracker-app)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->timeout(5)
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $validated['lat'],
                'lon' => $validated['lon'],
                'format' => 'json',
                'addressdetails' => 1,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Reverse geocoding temporarily unavailable.',
                    'result' => null,
                ], 503);
            }

            $data = $response->json();
            $address = $data['address'] ?? [];

            $result = [
                'display_name' => $data['display_name'] ?? '',
                'latitude' => (float) ($data['lat'] ?? $validated['lat']),
                'longitude' => (float) ($data['lon'] ?? $validated['lon']),
                'street' => $this->buildStreetAddress($address),
                'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
                'state' => $address['state'] ?? '',
                'postcode' => $address['postcode'] ?? '',
                'country' => $address['country'] ?? '',
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
}
