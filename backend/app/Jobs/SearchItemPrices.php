<?php

namespace App\Jobs;

use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\AI\AIPriceSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to search for prices after an item is added to a shopping list.
 * 
 * This is Phase 2 of the Smart Add flow - price search happens
 * asynchronously after the user has added the item.
 */
class SearchItemPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120; // 2 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $itemId,
        public int $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $item = ListItem::with('shoppingList')->find($this->itemId);

        if (!$item) {
            Log::warning("SearchItemPrices: Item {$this->itemId} not found");
            return;
        }

        if ($item->is_purchased) {
            Log::info("SearchItemPrices: Skipping purchased item {$this->itemId}");
            return;
        }

        Log::info("SearchItemPrices: Starting price search for item {$this->itemId}: {$item->product_name}");

        try {
            $this->searchAndUpdatePrices($item);
        } catch (\Exception $e) {
            Log::error("SearchItemPrices: Error searching prices for item {$this->itemId}: " . $e->getMessage());
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Search for prices and update the item.
     */
    protected function searchAndUpdatePrices(ListItem $item): void
    {
        $priceService = AIPriceSearchService::forUser($this->userId);

        if (!$priceService->isAvailable()) {
            Log::warning("SearchItemPrices: No AI provider available for user {$this->userId}");
            return;
        }

        // Build search query - prioritize UPC for accuracy
        $searchQuery = $item->product_query ?? $item->product_name;
        if (!empty($item->upc)) {
            $searchQuery = $item->upc . ' ' . $searchQuery;
        }

        // Perform the price search
        $searchResult = $priceService->search($searchQuery, [
            'is_generic' => $item->is_generic ?? false,
            'unit_of_measure' => $item->unit_of_measure ?? null,
        ]);

        if ($searchResult->error) {
            Log::warning("SearchItemPrices: Search error for item {$item->id}: {$searchResult->error}");
            return;
        }

        if (empty($searchResult->results)) {
            Log::info("SearchItemPrices: No results found for item {$item->id}: {$item->product_name}");
            return;
        }

        $lowestPrice = null;
        $lowestVendor = null;
        $lowestUrl = null;
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
                    'vendor_sku' => null,
                    'product_url' => $result['url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $result['in_stock'] ?? true,
                    'last_checked_at' => now(),
                ]);
            }

            $resultsProcessed++;

            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
                $lowestUrl = $result['url'] ?? null;
            }
        }

        // Update the main item with the lowest price found
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);
            
            // Update product URL if we found a better one
            if ($lowestUrl && empty($item->product_url)) {
                $item->update(['product_url' => $lowestUrl]);
            }

            // Update product image URL if missing and we found one
            if (empty($item->product_image_url)) {
                foreach ($searchResult->results as $result) {
                    if (!empty($result['image_url'])) {
                        $item->update(['product_image_url' => $result['image_url']]);
                        break;
                    }
                }
            }

            // Capture price history (using 'daily_job' as source since both are automated background processes)
            PriceHistory::captureFromItem($item, 'daily_job');
        }

        Log::info("SearchItemPrices: Completed for item {$item->id}. Found {$resultsProcessed} prices. Lowest: \${$lowestPrice} at {$lowestVendor}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SearchItemPrices: Job failed for item {$this->itemId}: " . $exception->getMessage());
    }
}
