<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Store;
use App\Services\Shopping\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private StoreService $storeService
    ) {}

    /**
     * List all active stores with the current user's preferences.
     */
    public function index(Request $request): JsonResponse
    {
        $stores = $this->storeService->getActiveStores($request->user());

        return response()->json(['data' => $stores]);
    }

    /**
     * Show store details.
     */
    public function show(Request $request, Store $store): JsonResponse
    {
        $store = $this->storeService->getStoreDetails($store, $request->user());

        return response()->json(['data' => $store]);
    }

    /**
     * Create a custom store.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'search_url_template' => ['nullable', 'string', 'max:2048'],
            'product_url_pattern' => ['nullable', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'is_local' => ['nullable', 'boolean'],
        ]);

        $store = $this->storeService->createStore($request->user(), $validated);

        return $this->createdResponse('Store created', ['data' => $store]);
    }

    /**
     * Update a store.
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'search_url_template' => ['nullable', 'string', 'max:2048'],
            'product_url_pattern' => ['nullable', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'is_local' => ['nullable', 'boolean'],
        ]);

        $store = $this->storeService->updateStore($store, $validated);

        return $this->successResponse('Store updated', ['data' => $store]);
    }

    /**
     * Delete a custom store.
     */
    public function destroy(Request $request, Store $store): JsonResponse
    {
        if ($store->is_default) {
            return $this->errorResponse('Cannot delete a default store', 422);
        }

        $this->storeService->deleteStore($store);

        return $this->deleteResponse('Store deleted');
    }

    /**
     * Get current user's store preferences.
     */
    public function userPreferences(Request $request): JsonResponse
    {
        $preferences = $this->storeService->getUserPreferences($request->user());

        return response()->json(['data' => $preferences]);
    }

    /**
     * Bulk update current user's store preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.priority' => ['nullable', 'integer', 'min:0'],
            'preferences.*.is_favorite' => ['nullable', 'boolean'],
        ]);

        $preferences = $this->storeService->updateUserPreferences($request->user(), $validated['preferences']);

        return $this->successResponse('Store preferences updated', ['data' => $preferences]);
    }

    /**
     * Suppress a store for the current user.
     */
    public function suppress(Request $request, Store $store): JsonResponse
    {
        $this->storeService->suppressStore($request->user(), $store);

        return $this->successResponse('Store suppressed');
    }

    /**
     * Restore a suppressed store for the current user.
     */
    public function restore(Request $request, Store $store): JsonResponse
    {
        $this->storeService->restoreStore($request->user(), $store);

        return $this->successResponse('Store restored');
    }

    /**
     * Bulk update store priorities.
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'priorities' => ['required', 'array'],
            'priorities.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            'priorities.*.priority' => ['required', 'integer', 'min:0'],
        ]);

        $this->storeService->updatePriorities($request->user(), $validated['priorities']);

        return $this->successResponse('Store priorities updated');
    }

    /**
     * Toggle favorite status for a store.
     */
    public function toggleFavorite(Request $request, Store $store): JsonResponse
    {
        $pref = $this->storeService->toggleFavorite($request->user(), $store);

        return $this->successResponse(
            $pref->is_favorite ? 'Store added to favorites' : 'Store removed from favorites',
            ['data' => $pref]
        );
    }

    /**
     * Link a parent store.
     */
    public function linkParent(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'parent_store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);

        $parent = Store::findOrFail($validated['parent_store_id']);
        $store = $this->storeService->linkParent($store, $parent);

        return $this->successResponse('Parent store linked', ['data' => $store]);
    }

    /**
     * Unlink parent store.
     */
    public function unlinkParent(Request $request, Store $store): JsonResponse
    {
        $store = $this->storeService->unlinkParent($store);

        return $this->successResponse('Parent store unlinked', ['data' => $store]);
    }

    /**
     * Check if nearby store discovery is available.
     */
    public function nearbyAvailability(): JsonResponse
    {
        return response()->json([
            'data' => [
                'available' => $this->storeService->isNearbyAvailable(),
            ],
        ]);
    }

    /**
     * Preview nearby stores for given coordinates.
     */
    public function nearbyPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_miles' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        $results = $this->storeService->searchNearby(
            $validated['lat'],
            $validated['lng'],
            $validated['radius_miles'] ?? 25,
            $validated['type'] ?? null,
        );

        return response()->json(['data' => $results]);
    }

    /**
     * Add selected nearby stores to the registry.
     */
    public function nearbyAdd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'places' => ['required', 'array', 'min:1'],
            'places.*.place_id' => ['required', 'string'],
            'places.*.name' => ['required', 'string', 'max:255'],
            'places.*.address' => ['nullable', 'string', 'max:500'],
            'places.*.location' => ['nullable', 'array'],
            'places.*.location.lat' => ['nullable', 'numeric'],
            'places.*.location.lng' => ['nullable', 'numeric'],
            'places.*.types' => ['nullable', 'array'],
        ]);

        $stores = $this->storeService->addNearbyStores($request->user(), $validated['places']);

        return $this->createdResponse('Stores added', ['data' => $stores]);
    }

    /**
     * Get suppressed vendors list.
     */
    public function suppressedVendors(Request $request): JsonResponse
    {
        $vendors = $this->storeService->getSuppressedVendors($request->user());

        return response()->json(['data' => $vendors]);
    }

    /**
     * Search addresses using Google Places autocomplete.
     */
    public function searchAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $results = $this->storeService->searchAddress($validated['query']);

        return response()->json(['data' => $results]);
    }

    /**
     * Geocode a Google Place ID to get coordinates and formatted address.
     */
    public function geocodePlace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'place_id' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->storeService->geocodePlace($validated['place_id']);

        if ($result === null) {
            return response()->json(['message' => 'Could not resolve address'], 422);
        }

        return response()->json(['data' => $result]);
    }
}
