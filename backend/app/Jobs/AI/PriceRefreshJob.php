<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Services\AI\AIPriceSearchService;
use Illuminate\Support\Facades\Log;

/**
 * PriceRefreshJob
 * 
 * Background job for refreshing prices for a single item.
 * Uses SERP API for real-time pricing data.
 */
class PriceRefreshJob extends BaseAIJob
{
    /**
     * Process the price refresh job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $itemId = $aiJob->related_item_id;

        if (!$itemId) {
            throw new \RuntimeException('No item ID provided for price refresh.');
        }

        $item = ListItem::with('shoppingList')->find($itemId);

        if (!$item) {
            throw new \RuntimeException('Item not found.');
        }

        $this->updateProgress($aiJob, 10);

        // Create price search service
        $priceService = AIPriceSearchService::forUser($this->userId);
        $priceService->setAIJobId($aiJob->id);

        // Check if SERP API is available
        if (!$priceService->isWebSearchAvailable()) {
            throw new \RuntimeException('SERP API is not configured. Please set up a SerpAPI key in Settings.');
        }

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // Get user's home zip code for local searches
        $homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $this->userId);

        // Determine if shop_local is enabled
        $shopLocal = $item->shouldShopLocal();

        // Build search query
        $searchQuery = $item->product_query ?? $item->product_name;

        // Perform price search
        $searchResult = $priceService->search($searchQuery, [
            'is_generic' => $item->is_generic ?? false,
            'unit_of_measure' => $item->unit_of_measure,
            'shop_local' => $shopLocal,
            'zip_code' => $homeZipCode,
        ]);

        $this->updateProgress($aiJob, 70);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        if ($searchResult->hasError()) {
            return [
                'success' => false,
                'error' => $searchResult->error,
                'providers_used' => $searchResult->providersUsed,
            ];
        }

        if (!$searchResult->hasResults()) {
            return [
                'success' => false,
                'error' => 'No prices found for this item.',
                'providers_used' => $searchResult->providersUsed,
            ];
        }

        // Update vendor prices
        $lowestPrice = null;
        $lowestVendor = null;
        $resultsProcessed = 0;

        foreach ($searchResult->results as $result) {
            $vendor = ItemVendorPrice::normalizeVendor($result['retailer'] ?? 'Unknown');
            $price = (float) ($result['price'] ?? 0);

            if ($price <= 0) {
                continue;
            }

            // Find or create vendor price entry
            $vendorPrice = $item->vendorPrices()
                ->where('vendor', $vendor)
                ->first();

            if ($vendorPrice) {
                $vendorPrice->updatePrice($price, $result['url'] ?? null, true);
            } else {
                $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'product_url' => $result['url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $result['in_stock'] ?? true,
                    'last_checked_at' => now(),
                ]);
            }

            $resultsProcessed++;

            // Track lowest price
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
            }
        }

        $this->updateProgress($aiJob, 85);

        // Update main item with best price
        $previousPrice = $item->current_price;
        
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);

            // Capture price history
            PriceHistory::captureFromItem($item, 'user_refresh');
        }

        // Update generic info from search if not already set
        if ($searchResult->isGeneric && !$item->is_generic) {
            $item->update([
                'is_generic' => true,
                'unit_of_measure' => $searchResult->unitOfMeasure,
            ]);
        }

        $this->updateProgress($aiJob, 90);

        // Calculate price change
        $priceChange = null;
        $priceChangePercent = null;
        if ($previousPrice !== null && $lowestPrice !== null) {
            $priceChange = $lowestPrice - $previousPrice;
            $priceChangePercent = $previousPrice > 0 
                ? round(($priceChange / $previousPrice) * 100, 1)
                : null;
        }

        Log::info('PriceRefreshJob: Completed', [
            'item_id' => $itemId,
            'product_name' => $item->product_name,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_processed' => $resultsProcessed,
            'price_change' => $priceChange,
        ]);

        return [
            'success' => true,
            'item_id' => $itemId,
            'product_name' => $item->product_name,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'previous_price' => $previousPrice,
            'price_change' => $priceChange,
            'price_change_percent' => $priceChangePercent,
            'results_count' => $resultsProcessed,
            'providers_used' => $searchResult->providersUsed,
            'is_generic' => $searchResult->isGeneric,
            'unit_of_measure' => $searchResult->unitOfMeasure,
        ];
    }
}
