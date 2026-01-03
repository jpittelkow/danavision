<?php

namespace App\Jobs;

use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\PriceApi\PriceApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DailyPriceCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $userId = null,
        public ?int $listId = null,
        public ?int $itemId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // If a specific item is provided, just check that item
        if ($this->itemId) {
            $this->checkSingleItem($this->itemId);
            return;
        }

        // If a specific list is provided, check all items in that list
        if ($this->listId && $this->userId) {
            $this->checkList($this->userId, $this->listId);
            return;
        }

        // If a specific user is provided, check all their items
        if ($this->userId) {
            $this->checkUserItems($this->userId);
            return;
        }

        // Otherwise, check all users' items
        $this->checkAllUsers();
    }

    /**
     * Check prices for a single item.
     */
    protected function checkSingleItem(int $itemId): void
    {
        $item = ListItem::with('shoppingList')->find($itemId);
        
        if (!$item || $item->is_purchased) {
            return;
        }

        $userId = $item->shoppingList->user_id;
        $this->refreshItemPrice($item, $userId);
    }

    /**
     * Check prices for all items in a list.
     */
    protected function checkList(int $userId, int $listId): void
    {
        $items = ListItem::whereHas('shoppingList', function ($q) use ($listId) {
            $q->where('id', $listId);
        })
            ->where('is_purchased', false)
            ->get();

        foreach ($items as $item) {
            $this->refreshItemPrice($item, $userId);
        }
    }

    /**
     * Check prices for all items belonging to a user.
     */
    protected function checkUserItems(int $userId): void
    {
        $items = ListItem::whereHas('shoppingList', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('is_purchased', false)
            ->get();

        Log::info("DailyPriceCheck: Checking {$items->count()} items for user {$userId}");

        foreach ($items as $item) {
            $this->refreshItemPrice($item, $userId);
        }
    }

    /**
     * Check prices for all users who have configured price checking.
     */
    protected function checkAllUsers(): void
    {
        // Get all users who have at least one unpurchased item
        $userIds = ListItem::where('is_purchased', false)
            ->join('shopping_lists', 'list_items.shopping_list_id', '=', 'shopping_lists.id')
            ->select('shopping_lists.user_id')
            ->distinct()
            ->pluck('user_id');

        Log::info("DailyPriceCheck: Starting daily price check for {$userIds->count()} users");

        foreach ($userIds as $userId) {
            // Check if user has a price API configured
            $priceService = PriceApiService::forUser($userId);
            
            if (!$priceService->isAvailable()) {
                Log::info("DailyPriceCheck: Skipping user {$userId} - no price API configured");
                continue;
            }

            // Dispatch a separate job for each user to distribute load
            dispatch(new self(userId: $userId))->delay(now()->addSeconds(rand(0, 300)));
        }
    }

    /**
     * Refresh price for a single item.
     */
    protected function refreshItemPrice(ListItem $item, int $userId): void
    {
        try {
            $priceService = PriceApiService::forUser($userId);
            
            if (!$priceService->isAvailable()) {
                return;
            }

            $searchQuery = $item->product_query ?? $item->product_name;
            $searchResult = $priceService->searchWithCache($searchQuery, 'product', 600); // 10 min cache

            if ($searchResult->hasError() || !$searchResult->hasResults()) {
                Log::warning("DailyPriceCheck: No results for item {$item->id}: {$item->product_name}");
                return;
            }

            $lowestPrice = null;
            $lowestVendor = null;

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
                        'in_stock' => true,
                        'last_checked_at' => now(),
                    ]);
                }

                if ($lowestPrice === null || $price < $lowestPrice) {
                    $lowestPrice = $price;
                    $lowestVendor = $vendor;
                }
            }

            // Update main item with best price
            if ($lowestPrice !== null) {
                $item->updatePrice($lowestPrice, $lowestVendor);
                PriceHistory::captureFromItem($item, 'daily_job');
            }

            Log::info("DailyPriceCheck: Updated item {$item->id}: {$item->product_name} - \${$lowestPrice}");

        } catch (\Exception $e) {
            Log::error("DailyPriceCheck: Error checking item {$item->id}: " . $e->getMessage());
        }
    }

    /**
     * Schedule the daily price check.
     * Called from the scheduler to check if now is the right time for each user.
     */
    public static function scheduleForUsers(): void
    {
        $currentTime = now()->format('H:i');

        // Find users whose price_check_time matches the current time
        $settings = Setting::where('key', Setting::PRICE_CHECK_TIME)
            ->where('value', $currentTime)
            ->get();

        foreach ($settings as $setting) {
            if ($setting->user_id) {
                dispatch(new self(userId: $setting->user_id));
                Log::info("DailyPriceCheck: Dispatched job for user {$setting->user_id} at {$currentTime}");
            }
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
                dispatch(new self(userId: $userId))->delay(now()->addSeconds(rand(0, 300)));
                Log::info("DailyPriceCheck: Dispatched default job for user {$userId}");
            }
        }
    }
}
