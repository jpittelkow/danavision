<?php

namespace App\Http\Controllers;

use App\Jobs\AI\NearbyStoreDiscoveryJob;
use App\Jobs\AI\StoreAutoConfigJob;
use App\Models\AIJob;
use App\Models\Setting;
use App\Models\Store;
use App\Models\UserStorePreference;
use App\Services\Crawler\StoreAutoConfigService;
use App\Services\Search\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * NearbyStoreController
 *
 * Handles API endpoints for discovering nearby stores using
 * Google Places API and automatically configuring them for
 * price discovery.
 */
class NearbyStoreController extends Controller
{
    /**
     * Start a nearby store discovery job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function discoverNearby(Request $request): JsonResponse
    {
        $request->validate([
            'radius_miles' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'in:grocery,electronics,pet,pharmacy,home,clothing,warehouse,general,specialty'],
            'latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
        ]);

        $user = $request->user();

        // Get location from request or user settings
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if ($latitude === null || $longitude === null) {
            // Try to get from user settings
            $latitude = Setting::get(Setting::HOME_LATITUDE, $user->id);
            $longitude = Setting::get(Setting::HOME_LONGITUDE, $user->id);

            if ($latitude === null || $longitude === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No location provided. Please set your home address in Settings or provide coordinates.',
                ], 400);
            }

            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
        }

        // Check if Google Places API is configured
        $placesService = GooglePlacesService::forUser($user->id);
        if (!$placesService->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => 'Google Places API key not configured. Please add your API key in Settings.',
            ], 400);
        }

        $radiusMiles = $request->input('radius_miles', 10);
        $categories = $request->input('categories', []);

        // Create the AI job
        $aiJob = AIJob::createJob(
            userId: $user->id,
            type: AIJob::TYPE_NEARBY_STORE_DISCOVERY,
            inputData: [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_miles' => $radiusMiles,
                'categories' => $categories,
            ],
        );

        // Dispatch the job
        dispatch(new NearbyStoreDiscoveryJob($aiJob->id, $user->id));

        return response()->json([
            'success' => true,
            'job_id' => $aiJob->id,
            'message' => 'Store discovery started',
        ], 201);
    }

    /**
     * Get the status of a discovery job.
     *
     * @param Request $request
     * @param AIJob $aiJob
     * @return JsonResponse
     */
    public function getDiscoveryStatus(Request $request, AIJob $aiJob): JsonResponse
    {
        $user = $request->user();

        // Ensure the job belongs to the current user
        if ($aiJob->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Job not found',
            ], 404);
        }

        // Ensure it's a nearby store discovery job
        if ($aiJob->type !== AIJob::TYPE_NEARBY_STORE_DISCOVERY) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid job type',
            ], 400);
        }

        $response = [
            'success' => true,
            'job_id' => $aiJob->id,
            'status' => $aiJob->status,
            'status_label' => $aiJob->status_label,
            'progress' => $aiJob->progress,
            'started_at' => $aiJob->started_at?->toISOString(),
            'completed_at' => $aiJob->completed_at?->toISOString(),
            'can_cancel' => $aiJob->canBeCancelled(),
        ];

        // Include output data if completed
        if ($aiJob->isCompleted()) {
            $outputData = $aiJob->output_data ?? [];
            $response['result'] = [
                'stores_found' => $outputData['stores_found'] ?? 0,
                'stores_added' => $outputData['stores_added'] ?? 0,
                'stores_skipped' => $outputData['stores_skipped'] ?? 0,
                'stores_configured' => $outputData['stores_configured'] ?? 0,
                'added_store_ids' => $outputData['added_store_ids'] ?? [],
            ];
        }

        // Include progress logs if processing
        if ($aiJob->status === AIJob::STATUS_PROCESSING) {
            $outputData = $aiJob->output_data ?? [];
            $response['progress_logs'] = $outputData['progress_logs'] ?? [];
            $response['status_message'] = $outputData['status_message'] ?? null;
        }

        // Include error if failed
        if ($aiJob->isFailed()) {
            $response['error'] = $aiJob->error_message;
        }

        return response()->json($response);
    }

    /**
     * Cancel a discovery job.
     *
     * @param Request $request
     * @param AIJob $aiJob
     * @return JsonResponse
     */
    public function cancelDiscovery(Request $request, AIJob $aiJob): JsonResponse
    {
        $user = $request->user();

        // Ensure the job belongs to the current user
        if ($aiJob->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Job not found',
            ], 404);
        }

        // Ensure it's a nearby store discovery job
        if ($aiJob->type !== AIJob::TYPE_NEARBY_STORE_DISCOVERY) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid job type',
            ], 400);
        }

        if (!$aiJob->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'error' => 'This job cannot be cancelled',
            ], 400);
        }

        $aiJob->markAsCancelled();

        return response()->json([
            'success' => true,
            'message' => 'Discovery job cancelled',
        ]);
    }

    /**
     * Get available store categories for discovery.
     *
     * @return JsonResponse
     */
    public function getCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'categories' => GooglePlacesService::getCategories(),
        ]);
    }

    /**
     * Preview stores that would be found without actually adding them.
     * Useful for showing estimated API costs before discovery.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function previewNearby(Request $request): JsonResponse
    {
        $request->validate([
            'radius_miles' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'in:grocery,electronics,pet,pharmacy,home,clothing,warehouse,general,specialty'],
            'latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
        ]);

        $user = $request->user();

        // Get location from request or user settings
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if ($latitude === null || $longitude === null) {
            $latitude = Setting::get(Setting::HOME_LATITUDE, $user->id);
            $longitude = Setting::get(Setting::HOME_LONGITUDE, $user->id);

            if ($latitude === null || $longitude === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No location provided',
                ], 400);
            }

            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
        }

        $placesService = GooglePlacesService::forUser($user->id);
        if (!$placesService->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => 'Google Places API key not configured',
            ], 400);
        }

        $radiusMiles = $request->input('radius_miles', 10);
        $categories = $request->input('categories', []);

        $result = $placesService->searchNearbyStores(
            $latitude,
            $longitude,
            $radiusMiles,
            $categories
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Search failed',
            ], 500);
        }

        // Estimate API costs
        $storeCount = count($result['stores']);
        $estimatedFirecrawlCredits = $storeCount * 5; // Rough estimate: 5 credits per store for auto-config

        return response()->json([
            'success' => true,
            'stores' => $result['stores'],
            'store_count' => $storeCount,
            'estimated_firecrawl_credits' => $estimatedFirecrawlCredits,
        ]);
    }

    /**
     * Add selected stores from preview results.
     *
     * This endpoint allows users to select specific stores from the preview
     * and add only those to their registry.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addSelectedStores(Request $request): JsonResponse
    {
        $request->validate([
            'stores' => ['required', 'array', 'min:1', 'max:50'],
            'stores.*.place_id' => ['required', 'string'],
            'stores.*.name' => ['required', 'string', 'max:255'],
            'stores.*.address' => ['nullable', 'string', 'max:500'],
            'stores.*.category' => ['nullable', 'string', 'in:grocery,electronics,pet,pharmacy,home,clothing,warehouse,general,specialty'],
            'stores.*.latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'stores.*.longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
            'stores.*.website' => ['nullable', 'string', 'max:500'],
            'stores.*.phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $userId = $user->id;
        $storesToAdd = $request->input('stores');

        $added = [];
        $skipped = [];
        $errors = [];

        foreach ($storesToAdd as $storeData) {
            $placeId = $storeData['place_id'];
            $storeName = $storeData['name'];

            try {
                // Check if store already exists by Google Place ID
                $existingStore = Store::findByGooglePlaceId($placeId);
                if ($existingStore) {
                    // Store exists, just add/update user preference
                    UserStorePreference::setPreference($userId, $existingStore->id, [
                        'enabled' => true,
                        'is_favorite' => false,
                        'priority' => 70,
                    ]);
                    $skipped[] = [
                        'place_id' => $placeId,
                        'name' => $storeName,
                        'reason' => 'Already exists in registry',
                        'store_id' => $existingStore->id,
                    ];
                    continue;
                }

                // Extract domain from website if available
                $domain = null;
                if (!empty($storeData['website'])) {
                    $parsedUrl = parse_url($storeData['website']);
                    if (isset($parsedUrl['host'])) {
                        $domain = preg_replace('/^www\./', '', strtolower($parsedUrl['host']));
                    }
                }

                // Check if store exists by domain
                if ($domain) {
                    $existingByDomain = Store::findByDomain($domain);
                    if ($existingByDomain) {
                        // Update the existing store with Google Place ID
                        $existingByDomain->update([
                            'google_place_id' => $placeId,
                            'latitude' => $storeData['latitude'] ?? $existingByDomain->latitude,
                            'longitude' => $storeData['longitude'] ?? $existingByDomain->longitude,
                            'address' => $storeData['address'] ?? $existingByDomain->address,
                            'phone' => $storeData['phone'] ?? $existingByDomain->phone,
                            'is_local' => true,
                        ]);
                        UserStorePreference::setPreference($userId, $existingByDomain->id, [
                            'enabled' => true,
                            'is_favorite' => false,
                            'priority' => 70,
                        ]);
                        $skipped[] = [
                            'place_id' => $placeId,
                            'name' => $storeName,
                            'reason' => 'Domain already exists, updated with location data',
                            'store_id' => $existingByDomain->id,
                        ];
                        continue;
                    }
                }

                // Create new store
                $category = $storeData['category'] ?? Store::CATEGORY_GENERAL;
                $store = Store::create([
                    'name' => $storeName,
                    'slug' => Str::slug($storeName) . '-' . Str::random(4),
                    'domain' => $domain ?? '',
                    'google_place_id' => $placeId,
                    'latitude' => $storeData['latitude'] ?? null,
                    'longitude' => $storeData['longitude'] ?? null,
                    'address' => $storeData['address'] ?? null,
                    'phone' => $storeData['phone'] ?? null,
                    'is_default' => false,
                    'is_local' => true,
                    'is_active' => true,
                    'auto_configured' => false,
                    'category' => $category,
                    'default_priority' => 40,
                ]);

                // Create user preference
                UserStorePreference::setPreference($userId, $store->id, [
                    'enabled' => true,
                    'is_favorite' => true, // Favorite newly added local stores
                    'priority' => 70,
                ]);

                // Queue URL discovery as a background job if store has website
                $website = $storeData['website'] ?? null;
                $urlDiscoveryQueued = false;
                if ($website && $domain) {
                    $autoConfigService = StoreAutoConfigService::forUser($userId);
                    if ($autoConfigService->isAvailable()) {
                        $configJob = AIJob::createJob(
                            userId: $userId,
                            type: AIJob::TYPE_STORE_AUTO_CONFIG,
                            inputData: [
                                'store_id' => $store->id,
                                'website_url' => $website,
                                'store_name' => $storeName,
                            ],
                        );
                        dispatch(new StoreAutoConfigJob($configJob->id, $userId))->afterResponse();
                        $urlDiscoveryQueued = true;
                    }
                }

                $added[] = [
                    'place_id' => $placeId,
                    'name' => $storeName,
                    'store_id' => $store->id,
                    'domain' => $domain,
                    'url_discovery_queued' => $urlDiscoveryQueued,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'place_id' => $placeId,
                    'name' => $storeName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Count how many URL discovery jobs were queued
        $urlDiscoveryQueued = count(array_filter($added, fn($s) => $s['url_discovery_queued'] ?? false));

        return response()->json([
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'errors' => $errors,
            'summary' => [
                'total_requested' => count($storesToAdd),
                'added' => count($added),
                'skipped' => count($skipped),
                'errors' => count($errors),
                'url_discovery_queued' => $urlDiscoveryQueued,
            ],
        ]);
    }

    /**
     * Check if the nearby store discovery feature is available.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasGooglePlacesKey = !empty(Setting::get(Setting::GOOGLE_PLACES_API_KEY, $user->id))
            || !empty(config('services.google_places.api_key'));

        $canAutoConfigure = StoreAutoConfigService::forUser($user->id)->isAvailable();

        $hasLocation = !empty(Setting::get(Setting::HOME_LATITUDE, $user->id))
            && !empty(Setting::get(Setting::HOME_LONGITUDE, $user->id));

        return response()->json([
            'success' => true,
            'available' => $hasGooglePlacesKey,
            'has_google_places_key' => $hasGooglePlacesKey,
            'has_location' => $hasLocation,
            'can_auto_configure' => $canAutoConfigure,
        ]);
    }
}
