<?php

namespace App\Services\Deals;

use App\Models\ListItem;
use App\Models\ScannedDeal;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Collection;

class DealPricingService
{
    public function __construct(
        private readonly DealMatchingService $dealMatchingService,
    ) {}

    /**
     * Get the effective price for a list item after applying the best deal.
     *
     * Uses best-deal-only logic: picks the single deal with the highest discount.
     * Exception: if a deal has conditions.stackable === true, it stacks with the best non-stackable deal.
     *
     * @return array{base_price: float, best_deal: ?ScannedDeal, effective_price: float, total_savings: float, deals_applied: array}
     */
    public function getEffectivePrice(ListItem $item, User $user): array
    {
        $deals = ScannedDeal::where('user_id', $user->id)
            ->where('matched_list_item_id', $item->id)
            ->active()
            ->get();

        return $this->computeEffectivePriceFromDeals($item, $deals);
    }

    /**
     * Compute the discount amount for a deal given a base price.
     */
    public function computeDealDiscount(ScannedDeal $deal, float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        return match ($deal->discount_type) {
            'amount_off' => min((float) $deal->discount_value, $basePrice),
            'percent_off' => min($basePrice, $basePrice * (min(100, (float) $deal->discount_value) / 100)),
            'fixed_price' => $deal->sale_price !== null ? max(0, $basePrice - (float) $deal->sale_price) : 0,
            'bogo' => $basePrice * 0.5,
            'buy_x_get_y' => $this->computeBuyXGetYDiscount($deal, $basePrice),
            default => 0,
        };
    }

    /**
     * Select the deal producing the largest discount from a set of deals.
     */
    public function getBestDeal(array $deals, float $basePrice): ?ScannedDeal
    {
        if (empty($deals)) {
            return null;
        }

        $bestDeal = null;
        $bestDiscount = 0;

        foreach ($deals as $deal) {
            $discount = $this->computeDealDiscount($deal, $basePrice);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestDeal = $deal;
            }
        }

        return $bestDeal;
    }

    /**
     * Get deal savings summary for a shopping list.
     * Batch-loads all active deals for the user to avoid N+1 queries.
     *
     * @return array{total_savings: float, items_with_deals: int, deals_applied: int}
     */
    public function getListSavingsSummary(ShoppingList $list, User $user): array
    {
        $items = $list->items()->where('is_purchased', false)->get();
        $dealsByItem = $this->batchLoadDeals($user, $items);

        $totalSavings = 0;
        $itemsWithDeals = 0;
        $dealsApplied = 0;

        foreach ($items as $item) {
            $itemDeals = $dealsByItem->get($item->id, collect());
            $effective = $this->computeEffectivePriceFromDeals($item, $itemDeals);
            if ($effective['total_savings'] > 0) {
                $totalSavings += $effective['total_savings'];
                $itemsWithDeals++;
                $dealsApplied += count($effective['deals_applied']);
            }
        }

        return [
            'total_savings' => round($totalSavings, 2),
            'items_with_deals' => $itemsWithDeals,
            'deals_applied' => $dealsApplied,
        ];
    }

    /**
     * Get effective prices for all items in a list, keyed by item ID.
     * Batch-loads all active deals for the user to avoid N+1 queries.
     *
     * @return array<int, array>
     */
    public function getListEffectivePrices(ShoppingList $list, User $user): array
    {
        $items = $list->items()->where('is_purchased', false)->get();
        $dealsByItem = $this->batchLoadDeals($user, $items);

        $result = [];

        foreach ($items as $item) {
            $itemDeals = $dealsByItem->get($item->id, collect());
            $effective = $this->computeEffectivePriceFromDeals($item, $itemDeals);
            if ($effective['total_savings'] > 0) {
                $result[$item->id] = $effective;
            }
        }

        return $result;
    }

    /**
     * Batch-load all active deals for a user, grouped by matched_list_item_id.
     * Single query replaces N queries (one per item).
     */
    private function batchLoadDeals(User $user, Collection $items): Collection
    {
        $itemIds = $items->pluck('id')->all();

        if (empty($itemIds)) {
            return collect();
        }

        return ScannedDeal::where('user_id', $user->id)
            ->whereIn('matched_list_item_id', $itemIds)
            ->active()
            ->get()
            ->groupBy('matched_list_item_id');
    }

    /**
     * Core pricing computation given a list item and its pre-loaded deals.
     */
    private function computeEffectivePriceFromDeals(ListItem $item, Collection $deals): array
    {
        $basePrice = (float) ($item->current_price ?? 0);
        $empty = [
            'base_price' => $basePrice,
            'best_deal' => null,
            'effective_price' => $basePrice,
            'total_savings' => 0,
            'deals_applied' => [],
        ];

        if ($basePrice <= 0 || $deals->isEmpty()) {
            return $empty;
        }

        $stackable = $deals->filter(fn ($d) => ($d->conditions['stackable'] ?? false) === true);
        $nonStackable = $deals->reject(fn ($d) => ($d->conditions['stackable'] ?? false) === true);

        $bestDeal = $this->getBestDeal($nonStackable->values()->all(), $basePrice);

        $totalDiscount = 0;
        $appliedDeals = [];

        if ($bestDeal) {
            $discount = $this->computeDealDiscount($bestDeal, $basePrice);
            $totalDiscount += $discount;
            $appliedDeals[] = [
                'id' => $bestDeal->id,
                'type' => $bestDeal->deal_type,
                'discount_type' => $bestDeal->discount_type,
                'description' => $bestDeal->getDiscountDescription(),
                'discount_amount' => round($discount, 2),
            ];
        }

        foreach ($stackable as $deal) {
            $priceAfterPrevious = max(0, $basePrice - $totalDiscount);
            $discount = $this->computeDealDiscount($deal, $priceAfterPrevious);
            $totalDiscount += $discount;
            $appliedDeals[] = [
                'id' => $deal->id,
                'type' => $deal->deal_type,
                'discount_type' => $deal->discount_type,
                'description' => $deal->getDiscountDescription(),
                'discount_amount' => round($discount, 2),
            ];
        }

        $effectivePrice = round(max(0, $basePrice - $totalDiscount), 2);

        return [
            'base_price' => $basePrice,
            'best_deal' => $bestDeal,
            'effective_price' => $effectivePrice,
            'total_savings' => round($totalDiscount, 2),
            'deals_applied' => $appliedDeals,
        ];
    }

    private function computeBuyXGetYDiscount(ScannedDeal $deal, float $basePrice): float
    {
        $conditions = $deal->conditions ?? [];
        $buyQty = (int) ($conditions['buy_quantity'] ?? 2);
        $getQty = (int) ($conditions['get_quantity'] ?? 1);
        $totalQty = $buyQty + $getQty;

        return $totalQty > 0 ? $basePrice * ($getQty / $totalQty) : 0;
    }
}
