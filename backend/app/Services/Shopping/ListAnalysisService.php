<?php

namespace App\Services\Shopping;

use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Deals\DealPricingService;
use Illuminate\Support\Collection;

class ListAnalysisService
{
    public function __construct(
        private readonly DealPricingService $dealPricingService,
    ) {}
    /**
     * Analyze a shopping list by store — returns per-store totals and comparisons.
     *
     * @return array{stores: array, cheapest_store: array|null, split_shopping: array}
     */
    public function analyzeByStore(ShoppingList $list, User $user): array
    {
        $list->load([
            'items' => function ($query) {
                $query->where('is_purchased', false);
            },
            'items.vendorPrices' => function ($query) {
                $query->whereNotNull('current_price')
                    ->where('in_stock', true)
                    ->orderBy('current_price', 'asc');
            },
            'items.vendorPrices.store',
        ]);

        $activeItems = $list->items;

        if ($activeItems->isEmpty()) {
            return $this->emptyResult();
        }

        // Build: storeKey → [ store_id, store_name, items[] ]
        $storeMap = [];

        foreach ($activeItems as $item) {
            foreach ($item->vendorPrices as $vp) {
                $storeKey = $vp->store_id
                    ? "store_{$vp->store_id}"
                    : "vendor_{$vp->vendor}";

                $storeName = $vp->store?->name ?? $vp->vendor ?? 'Unknown';
                $storeId   = $vp->store_id;

                if (!isset($storeMap[$storeKey])) {
                    $storeMap[$storeKey] = [
                        'store_id'   => $storeId,
                        'store_name' => $storeName,
                        'items'      => [],
                    ];
                }

                $storeMap[$storeKey]['items'][$item->id] = [
                    'item_id'      => $item->id,
                    'name'         => $item->product_name,
                    'price'        => (float) $vp->current_price,
                    'unit_price'   => $vp->unit_price !== null ? (float) $vp->unit_price : null,
                    'unit_quantity' => $vp->unit_quantity !== null ? (float) $vp->unit_quantity : null,
                    'unit_type'    => $vp->unit_type,
                    'package_size' => $vp->package_size,
                    'is_cheapest'  => false, // populated below
                ];
            }
        }

        $totalItemCount = $activeItems->count();

        // Find cheapest vendor price per item across all stores (for is_cheapest flag)
        $cheapestPerItem = $this->computeCheapestPerItem($activeItems);

        // Apply deal discounts to store map items
        $dealEffective = $this->dealPricingService->getListEffectivePrices($list, $user);

        foreach ($storeMap as $storeKey => &$entry) {
            foreach ($entry['items'] as $itemId => &$itemData) {
                if (isset($dealEffective[$itemId])) {
                    $eff = $dealEffective[$itemId];
                    $itemData['deal_discount'] = $eff['total_savings'];
                    $itemData['effective_price'] = $eff['effective_price'];
                    $itemData['deals'] = $eff['deals_applied'];
                }
            }
        }
        unset($entry, $itemData);

        // Compute per-store totals (use effective_price when available)
        $stores = [];
        foreach ($storeMap as $entry) {
            $itemsFound = count($entry['items']);
            $total      = 0.0;
            $items      = [];

            foreach ($entry['items'] as $itemData) {
                $price = $itemData['effective_price'] ?? $itemData['price'];
                $total += $price;
                $itemData['is_cheapest'] = isset($cheapestPerItem[$itemData['item_id']])
                    && $cheapestPerItem[$itemData['item_id']] == $itemData['price'];
                $items[] = $itemData;
            }

            $stores[] = [
                'store_id'         => $entry['store_id'],
                'store_name'       => $entry['store_name'],
                'total_cost'       => round($total, 2),
                'items_found'      => $itemsFound,
                'items_missing'    => $totalItemCount - $itemsFound,
                'coverage_percent' => $totalItemCount > 0
                    ? round(($itemsFound / $totalItemCount) * 100, 1)
                    : 0,
                'items'            => $items,
            ];
        }

        // Sort stores by total cost ascending (complete stores first)
        usort($stores, function ($a, $b) use ($totalItemCount) {
            // Penalise stores that are missing items
            $aComplete = $a['items_found'] === $totalItemCount ? 0 : 1;
            $bComplete = $b['items_found'] === $totalItemCount ? 0 : 1;
            if ($aComplete !== $bComplete) {
                return $aComplete - $bComplete;
            }
            return $a['total_cost'] <=> $b['total_cost'];
        });

        // Compute savings vs the most expensive complete store
        $completeCosts = array_column(
            array_filter($stores, fn($s) => $s['items_missing'] === 0),
            'total_cost'
        );
        $maxCompleteCost = !empty($completeCosts) ? max($completeCosts) : null;

        foreach ($stores as &$store) {
            $store['savings_vs_highest'] = $maxCompleteCost !== null
                ? round($maxCompleteCost - $store['total_cost'], 2)
                : null;
        }
        unset($store);

        $cheapestStore = !empty($stores) ? $stores[0] : null;

        return [
            'stores'         => $stores,
            'cheapest_store' => $cheapestStore,
            'split_shopping' => $this->computeBestSplit($activeItems, $storeMap),
            'total_items'    => $totalItemCount,
            'analyzed_at'    => now()->toIso8601String(),
        ];
    }

