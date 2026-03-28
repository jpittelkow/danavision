<?php

namespace App\Services\PriceSearch;

use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use App\Services\PriceSearch\Providers\BestBuyApiProvider;
use App\Services\PriceSearch\Providers\KrogerApiProvider;
use App\Services\Shopping\StoreService;
use Illuminate\Support\Facades\Log;

class LocationOptionsResolver
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    /**
     * Resolve location-specific options for price search providers.
     *
     * Returns an array of options keys that can be merged into
     * the PriceApiService::search() call when shop_local is enabled.
     */
    public function resolveOptions(User $user): array
    {
        $location = $this->storeService->getUserLocation($user);
        if (!$location) {
            return [];
        }

        $options = [];

        // SerpAPI: location string for geo-targeted Google Shopping
        if (!empty($location['address'])) {
            $options['location'] = $location['address'];
        } elseif (!empty($location['zip_code'])) {
            $options['location'] = $location['zip_code'];
        }

        // CrawlAI: zip code for {zip} URL template substitution
        if (!empty($location['zip_code'])) {
            $options['zip'] = $location['zip_code'];
        }

        // Raw coordinates for any provider that needs them
        $lat = $location['latitude'] ?? null;
        $lng = $location['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            Log::warning('LocationOptionsResolver: user has address but no coordinates, skipping store-specific lookups', [
                'user_id' => $user->id,
                'has_address' => !empty($location['address']),
                'has_zip' => !empty($location['zip_code']),
            ]);

            return $options;
        }

        $options['latitude'] = (float) $lat;
        $options['longitude'] = (float) $lng;

        // Kroger: resolve nearest store location ID
        $krogerLocationId = $this->resolveKrogerLocationId($user, (float) $lat, (float) $lng);
        if ($krogerLocationId !== null) {
            $options['kroger_location_id'] = $krogerLocationId;
        }

        // Best Buy: resolve nearest store ID
        $bestBuyStoreId = $this->resolveBestBuyStoreId($user, (float) $lat, (float) $lng);
        if ($bestBuyStoreId !== null) {
            $options['bestbuy_store_id'] = $bestBuyStoreId;
        }

        return $options;
    }

    /**
     * Clear cached provider location IDs when user changes home address.
     */
    public function clearCachedLocationIds(User $user): void
    {
        $cleared = UserStorePreference::where('user_id', $user->id)
            ->whereNotNull('location_id')
            ->update(['location_id' => null]);

        if ($cleared > 0) {
            Log::info('LocationOptionsResolver: cleared cached location IDs', [
                'user_id' => $user->id,
                'count' => $cleared,
            ]);
        }
    }

    /**
     * Resolve the nearest Kroger location ID for the user's home coordinates.
     *
     * Checks cached value in UserStorePreference first, then falls back to
     * a live API lookup via KrogerApiProvider::searchLocations().
     */
    private function resolveKrogerLocationId(User $user, float $lat, float $lng): ?string
    {
        $krogerStore = Store::where('slug', 'kroger')->first();

        // Check for cached value
        if ($krogerStore) {
            $pref = UserStorePreference::where('user_id', $user->id)
                ->where('store_id', $krogerStore->id)
                ->whereNotNull('location_id')
                ->first();

            if ($pref) {
                return $pref->location_id;
            }
        }

        // Live lookup via Kroger API
        $krogerProvider = app(KrogerApiProvider::class);
        if (!$krogerProvider->isAvailable()) {
            return null;
        }

        try {
            $locations = $krogerProvider->searchLocations($lat, $lng, 25);
            if (empty($locations)) {
                return null;
            }

            $locationId = $locations[0]['location_id'] ?? null;

            // Cache in UserStorePreference
            if ($krogerStore && $locationId) {
                UserStorePreference::updateOrCreate(
                    ['user_id' => $user->id, 'store_id' => $krogerStore->id],
                    ['location_id' => $locationId]
                );

                Log::info('LocationOptionsResolver: cached Kroger location ID', [
                    'user_id' => $user->id,
                    'location_id' => $locationId,
                ]);
            }

            return $locationId;
        } catch (\Exception $e) {
            Log::warning('LocationOptionsResolver: Kroger location lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve the nearest Best Buy store ID for the user's home coordinates.
     *
     * Same caching pattern as Kroger: check UserStorePreference first,
     * then fall back to live BestBuyApiProvider::searchStores() lookup.
     */
    private function resolveBestBuyStoreId(User $user, float $lat, float $lng): ?string
    {
        $bbStore = Store::where('slug', 'best-buy')->first();

        // Check for cached value
        if ($bbStore) {
            $pref = UserStorePreference::where('user_id', $user->id)
                ->where('store_id', $bbStore->id)
                ->whereNotNull('location_id')
                ->first();

            if ($pref) {
                return $pref->location_id;
            }
        }

        // Live lookup via Best Buy API
        $bbProvider = app(BestBuyApiProvider::class);
        if (!$bbProvider->isAvailable()) {
            return null;
        }

        try {
            $stores = $bbProvider->searchStores($lat, $lng, 25);
            if (empty($stores)) {
                return null;
            }

            $storeId = (string) ($stores[0]['store_id'] ?? '');
            if ($storeId === '') {
                return null;
            }

            // Cache in UserStorePreference
            if ($bbStore) {
                UserStorePreference::updateOrCreate(
                    ['user_id' => $user->id, 'store_id' => $bbStore->id],
                    ['location_id' => $storeId]
                );

                Log::info('LocationOptionsResolver: cached Best Buy store ID', [
                    'user_id' => $user->id,
                    'store_id' => $storeId,
                ]);
            }

            return $storeId;
        } catch (\Exception $e) {
            Log::warning('LocationOptionsResolver: Best Buy store lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
