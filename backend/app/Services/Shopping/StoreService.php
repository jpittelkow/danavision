<?php

namespace App\Services\Shopping;

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use App\Services\AuditService;
use App\Services\Location\GooglePlacesService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class StoreService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly GooglePlacesService $googlePlacesService,
        private readonly StoreChainService $storeChainService,
    ) {}

    /**
     * Get all active stores with the current user's preference overlaid.
     */
    public function getActiveStores(User $user): array
    {
        $stores = Store::where('is_active', true)
            ->with('parentStore')
            ->orderBy('name')
            ->get();

        $preferences = UserStorePreference::where('user_id', $user->id)
            ->get()
            ->keyBy('store_id');

        return $stores->map(function (Store $store) use ($preferences) {
            $pref = $preferences->get($store->id);
            $store->user_enabled = $pref ? $pref->enabled : true;
            $store->user_priority = $pref ? $pref->priority : $store->default_priority;
            $store->is_favorite = $pref ? $pref->is_favorite : false;
            return $store;
        })->sortBy('user_priority')->values()->toArray();
    }

    /**
     * Get store details with user preference.
     */
    public function getStoreDetails(Store $store, User $user): Store
    {
        $store->load(['parentStore', 'subsidiaries']);

        $pref = UserStorePreference::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->first();

        $store->user_enabled = $pref ? $pref->enabled : true;
        $store->user_priority = $pref ? $pref->priority : $store->default_priority;
        $store->is_favorite = $pref ? $pref->is_favorite : false;

        return $store;
    }

    /**
     * Get user's store preferences.
     */
    public function getUserPreferences(User $user): Collection
    {
        return UserStorePreference::where('user_id', $user->id)
            ->with('store')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Bulk update user store preferences.
     */
    public function updateUserPreferences(User $user, array $preferences): array
    {
        $updated = [];

        foreach ($preferences as $pref) {
            $updated[] = UserStorePreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'store_id' => $pref['store_id'],
                ],
                [
                    'enabled' => $pref['enabled'],
                    'priority' => $pref['priority'] ?? null,
                    'is_favorite' => $pref['is_favorite'] ?? false,
                ]
            );
        }

        $this->auditService->log('store_preferences.updated', null, [], [
            'count' => count($updated),
        ], $user->id);

        return $updated;
    }

    /**
     * Create a custom store.
     */
    public function createStore(User $user, array $data): Store
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_default'] = false;
        $data['is_active'] = true;

        $store = Store::create($data);

        $this->auditService->log('store.created', $store, [], $data, $user->id);

        return $store;
    }

    /**
     * Update a store.
     */
    public function updateStore(Store $store, array $data): Store
    {
        $oldValues = $store->getAttributes();
        $store->update($data);

        $this->auditService->log('store.updated', $store, $oldValues, $data);

        return $store;
    }

    /**
     * Delete a custom store (cannot delete default stores).
     */
    public function deleteStore(Store $store): void
    {
        if ($store->is_default) {
            throw new \InvalidArgumentException('Cannot delete a default store.');
        }

        $this->auditService->log('store.deleted', $store, $store->getAttributes());

        $store->delete();
    }

    /**
     * Toggle favorite status for a store.
     */
    public function toggleFavorite(User $user, Store $store): UserStorePreference
    {
        $pref = UserStorePreference::firstOrCreate(
            ['user_id' => $user->id, 'store_id' => $store->id],
            ['enabled' => true, 'priority' => $store->default_priority]
        );

        $pref->update(['is_favorite' => !$pref->is_favorite]);

        return $pref;
    }

    /**
     * Suppress a store for a user (add to suppressed vendors list).
     */
    public function suppressStore(User $user, Store $store): void
    {
        $pref = UserStorePreference::firstOrCreate(
            ['user_id' => $user->id, 'store_id' => $store->id],
            ['priority' => $store->default_priority]
        );

        $pref->update(['enabled' => false]);

        // Also add to suppressed_vendors setting for price search filtering
        $setting = Setting::firstOrCreate(
            ['user_id' => $user->id, 'group' => 'shopping', 'key' => 'suppressed_vendors'],
            ['value' => []]
        );

        $vendors = (array) $setting->value;
        $storeName = strtolower($store->name);

        if (!in_array($storeName, $vendors)) {
            $vendors[] = $storeName;
            $setting->update(['value' => $vendors]);
        }

        $this->auditService->log('store.suppressed', $store, [], [
            'store_name' => $store->name,
        ], $user->id);
    }

    /**
     * Restore a suppressed store for a user.
     */
    public function restoreStore(User $user, Store $store): void
    {
        $pref = UserStorePreference::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->first();

        if ($pref) {
            $pref->update(['enabled' => true]);
        }

        // Remove from suppressed_vendors setting
        $setting = Setting::where('user_id', $user->id)
            ->where('group', 'shopping')
            ->where('key', 'suppressed_vendors')
            ->first();

        if ($setting) {
            $vendors = (array) $setting->value;
            $storeName = strtolower($store->name);
            $vendors = array_values(array_filter($vendors, fn ($v) => strtolower($v) !== $storeName));
            $setting->update(['value' => $vendors]);
        }

        $this->auditService->log('store.restored', $store, [], [
            'store_name' => $store->name,
        ], $user->id);
    }

    /**
     * Bulk update store priorities for a user.
     */
    public function updatePriorities(User $user, array $priorities): void
    {
        foreach ($priorities as $entry) {
            UserStorePreference::updateOrCreate(
                ['user_id' => $user->id, 'store_id' => $entry['store_id']],
                ['priority' => $entry['priority']]
            );
        }

        $this->auditService->log('store_priorities.updated', null, [], [
            'count' => count($priorities),
        ], $user->id);
    }

    /**
     * Link a store as a subsidiary of a parent store.
     */
    public function linkParent(Store $store, Store $parent): Store
    {
        $store->update(['parent_store_id' => $parent->id]);

        $this->auditService->log('store.parent_linked', $store, [], [
            'parent_store_id' => $parent->id,
            'parent_name' => $parent->name,
        ]);

        return $store;
    }

    /**
     * Unlink a store from its parent.
     */
    public function unlinkParent(Store $store): Store
    {
        $store->update(['parent_store_id' => null]);

        $this->auditService->log('store.parent_unlinked', $store);

        return $store;
    }

    /**
     * Search for nearby stores using Google Places.
     */
    public function searchNearby(float $lat, float $lng, int $radiusMiles = 25, ?string $type = null): array
    {
        return $this->googlePlacesService->searchNearby($lat, $lng, $radiusMiles, $type);
    }

    /**
     * Check if nearby store discovery is available.
     */
    public function isNearbyAvailable(): bool
    {
        return $this->googlePlacesService->isAvailable();
    }

    /**
     * Add selected nearby stores to the registry.
     */
    public function addNearbyStores(User $user, array $places): array
    {
        $stores = [];

        foreach ($places as $place) {
            // Check if store already exists by Google Place ID
            $existing = Store::where('google_place_id', $place['place_id'])->first();
            if ($existing) {
                $stores[] = $existing;
                continue;
            }

            // Extract domain from website if available
            $details = $this->googlePlacesService->getPlaceDetails($place['place_id']);
            $domain = null;
            if (!empty($details['website'])) {
                $parsed = parse_url($details['website']);
                $domain = $parsed['host'] ?? null;
            }

            $store = Store::create([
                'name' => $place['name'],
                'slug' => Str::slug($place['name']),
                'domain' => $domain,
                'google_place_id' => $place['place_id'],
                'latitude' => $place['location']['lat'] ?? null,
                'longitude' => $place['location']['lng'] ?? null,
                'address' => $place['address'] ?? $details['address'] ?? null,
                'phone' => $details['phone'] ?? null,
                'is_default' => false,
                'is_local' => true,
                'is_active' => true,
                'category' => $this->inferCategory($place['types'] ?? []),
            ]);

            // Auto-link to parent chain if it's a known subsidiary
            $this->storeChainService->autoLinkSubsidiary($store);

            // Auto-enable for the discovering user
            UserStorePreference::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'enabled' => true,
                'is_favorite' => false,
                'priority' => 50,
            ]);

            $stores[] = $store;
        }

        $this->auditService->log('store.nearby_added', null, [], [
            'count' => count($stores),
        ], $user->id);

        return $stores;
    }

    /**
     * Get suppressed vendors list for a user.
     */
    public function getSuppressedVendors(User $user): array
    {
        $setting = Setting::where('user_id', $user->id)
            ->where('group', 'shopping')
            ->where('key', 'suppressed_vendors')
            ->first();

        return $setting ? (array) $setting->value : [];
    }

    /**
     * Get the user's home location from their shopping settings.
     *
     * @return array{address: string|null, zip_code: string|null, latitude: float|null, longitude: float|null}|null
     */
    public function getUserLocation(User $user): ?array
    {
        $lat = $user->getSetting('shopping', 'home_latitude');
        $lng = $user->getSetting('shopping', 'home_longitude');

        // At minimum we need coordinates or a zip code
        $zip = $user->getSetting('shopping', 'home_zip_code');
        if ($lat === null && $lng === null && $zip === null) {
            return null;
        }

        return [
            'address' => $user->getSetting('shopping', 'home_address'),
            'zip_code' => $zip,
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
        ];
    }

    /**
     * Search address using Google Places autocomplete.
     */
    public function searchAddress(string $query): array
    {
        return $this->googlePlacesService->searchAddress($query);
    }

    /**
     * Geocode a Google Place ID to get coordinates and formatted address.
     */
    public function geocodePlace(string $placeId): ?array
    {
        $details = $this->googlePlacesService->getPlaceDetails($placeId);

        if (empty($details)) {
            return null;
        }

        return [
            'address' => $details['address'] ?? '',
            'zip_code' => $details['postal_code'] ?? null,
            'latitude' => $details['location']['lat'] ?? null,
            'longitude' => $details['location']['lng'] ?? null,
        ];
    }

    /**
     * Infer a store category from Google Places types.
     */
    private function inferCategory(array $types): string
    {
        $typeMap = [
            'grocery_or_supermarket' => 'Grocery',
            'supermarket' => 'Grocery',
            'convenience_store' => 'Convenience',
            'drugstore' => 'Pharmacy',
            'pharmacy' => 'Pharmacy',
            'department_store' => 'Department',
            'electronics_store' => 'Electronics',
            'hardware_store' => 'Hardware',
            'home_goods_store' => 'Home',
            'clothing_store' => 'Clothing',
            'furniture_store' => 'Furniture',
            'pet_store' => 'Pet',
            'shopping_mall' => 'Mall',
            'store' => 'General',
        ];

        foreach ($types as $type) {
            if (isset($typeMap[$type])) {
                return $typeMap[$type];
            }
        }

        return 'Other';
    }
}
