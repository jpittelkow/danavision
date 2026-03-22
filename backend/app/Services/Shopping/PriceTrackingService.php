<?php

namespace App\Services\Shopping;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Notifications\AllTimeLowNotification;
use App\Notifications\PriceDropNotification;
use App\Services\PriceSearch\PriceSearchService;
use App\Services\PriceSearch\UnitPriceNormalizer;
use App\Services\PriceSearch\VendorNameResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PriceTrackingService
{
    public function __construct(
        private readonly PriceSearchService $priceSearchService,
        private readonly ListItemService $listItemService,
        private readonly UnitPriceNormalizer $unitPriceNormalizer,
        private readonly VendorNameResolver $vendorNameResolver,
    ) {}

    /**
     * Refresh prices for a single item.
     *
     * Creates an AIJob, calls PriceSearchService, updates vendor prices,
     * creates price history records, and detects price drops.
     */
    public function refreshItem(ListItem $item): AIJob
    {
        $item->loadMissing('shoppingList.user');

        $aiJob = AIJob::create([
            'user_id' => $item->shoppingList->user_id,
            'type' => 'price_refresh',
            'status' => 'processing',
            'started_at' => now(),
            'related_item_id' => $item->id,
            'related_list_id' => $item->shopping_list_id,
            'input_data' => [
                'product_name' => $item->product_name,
                'product_query' => $item->product_query,
                'sku' => $item->sku,
                'upc' => $item->upc,
                'current_price' => $item->current_price,
                'current_retailer' => $item->current_retailer,
            ],
        ]);

        try {
            $results = $this->priceSearchService->searchPrices($item);

            if (empty($results)) {
                $aiJob->markCompleted(['results_count' => 0, 'message' => 'No results found']);
                $item->update(['last_checked_at' => now()]);
                return $aiJob->fresh();
            }

            $previousPrice = $item->current_price;
            $previousLowest = $item->lowest_price;

            $this->updateVendorPrices($item, $results);
            $bestPrice = $this->updateItemFromResults($item, $results);

            // Send notifications for price changes
            $item->refresh();
            $this->sendPriceNotifications($item, $previousPrice, $previousLowest, $bestPrice);

            $aiJob->markCompleted([
                'results_count' => count($results),
                'best_price' => $bestPrice,
            ]);
        } catch (\Exception $e) {
            Log::error('PriceTrackingService: refresh failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            $aiJob->markFailed($e->getMessage());
        }

        return $aiJob->fresh();
    }

    /**
     * Refresh prices for all items in a list.
     */
    public function refreshList(ShoppingList $list): array
    {
        $list->loadMissing('items.shoppingList.user');

        $jobs = [];

        foreach ($list->items as $item) {
            $jobs[] = $this->refreshItem($item);
        }

        return $jobs;
    }

    /**
     * Return items in a list that have dropped in price since last check.
     */
    public function checkPriceDrops(ShoppingList $list): Collection
    {
        return $list->items()
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->get();
    }

    /**
     * Return the lowest in-stock vendor price for an item, or null if none.
     */
    public function getBestVendorPrice(ListItem $item): ?ItemVendorPrice
    {
        return $item->vendorPrices()
            ->where('in_stock', true)
            ->whereNotNull('current_price')
            ->orderBy('current_price', 'asc')
            ->first();
    }

    /**
     * Update or create ItemVendorPrice records from search results.
     * Note: PriceHistory is created by ListItemService::updatePrice for the best result only,
     * avoiding duplicate history entries.
     */
    private function updateVendorPrices(ListItem $item, array $results): void
    {
        foreach ($results as $result) {
            $retailer = $result['retailer'] ?? '';
            if (empty($retailer)) {
                continue;
            }

            $price = $result['price'] ?? null;
            if ($price === null) {
                continue;
            }

            // Resolve vendor name to a Store record
            $storeId = $this->vendorNameResolver->resolveToId(
                $retailer,
                $result['url'] ?? null,
            );

            // Compute unit price from product name and package size
            $productName = $result['product_name'] ?? ($item->product_name ?? '');
            $packageSize = $result['package_size'] ?? null;
            $unitData = $this->unitPriceNormalizer->normalize($productName, $price, $packageSize);

            $vendorPrice = ItemVendorPrice::firstOrNew([
                'list_item_id' => $item->id,
                'vendor' => $retailer,
            ]);

            if ($vendorPrice->exists) {
                $previousPrice = $vendorPrice->current_price;
                $lowestPrice = $vendorPrice->lowest_price;
                $highestPrice = $vendorPrice->highest_price;

                if ($lowestPrice === null || $price < (float) $lowestPrice) {
                    $lowestPrice = $price;
                }
                if ($highestPrice === null || $price > (float) $highestPrice) {
                    $highestPrice = $price;
                }

                $onSale = $previousPrice !== null && $price < (float) $previousPrice;
                $salePercent = $onSale && (float) $previousPrice > 0
                    ? round((((float) $previousPrice - $price) / (float) $previousPrice) * 100, 2)
                    : null;

                $vendorPrice->fill([
                    'store_id' => $storeId ?? $vendorPrice->store_id,
                    'previous_price' => $previousPrice,
                    'current_price' => $price,
                    'unit_price' => $unitData['unit_price'],
                    'unit_quantity' => $unitData['unit_quantity'],
                    'unit_type' => $unitData['unit_type'],
                    'package_size' => $unitData['package_size'] ?? $vendorPrice->package_size,
                    'lowest_price' => $lowestPrice,
                    'highest_price' => $highestPrice,
                    'on_sale' => $onSale,
                    'sale_percent_off' => $salePercent,
                    'in_stock' => $result['in_stock'] ?? true,
                    'product_url' => $result['url'] ?? $vendorPrice->product_url,
                    'last_checked_at' => now(),
                ]);
            } else {
                $vendorPrice->fill([
                    'store_id' => $storeId,
                    'current_price' => $price,
                    'unit_price' => $unitData['unit_price'],
                    'unit_quantity' => $unitData['unit_quantity'],
                    'unit_type' => $unitData['unit_type'],
                    'package_size' => $unitData['package_size'],
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $result['in_stock'] ?? true,
                    'product_url' => $result['url'] ?? null,
                    'last_checked_at' => now(),
                ]);
            }

            $vendorPrice->save();
        }
    }

    /**
     * Update the item's price fields from the best result.
     */
    private function updateItemFromResults(ListItem $item, array $results): ?float
    {
        // Find the best (lowest in-stock) price
        $bestResult = null;
        $bestPrice = null;

        foreach ($results as $result) {
            $price = $result['price'] ?? null;
            $inStock = $result['in_stock'] ?? true;

            if ($price !== null && $inStock && ($bestPrice === null || $price < $bestPrice)) {
                $bestPrice = $price;
                $bestResult = $result;
            }
        }

        if ($bestResult && $bestPrice !== null) {
            $this->listItemService->updatePrice(
                $item,
                $bestPrice,
                $bestResult['retailer'] ?? 'Unknown',
                $bestResult['provider'] ?? 'unknown',
            );
        }

        $item->update(['last_checked_at' => now()]);

        return $bestPrice;
    }

    /**
     * Send price drop and all-time low notifications.
     */
    private function sendPriceNotifications(ListItem $item, ?string $previousPrice, ?string $previousLowest, ?float $bestPrice): void
    {
        if ($bestPrice === null || $previousPrice === null) {
            return;
        }

        $user = $item->shoppingList?->user;
        $list = $item->shoppingList;

        if (!$user || !$list) {
            return;
        }

        $prev = (float) $previousPrice;

        // Check for price drop
        if ($bestPrice < $prev) {
            $shouldNotify = $list->notify_on_any_drop;

            // Check threshold notification
            if (!$shouldNotify && $list->notify_on_threshold && $list->threshold_percent) {
                $dropPercent = (($prev - $bestPrice) / $prev) * 100;
                $shouldNotify = $dropPercent >= (float) $list->threshold_percent;
            }

            if ($shouldNotify) {
                try {
                    (new PriceDropNotification(
                        $item->product_name ?? 'Unknown',
                        $prev,
                        $bestPrice,
                        $list->name,
                        $list->id,
                        $item->id,
                    ))->send($user);
                } catch (\Exception $e) {
                    Log::warning('PriceTrackingService: failed to send price drop notification', [
                        'item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Check for all-time low
        if ($previousLowest !== null && $bestPrice < (float) $previousLowest) {
            try {
                (new AllTimeLowNotification(
                    $item->product_name ?? 'Unknown',
                    $bestPrice,
                    (float) $previousLowest,
                    $item->current_retailer ?? 'Unknown',
                    $list->name,
                    $list->id,
                    $item->id,
                ))->send($user);
            } catch (\Exception $e) {
                Log::warning('PriceTrackingService: failed to send all-time low notification', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
