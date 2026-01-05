<?php

namespace App\Http\Controllers;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with comprehensive analytics.
     *
     * Provides:
     * - Basic stats (lists, items, drops, savings)
     * - Recent price drops with item links
     * - All-time low items with links
     * - Store leaderboard (best prices)
     * - Active jobs status
     * - Last price update timestamp
     * - 7-day price trend data
     * - Items needing attention
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get user's lists (owned + shared)
        $listIds = $user->shoppingLists()->pluck('id')
            ->merge($user->sharedLists()->pluck('shopping_list_id'))
            ->unique();

        // Get items with recent price drops (with list info)
        $recentDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->with('shoppingList:id,name')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatItemForDashboard($item));

        // Get all-time low items (with list info)
        $allTimeLows = ListItem::whereIn('shopping_list_id', $listIds)
            ->with('shoppingList:id,name')
            ->whereNotNull('lowest_price')
            ->whereColumn('current_price', '<=', 'lowest_price')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatItemForDashboard($item));

        // Count total all-time lows
        $allTimeLowsCount = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('lowest_price')
            ->whereColumn('current_price', '<=', 'lowest_price')
            ->count();

        // Calculate basic stats
        $totalItems = ListItem::whereIn('shopping_list_id', $listIds)->count();
        $itemsWithDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->count();

        $potentialSavings = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->selectRaw('SUM(previous_price - current_price) as savings')
            ->value('savings') ?? 0;

        // Get store leaderboard - stores with most "best price" wins
        $storeStats = $this->getStoreLeaderboard($listIds);

        // Get active jobs count
        $activeJobsCount = AIJob::where('user_id', $user->id)
            ->active()
            ->count();

        // Get recent active jobs for display
        $activeJobs = AIJob::where('user_id', $user->id)
            ->active()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => $job->type,
                'type_label' => $job->type_label,
                'status' => $job->status,
                'progress' => $job->progress,
                'input_summary' => $job->input_summary,
                'created_at' => $job->created_at->toISOString(),
            ]);

        // Get last price update timestamp
        $lastPriceUpdate = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('last_checked_at')
            ->max('last_checked_at');

        // Get 7-day price trend data
        $priceTrend = $this->getPriceTrendData($listIds);

        // Get items needing attention (not checked in 7+ days)
        $itemsNeedingAttention = ListItem::whereIn('shopping_list_id', $listIds)
            ->with('shoppingList:id,name')
            ->where('is_purchased', false)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subDays(7));
            })
            ->orderBy('last_checked_at')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatItemForDashboard($item));

        // Get items below target price
        $itemsBelowTarget = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('target_price')
            ->whereNotNull('current_price')
            ->whereColumn('current_price', '<=', 'target_price')
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'lists_count' => $listIds->count(),
                'items_count' => $totalItems,
                'items_with_drops' => $itemsWithDrops,
                'total_potential_savings' => round($potentialSavings, 2),
                'all_time_lows_count' => $allTimeLowsCount,
                'items_below_target' => $itemsBelowTarget,
            ],
            'recent_drops' => $recentDrops,
            'all_time_lows' => $allTimeLows,
            'store_stats' => $storeStats,
            'active_jobs_count' => $activeJobsCount,
            'active_jobs' => $activeJobs,
            'last_price_update' => $lastPriceUpdate,
            'price_trend' => $priceTrend,
            'items_needing_attention' => $itemsNeedingAttention,
        ]);
    }

    /**
     * Format a list item for dashboard display.
     *
     * @param ListItem $item
     * @return array
     */
    protected function formatItemForDashboard(ListItem $item): array
    {
        $priceChangePercent = null;
        if ($item->previous_price && $item->previous_price > 0 && $item->current_price) {
            $priceChangePercent = (($item->previous_price - $item->current_price) / $item->previous_price) * 100;
        }

        return [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'product_image_url' => $item->getDisplayImageUrl(),
            'current_price' => $item->current_price,
            'previous_price' => $item->previous_price,
            'lowest_price' => $item->lowest_price,
            'target_price' => $item->target_price,
            'price_change_percent' => $priceChangePercent ? round($priceChangePercent, 1) : null,
            'is_at_all_time_low' => $item->isAtAllTimeLow(),
            'last_checked_at' => $item->last_checked_at?->toISOString(),
            'list' => $item->shoppingList ? [
                'id' => $item->shoppingList->id,
                'name' => $item->shoppingList->name,
            ] : null,
        ];
    }

    /**
     * Get store leaderboard - stores ranked by number of "best price" wins.
     *
     * @param \Illuminate\Support\Collection $listIds
     * @return array
     */
    protected function getStoreLeaderboard($listIds): array
    {
        // Get all items with their vendor prices
        $itemIds = ListItem::whereIn('shopping_list_id', $listIds)->pluck('id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        // For each item, find the vendor with the lowest current price
        // Then count how many times each vendor has the best price
        $vendorPrices = ItemVendorPrice::whereIn('list_item_id', $itemIds)
            ->whereNotNull('current_price')
            ->where('in_stock', true)
            ->get()
            ->groupBy('list_item_id');

        $vendorWins = [];
        $vendorTotalSavings = [];

        foreach ($vendorPrices as $itemPrices) {
            if ($itemPrices->isEmpty()) continue;

            // Find minimum price for this item
            $minPrice = $itemPrices->min('current_price');
            $maxPrice = $itemPrices->max('current_price');

            // Find all vendors with that price (could be ties)
            $winners = $itemPrices->where('current_price', $minPrice);

            foreach ($winners as $winner) {
                $vendor = $winner->vendor;
                $vendorWins[$vendor] = ($vendorWins[$vendor] ?? 0) + 1;

                // Calculate potential savings vs highest price
                if ($maxPrice > $minPrice) {
                    $vendorTotalSavings[$vendor] = ($vendorTotalSavings[$vendor] ?? 0) + ($maxPrice - $minPrice);
                }
            }
        }

        // Sort by wins descending
        arsort($vendorWins);

        // Format for frontend
        $result = [];
        foreach (array_slice($vendorWins, 0, 6, true) as $vendor => $wins) {
            $result[] = [
                'vendor' => $vendor,
                'wins' => $wins,
                'total_savings' => round($vendorTotalSavings[$vendor] ?? 0, 2),
            ];
        }

        return $result;
    }

    /**
     * Get 7-day price trend data for charting.
     *
     * @param \Illuminate\Support\Collection $listIds
     * @return array
     */
    protected function getPriceTrendData($listIds): array
    {
        $itemIds = ListItem::whereIn('shopping_list_id', $listIds)->pluck('id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        $sevenDaysAgo = now()->subDays(7)->startOfDay();

        // Get price history grouped by date
        $history = PriceHistory::whereIn('list_item_id', $itemIds)
            ->where('captured_at', '>=', $sevenDaysAgo)
            ->orderBy('captured_at')
            ->get()
            ->groupBy(fn ($h) => $h->captured_at->format('Y-m-d'));

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayHistory = $history->get($date);

            if ($dayHistory) {
                $trend[] = [
                    'date' => $date,
                    'avg_price' => round($dayHistory->avg('price'), 2),
                    'min_price' => round($dayHistory->min('price'), 2),
                    'max_price' => round($dayHistory->max('price'), 2),
                    'count' => $dayHistory->count(),
                ];
            } else {
                $trend[] = [
                    'date' => $date,
                    'avg_price' => null,
                    'min_price' => null,
                    'max_price' => null,
                    'count' => 0,
                ];
            }
        }

        return $trend;
    }
}
