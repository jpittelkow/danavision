<?php

namespace App\Jobs\AI;

use App\Jobs\AI\StoreAutoConfigJob;
use App\Models\AIJob;
use App\Models\Setting;
use App\Models\Store;
use App\Models\UserStorePreference;
use App\Services\Crawler\StoreAutoConfigService;
use App\Services\Search\GooglePlacesService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NearbyStoreDiscoveryJob
 *
 * Background job for discovering stores near the user's location
 * using Google Places API and automatically configuring them
 * for price discovery.
 *
 * Process:
 * 1. Use Google Places API to find stores within the specified radius
 * 2. Get website URLs for each store
 * 3. Check if store already exists in the registry
 * 4. For new stores, use Firecrawl to detect search URL templates
 * 5. Create Store records with auto-configured templates
 * 6. Create UserStorePreference records (enabled by default)
 */
class NearbyStoreDiscoveryJob extends BaseAIJob
{
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes for discovering multiple stores

    /**
     * Process the nearby store discovery job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $logs = [];

        // Extract input parameters
        $latitude = $inputData['latitude'] ?? null;
        $longitude = $inputData['longitude'] ?? null;
        $radiusMiles = $inputData['radius_miles'] ?? 10;
        $categories = $inputData['categories'] ?? [];

        // Validate required coordinates
        if ($latitude === null || $longitude === null) {
            // Try to get from user settings
            $latitude = Setting::get(Setting::HOME_LATITUDE, $this->userId);
            $longitude = Setting::get(Setting::HOME_LONGITUDE, $this->userId);

            if ($latitude === null || $longitude === null) {
                throw new \RuntimeException('No location provided. Please set your home address in Settings or use current location.');
            }

            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
        }

        $logs[] = "Starting nearby store discovery";
        $logs[] = "Location: {$latitude}, {$longitude}";
        $logs[] = "Radius: {$radiusMiles} miles";
        $logs[] = "Categories: " . (empty($categories) ? 'All' : implode(', ', $categories));
        $this->updateProgress($aiJob, 5, $logs);

        // Initialize Google Places service
        $placesService = GooglePlacesService::forUser($this->userId);

        if (!$placesService->isAvailable()) {
            throw new \RuntimeException('Google Places API key not configured. Please add your API key in Settings.');
        }

        $logs[] = "Google Places API initialized";
        $this->updateProgress($aiJob, 10, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Search for nearby stores
        $logs[] = "Searching for nearby stores...";
        $this->updateProgress($aiJob, 15, $logs);

        $searchResult = $placesService->searchNearbyStores(
            $latitude,
            $longitude,
            $radiusMiles,
            $categories
        );

        if (!$searchResult['success']) {
            throw new \RuntimeException($searchResult['error'] ?? 'Failed to search for nearby stores');
        }

        $discoveredStores = $searchResult['stores'];
        $storeCount = count($discoveredStores);

        $logs[] = "Found {$storeCount} stores nearby";
        $this->updateProgress($aiJob, 25, $logs);

        if ($storeCount === 0) {
            $logs[] = "No stores found in the specified area";
            return [
                'stores_found' => 0,
                'stores_added' => 0,
                'stores_skipped' => 0,
                'stores_configured' => 0,
                'logs' => $logs,
            ];
        }

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Check if Firecrawl is available for auto-config (will be done as separate jobs)
        $autoConfigService = StoreAutoConfigService::forUser($this->userId);
        $firecrawlAvailable = $autoConfigService->isAvailable();

        if ($firecrawlAvailable) {
            $logs[] = "Firecrawl configured - URL discovery will be queued for new stores";
        } else {
            $logs[] = "Note: Firecrawl not configured - stores will be added without auto-configuration";
        }

        // Process each discovered store
        $storesAdded = 0;
        $storesSkipped = 0;
        $storesConfigured = 0;
        $addedStoreIds = [];

        $progressPerStore = 60 / max($storeCount, 1);

        foreach ($discoveredStores as $index => $place) {
            // Check for cancellation periodically
            if ($index % 5 === 0 && $this->isCancelled($aiJob)) {
                $logs[] = "Discovery cancelled by user";
                return [
                    'cancelled' => true,
                    'stores_found' => $storeCount,
                    'stores_added' => $storesAdded,
                    'stores_skipped' => $storesSkipped,
                    'stores_configured' => $storesConfigured,
                    'logs' => $logs,
                ];
            }

            $storeName = $place['name'] ?? 'Unknown Store';
            $placeId = $place['place_id'] ?? null;
            $website = $place['website'] ?? null;

            $logs[] = "Processing: {$storeName}";
            $currentProgress = 25 + (int)(($index + 1) * $progressPerStore);
            $this->updateProgress($aiJob, $currentProgress, $logs);

            // Check if store already exists by Google Place ID
            if ($placeId) {
                $existingStore = Store::findByGooglePlaceId($placeId);
                if ($existingStore) {
                    $logs[] = "  → Already in registry (by place ID)";
                    $storesSkipped++;
                    $this->ensureUserPreference($existingStore->id);
                    continue;
                }
            }

            // Check if store already exists by domain
            $domain = null;
            if ($website) {
                $domain = GooglePlacesService::extractDomain($website);
                if ($domain) {
                    $existingStore = Store::findByDomain($domain);
                    if ($existingStore) {
                        // Update the existing store with Google Place data if missing
                        if (!$existingStore->google_place_id && $placeId) {
                            $existingStore->update([
                                'google_place_id' => $placeId,
                                'latitude' => $place['latitude'] ?? null,
                                'longitude' => $place['longitude'] ?? null,
                                'address' => $place['address'] ?? null,
                                'phone' => $place['phone'] ?? null,
                            ]);
                        }
                        $logs[] = "  → Already in registry (by domain)";
                        $storesSkipped++;
                        $this->ensureUserPreference($existingStore->id);
                        continue;
                    }
                }
            }

            // Create new store
            try {
                $category = $place['category'] ?? Store::CATEGORY_GENERAL;

                $store = Store::create([
                    'name' => $storeName,
                    'slug' => Str::slug($storeName) . '-' . Str::random(4),
                    'domain' => $domain ?? '',
                    'google_place_id' => $placeId,
                    'latitude' => $place['latitude'] ?? null,
                    'longitude' => $place['longitude'] ?? null,
                    'address' => $place['address'] ?? null,
                    'phone' => $place['phone'] ?? null,
                    'is_default' => false,
                    'is_local' => true,
                    'is_active' => true,
                    'auto_configured' => false,
                    'category' => $category,
                    'default_priority' => 40,
                ]);

                $storesAdded++;
                $addedStoreIds[] = $store->id;
                $logs[] = "  → Added to registry";

                // Create user preference (enabled and favorited)
                $this->createUserPreference($store->id);

                // Queue URL discovery as a separate background job (non-blocking)
                if ($firecrawlAvailable && $website && $domain) {
                    $configJob = AIJob::createJob(
                        userId: $this->userId,
                        type: AIJob::TYPE_STORE_AUTO_CONFIG,
                        inputData: [
                            'store_id' => $store->id,
                            'website_url' => $website,
                            'store_name' => $storeName,
                        ],
                    );
                    dispatch(new StoreAutoConfigJob($configJob->id, $this->userId));
                    $storesConfigured++; // Count queued jobs
                    $logs[] = "  → URL discovery queued";
                }

            } catch (\Exception $e) {
                Log::warning('NearbyStoreDiscoveryJob: Failed to create store', [
                    'store_name' => $storeName,
                    'error' => $e->getMessage(),
                ]);
                $logs[] = "  → Failed to add: " . $e->getMessage();
            }
        }

        $logs[] = "Discovery complete";
        $logs[] = "Summary:";
        $logs[] = "  - Found: {$storeCount} stores";
        $logs[] = "  - Added: {$storesAdded} new stores";
        $logs[] = "  - Skipped: {$storesSkipped} (already in registry)";
        $logs[] = "  - URL discovery queued: {$storesConfigured} stores";

        $this->updateProgress($aiJob, 95, $logs);

        Log::info('NearbyStoreDiscoveryJob: Completed', [
            'ai_job_id' => $aiJob->id,
            'user_id' => $this->userId,
            'stores_found' => $storeCount,
            'stores_added' => $storesAdded,
            'stores_configured' => $storesConfigured,
        ]);

        return [
            'stores_found' => $storeCount,
            'stores_added' => $storesAdded,
            'stores_skipped' => $storesSkipped,
            'stores_configured' => $storesConfigured,
            'added_store_ids' => $addedStoreIds,
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'radius_miles' => $radiusMiles,
            'categories' => $categories,
            'logs' => $logs,
        ];
    }

    /**
     * Ensure a user preference exists for a store.
     *
     * @param int $storeId
     */
    protected function ensureUserPreference(int $storeId): void
    {
        UserStorePreference::firstOrCreate(
            [
                'user_id' => $this->userId,
                'store_id' => $storeId,
            ],
            [
                'enabled' => true,
                'is_favorite' => false,
                'priority' => 50,
            ]
        );
    }

    /**
     * Create a user preference for a newly added store.
     *
     * @param int $storeId
     */
    protected function createUserPreference(int $storeId): void
    {
        UserStorePreference::create([
            'user_id' => $this->userId,
            'store_id' => $storeId,
            'enabled' => true,
            'is_favorite' => true, // Favorite newly discovered local stores
            'priority' => 70, // Higher priority for local stores
        ]);
    }

    /**
     * Get the tags for the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'ai-job',
            'nearby-store-discovery',
            'user:' . $this->userId,
            'ai_job_id:' . $this->aiJobId,
        ];
    }
}
