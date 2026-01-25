<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Services\Crawler\StoreDiscoveryService;
use Illuminate\Support\Facades\Log;

/**
 * FirecrawlRefreshJob (now powered by Crawl4AI)
 *
 * Background job for refreshing product prices using Crawl4AI.
 * Uses known product URLs to get updated prices without re-discovering stores.
 *
 * This job:
 * 1. Collects all known product URLs from item_vendor_prices
 * 2. Uses Crawl4AI to scrape URLs + AI to extract prices (free scraping)
 * 3. Updates the item_vendor_prices table with new prices
 * 4. Triggers notifications if price drops are detected
 *
 * @see docs/adr/016-crawl4ai-integration.md
 */
class FirecrawlRefreshJob extends BaseAIJob
{
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Process the Firecrawl refresh job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $itemId = $aiJob->related_item_id;
        $logs = [];

        $logs[] = "Starting price refresh job";
        $this->updateProgress($aiJob, 10, $logs);

        // Create StoreDiscovery service (uses Crawl4AI)
        $discoveryService = StoreDiscoveryService::forUser($this->userId);

        // Check if service is available (Crawl4AI + AI provider)
        if (!$discoveryService->isAvailable()) {
            throw new \RuntimeException('Price refresh not available. Please ensure AI provider is configured in Settings.');
        }

        $logs[] = "StoreDiscovery service initialized (Crawl4AI mode)";
        $this->updateProgress($aiJob, 20, $logs);

        // Get the item and its vendor prices with URLs
        $item = null;
        $vendorPrices = collect();

        if ($itemId) {
            $item = ListItem::with('vendorPrices')->find($itemId);
            if ($item) {
                $vendorPrices = $item->vendorPrices
                    ->filter(fn ($vp) => !empty($vp->product_url));
                $logs[] = "Found product: {$item->product_name}";
            }
        }

        if ($vendorPrices->isEmpty()) {
            $logs[] = "No product URLs found to refresh";
            Log::info('FirecrawlRefreshJob: No URLs to refresh', ['item_id' => $itemId]);
            return [
                'message' => 'No product URLs found to refresh',
                'urls_checked' => 0,
                'prices_updated' => 0,
                'logs' => $logs,
            ];
        }

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Collect URLs to scrape
        $urls = $vendorPrices->pluck('product_url')->toArray();
        $logs[] = "Found " . count($urls) . " URLs to check";
        
        // List vendors
        $vendors = $vendorPrices->pluck('vendor')->toArray();
        $logs[] = "Stores: " . implode(', ', array_slice($vendors, 0, 5));

        Log::info('FirecrawlRefreshJob: Starting URL refresh', [
            'ai_job_id' => $aiJob->id,
            'item_id' => $itemId,
            'urls_count' => count($urls),
        ]);

        $logs[] = "Scraping URLs with Crawl4AI...";
        $this->updateProgress($aiJob, 30, $logs);

        // Perform Crawl4AI scraping with AI price extraction
        $productName = $item?->product_name ?? $inputData['product_name'] ?? null;
        $result = $discoveryService->refreshPrices($urls, $productName);

        $logs[] = "Received response from Crawl4AI";
        $this->updateProgress($aiJob, 70, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Update prices from results
        $pricesUpdated = 0;
        $priceDrops = [];

        if ($result->isSuccess() && $result->hasResults()) {
            $logs[] = "Processing {$result->count()} price results...";
            
            foreach ($result->results as $priceResult) {
                $url = $priceResult['product_url'] ?? null;
                if (!$url) {
                    continue;
                }

                // Find the vendor price record for this URL
                $vendorPrice = $vendorPrices->first(fn ($vp) => $vp->product_url === $url);
                if (!$vendorPrice) {
                    continue;
                }

                $newPrice = (float) ($priceResult['price'] ?? 0);
                if ($newPrice <= 0) {
                    continue;
                }

                // Track price drop
                $oldPrice = $vendorPrice->current_price;
                if ($oldPrice && $newPrice < $oldPrice) {
                    $priceDrops[] = [
                        'vendor' => $vendorPrice->vendor,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'drop_amount' => $oldPrice - $newPrice,
                        'drop_percent' => (($oldPrice - $newPrice) / $oldPrice) * 100,
                    ];
                    $logs[] = "Price drop at {$vendorPrice->vendor}: \${$oldPrice} → \${$newPrice}";
                }

                // Determine stock status
                $inStock = ($priceResult['stock_status'] ?? 'in_stock') !== 'out_of_stock';

                // Update the vendor price
                $vendorPrice->updatePrice($newPrice, $url, $inStock);
                $vendorPrice->update([
                    'last_firecrawl_at' => now(),
                    'firecrawl_source' => 'crawl4ai_refresh',
                ]);

                $pricesUpdated++;
            }
        } else if ($result->error) {
            $logs[] = "ERROR: " . $result->error;
        }

        $logs[] = "Updated {$pricesUpdated} prices";
        $this->updateProgress($aiJob, 85, $logs);

        // Update the main item with the best price
        if ($item && $pricesUpdated > 0) {
            $this->updateItemBestPrice($item);
            $logs[] = "Updated best price for item";
        }

        if (count($priceDrops) > 0) {
            $logs[] = "Found " . count($priceDrops) . " price drop(s)!";
        }

        $logs[] = "Price refresh completed";
        $this->updateProgress($aiJob, 95, $logs);

        Log::info('FirecrawlRefreshJob: Completed', [
            'ai_job_id' => $aiJob->id,
            'item_id' => $itemId,
            'urls_checked' => count($urls),
            'prices_updated' => $pricesUpdated,
            'price_drops' => count($priceDrops),
        ]);

        return [
            'product_name' => $item?->product_name ?? $inputData['product_name'] ?? null,
            'urls_checked' => count($urls),
            'prices_updated' => $pricesUpdated,
            'price_drops' => $priceDrops,
            'error' => $result->error,
            'logs' => $logs,
        ];
    }

