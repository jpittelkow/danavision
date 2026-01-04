<?php

namespace App\Http\Controllers;

use App\Jobs\AI\NearbyStoreDiscoveryJob;
use App\Models\AIJob;
use App\Models\Setting;
use App\Services\Search\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $hasFirecrawlKey = !empty(Setting::get(Setting::FIRECRAWL_API_KEY, $user->id));

        $hasLocation = !empty(Setting::get(Setting::HOME_LATITUDE, $user->id))
            && !empty(Setting::get(Setting::HOME_LONGITUDE, $user->id));

        return response()->json([
            'success' => true,
            'available' => $hasGooglePlacesKey,
            'has_google_places_key' => $hasGooglePlacesKey,
            'has_firecrawl_key' => $hasFirecrawlKey,
            'has_location' => $hasLocation,
            'can_auto_configure' => $hasFirecrawlKey,
        ]);
    }
}
