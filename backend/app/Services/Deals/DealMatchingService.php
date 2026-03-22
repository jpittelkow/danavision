<?php

namespace App\Services\Deals;

use App\Models\ListItem;
use App\Models\ScannedDeal;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DealMatchingService
{
    /**
     * Minimum Jaccard similarity threshold for fuzzy matching.
     */
    private const MATCH_THRESHOLD = 0.5;

    /**
     * Auto-match a deal to the user's list items.
     *
     * Strategy: exact name match → token overlap (Jaccard) → UPC match
     */
    public function matchDealToItems(ScannedDeal $deal, User $user): ?ListItem
    {
        $items = ListItem::whereHas('shoppingList', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_purchased', false)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $dealName = strtolower(trim($deal->product_name));

        // 1. Exact name match (case-insensitive)
        $exact = $items->first(fn ($item) => strtolower(trim($item->product_name)) === $dealName);
        if ($exact) {
            return $this->applyMatch($deal, $exact);
        }

        // 2. Contains match (deal product name contains item name or vice versa)
        $contains = $items->first(function ($item) use ($dealName) {
            $itemName = strtolower(trim($item->product_name));
            return Str::contains($dealName, $itemName) || Str::contains($itemName, $dealName);
        });
        if ($contains) {
            return $this->applyMatch($deal, $contains);
        }

        // 3. Token overlap (Jaccard similarity)
        $dealTokens = $this->tokenize($dealName);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($items as $item) {
            $itemTokens = $this->tokenize(strtolower(trim($item->product_name)));
            $score = $this->jaccardSimilarity($dealTokens, $itemTokens);

            if ($score >= self::MATCH_THRESHOLD && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        if ($bestMatch) {
            return $this->applyMatch($deal, $bestMatch);
        }

        // 4. UPC match (if deal conditions include UPC)
        $dealConditions = $deal->conditions ?? [];
        $dealUpc = $dealConditions['upc'] ?? null;

        if ($dealUpc) {
            $upcMatch = $items->first(fn ($item) => $item->upc === $dealUpc);
            if ($upcMatch) {
                return $this->applyMatch($deal, $upcMatch);
            }
        }

        return null;
    }

    /**
     * Match all active deals to items in a shopping list.
     *
     * @return array<int, ScannedDeal[]> Map of list_item_id => matched deals
     */
    public function matchAllDealsToList(ShoppingList $list): array
    {
        $user = $list->user;
        $items = $list->items()->where('is_purchased', false)->get();
        $deals = ScannedDeal::where('user_id', $user->id)->active()->get();

        $matches = [];

        foreach ($items as $item) {
            $itemName = strtolower(trim($item->product_name));
            $itemTokens = $this->tokenize($itemName);

            foreach ($deals as $deal) {
                $dealName = strtolower(trim($deal->product_name));

                $isMatch = $itemName === $dealName
                    || Str::contains($dealName, $itemName)
                    || Str::contains($itemName, $dealName)
                    || $this->jaccardSimilarity($itemTokens, $this->tokenize($dealName)) >= self::MATCH_THRESHOLD
                    || ($item->upc && $item->upc === ($deal->conditions['upc'] ?? null));

                if ($isMatch) {
                    $matches[$item->id][] = $deal;
                }
            }
        }

        return $matches;
    }

    /**
     * Manually match a deal to a list item.
     */
    public function manualMatch(ScannedDeal $deal, ListItem $item): ScannedDeal
    {
        $deal->update(['matched_list_item_id' => $item->id]);

        Log::info('DealMatchingService: manual match', [
            'deal_id' => $deal->id,
            'item_id' => $item->id,
        ]);

        return $deal->fresh(['store', 'matchedItem']);
    }

    /**
     * Remove a deal-to-item match.
     */
    public function unmatch(ScannedDeal $deal): ScannedDeal
    {
        $deal->update(['matched_list_item_id' => null]);

        Log::info('DealMatchingService: unmatched', ['deal_id' => $deal->id]);

        return $deal->fresh(['store', 'matchedItem']);
    }

    private function applyMatch(ScannedDeal $deal, ListItem $item): ListItem
    {
        $deal->update(['matched_list_item_id' => $item->id]);

        Log::info('DealMatchingService: auto-matched', [
            'deal_id' => $deal->id,
            'deal_product' => $deal->product_name,
            'item_id' => $item->id,
            'item_product' => $item->product_name,
        ]);

        return $item;
    }

    /**
     * Tokenize a string into words, removing common stop words.
     *
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $words = preg_split('/[\s\-\/,]+/', $text);
        $stopWords = ['the', 'a', 'an', 'of', 'for', 'and', 'or', 'with', 'in'];

        return array_values(array_filter(
            array_map('trim', $words),
            fn ($w) => strlen($w) > 1 && !in_array($w, $stopWords)
        ));
    }

    /**
     * Compute Jaccard similarity between two sets of tokens.
     */
    private function jaccardSimilarity(array $a, array $b): float
    {
        if (empty($a) && empty($b)) {
            return 0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0;
    }
}