    /**
     * Update the main item with the best (lowest) price from vendor prices.
     *
     * @param ListItem $item The list item
     */
    protected function updateItemBestPrice(ListItem $item): void
    {
        $item->refresh();
        $item->load('vendorPrices');

        // Find the best price among in-stock vendors
        $bestVendorPrice = $item->vendorPrices
            ->filter(fn ($vp) => $vp->in_stock && $vp->current_price > 0)
            ->sortBy('current_price')
            ->first();

        if (!$bestVendorPrice) {
            // Fall back to any vendor with a price
            $bestVendorPrice = $item->vendorPrices
                ->filter(fn ($vp) => $vp->current_price > 0)
                ->sortBy('current_price')
                ->first();
        }

        if ($bestVendorPrice) {
            $item->updatePrice($bestVendorPrice->current_price, $bestVendorPrice->vendor);
            
            // Update product URL if the item doesn't have one
            if (empty($item->product_url) && !empty($bestVendorPrice->product_url)) {
                $item->update(['product_url' => $bestVendorPrice->product_url]);
            }

            // Capture price history
            PriceHistory::captureFromItem($item, 'crawl4ai_refresh');
        }
    }

    /**
     * Schedule daily refresh for all users with Firecrawl configured.
     * Called from the scheduler to refresh prices at user-configured times.
     */
    public static function scheduleForUsers(): void
    {
        $currentTime = now()->format('H:i');

        // Find users whose price_check_time matches the current time
        $settings = Setting::where('key', Setting::PRICE_CHECK_TIME)
            ->where('value', $currentTime)
            ->get();

        foreach ($settings as $setting) {
            if (!$setting->user_id) {
                continue;
            }

            self::scheduleForUser($setting->user_id);
        }

        // Also check for users who haven't set a time (default 3:00 AM)
        if ($currentTime === '03:00') {
            $usersWithCustomTime = Setting::where('key', Setting::PRICE_CHECK_TIME)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $userIds = ListItem::where('is_purchased', false)
                ->join('shopping_lists', 'list_items.shopping_list_id', '=', 'shopping_lists.id')
                ->select('shopping_lists.user_id')
                ->distinct()
                ->whereNotIn('shopping_lists.user_id', $usersWithCustomTime)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                self::scheduleForUser($userId);
            }
        }
    }

    /**
     * Schedule refresh jobs for a specific user.
     *
     * @param int $userId The user ID
     */
    public static function scheduleForUser(int $userId): void
    {
        // Check if user has price refresh available (Crawl4AI + AI provider)
        $discoveryService = StoreDiscoveryService::forUser($userId);

        if (!$discoveryService->isAvailable()) {
            Log::info('FirecrawlRefreshJob: Skipping user without AI provider', ['user_id' => $userId]);
            return;
        }

        // Get all unpurchased items with vendor prices that have URLs
        $items = ListItem::with(['vendorPrices', 'shoppingList'])
            ->whereHas('shoppingList', fn ($q) => $q->where('user_id', $userId))
            ->where('is_purchased', false)
            ->whereHas('vendorPrices', fn ($q) => $q->whereNotNull('product_url'))
            ->get();

        if ($items->isEmpty()) {
            Log::info('FirecrawlRefreshJob: No items to refresh for user', ['user_id' => $userId]);
            return;
        }

        // Dispatch refresh job for each item with a delay to spread load
        $delay = rand(0, 300); // Random initial delay up to 5 minutes
        
        foreach ($items as $item) {
            $aiJob = AIJob::createJob(
                userId: $userId,
                type: AIJob::TYPE_FIRECRAWL_REFRESH,
                inputData: [
                    'product_name' => $item->product_name,
                    'source' => 'daily_refresh',
                ],
                relatedItemId: $item->id,
                relatedListId: $item->shopping_list_id,
            );

            dispatch(new self($aiJob->id, $userId))
                ->delay(now()->addSeconds($delay));
            
            $delay += rand(10, 30); // Space out jobs

            Log::info('FirecrawlRefreshJob: Scheduled daily refresh', [
                'user_id' => $userId,
                'item_id' => $item->id,
                'product' => $item->product_name,
            ]);
        }
    }
}