    /**
     * Compute the optimal split-shopping strategy:
     * for each item pick the cheapest store, then group by store.
     *
     * @return array{stores: array, total_cost: float, total_savings: float|null}
     */
    public function computeBestSplit(Collection $items, array $storeMap = []): array
    {
        // Rebuild a flat lookup: item_id → [{storeKey, storeName, storeId, price}]
        $itemOptions = [];
        foreach ($storeMap as $storeKey => $entry) {
            foreach ($entry['items'] as $itemId => $itemData) {
                $itemOptions[$itemId][] = [
                    'store_key'  => $storeKey,
                    'store_id'   => $entry['store_id'],
                    'store_name' => $entry['store_name'],
                    'price'      => $itemData['price'],
                    'item_name'  => $itemData['name'],
                    'unit_price' => $itemData['unit_price'],
                    'unit_type'  => $itemData['unit_type'],
                ];
            }
        }

        // For each item pick the cheapest option
        $splitPlan = []; // storeKey → {store info + items[]}
        $totalCost = 0.0;

        foreach ($items as $item) {
            $options = $itemOptions[$item->id] ?? [];
            if (empty($options)) {
                continue;
            }

            // Sort by price ascending, pick first
            usort($options, fn($a, $b) => $a['price'] <=> $b['price']);
            $best = $options[0];
            $totalCost += $best['price'];

            $sk = $best['store_key'];
            if (!isset($splitPlan[$sk])) {
                $splitPlan[$sk] = [
                    'store_id'   => $best['store_id'],
                    'store_name' => $best['store_name'],
                    'items'      => [],
                    'subtotal'   => 0.0,
                ];
            }
            $splitPlan[$sk]['items'][] = [
                'item_id'    => $item->id,
                'name'       => $item->product_name,
                'price'      => $best['price'],
                'unit_price' => $best['unit_price'],
                'unit_type'  => $best['unit_type'],
            ];
            $splitPlan[$sk]['subtotal'] += $best['price'];
        }

        // Round subtotals
        foreach ($splitPlan as &$plan) {
            $plan['subtotal'] = round($plan['subtotal'], 2);
        }
        unset($plan);

        // Compute savings vs cheapest single-store that covers all items
        $singleStoreCosts = [];
        foreach ($storeMap as $storeKey => $entry) {
            if (count($entry['items']) === $items->count()) {
                $singleStoreCosts[] = array_sum(
                    array_column($entry['items'], 'price')
                );
            }
        }
        $cheapestSingle  = !empty($singleStoreCosts) ? min($singleStoreCosts) : null;
        $totalSavings    = $cheapestSingle !== null
            ? round($cheapestSingle - $totalCost, 2)
            : null;

        return [
            'stores'        => array_values($splitPlan),
            'total_cost'    => round($totalCost, 2),
            'total_savings' => $totalSavings,
            'store_count'   => count($splitPlan),
        ];
    }

    /**
     * Returns the cheapest price per item_id across all vendor prices.
     */
    private function computeCheapestPerItem(Collection $items): array
    {
        $cheapest = [];

        foreach ($items as $item) {
            foreach ($item->vendorPrices as $vp) {
                $price = (float) $vp->current_price;
                if (!isset($cheapest[$item->id]) || $price < $cheapest[$item->id]) {
                    $cheapest[$item->id] = $price;
                }
            }
        }

        return $cheapest;
    }

    private function emptyResult(): array
    {
        return [
            'stores'         => [],
            'cheapest_store' => null,
            'split_shopping' => ['stores' => [], 'total_cost' => 0.0, 'total_savings' => null, 'store_count' => 0],
            'total_items'    => 0,
            'analyzed_at'    => now()->toIso8601String(),
        ];
    }
}
