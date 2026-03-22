<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ListItem;
use App\Models\ListShare;
use App\Models\PriceHistory;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get dashboard stats for the stats widget.
     */
    public function stats(Request $request): JsonResponse
    {
        $storagePath = storage_path();
        $diskTotal = disk_total_space($storagePath);
        $diskFree = disk_free_space($storagePath);
        $diskTotal = $diskTotal !== false ? (int) $diskTotal : 0;
        $diskFree = $diskFree !== false ? (int) $diskFree : 0;
        $storageUsed = $diskTotal - $diskFree;

        $metrics = [
            ['label' => 'Total Users', 'value' => User::count()],
            ['label' => 'Storage Used', 'value' => FileHelper::formatBytes($storageUsed)],
        ];

        return response()->json(['metrics' => $metrics]);
    }

    /**
     * Get environment info for the environment widget.
     */
    public function environment(): JsonResponse
    {
        return response()->json([
            'environment' => config('app.env', 'production'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => config('database.default'),
        ]);
    }

    /**
     * Get shopping-related stats for the dashboard.
     */
    public function shoppingStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $listIds = ShoppingList::where('user_id', $user->id)->pluck('id');

        $totalLists = $listIds->count();
        $totalItems = ListItem::whereIn('shopping_list_id', $listIds)->count();

        // Price drops: items where current < previous
        $priceDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->count();

        // All-time lows: items at their lowest price ever
        $allTimeLows = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('lowest_price')
            ->whereColumn('current_price', '<=', 'lowest_price')
            ->where('current_price', '>', 0)
            ->count();

        // Total savings: sum of (highest - current) for items with prices
        $savingsItems = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('highest_price')
            ->whereColumn('highest_price', '>', 'current_price')
            ->get();

        $totalSavings = $savingsItems->sum(function ($item) {
            return (float) $item->highest_price - (float) $item->current_price;
        });

        // Items below target price
        $belowTarget = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('target_price')
            ->whereColumn('current_price', '<=', 'target_price')
            ->count();

        // Items needing refresh (not checked in 24+ hours)
        $needsRefresh = ListItem::whereIn('shopping_list_id', $listIds)
            ->where('is_purchased', false)
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subHours(24));
            })
            ->count();

        // Pending shares
        $pendingShares = ListShare::where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->count();

        // Recent price drops (last 5 with details)
        $recentDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->with('shoppingList:id,name')
            ->orderBy('last_checked_at', 'desc')
            ->limit(5)
            ->get(['id', 'shopping_list_id', 'product_name', 'current_price', 'previous_price', 'current_retailer', 'last_checked_at']);

        // 7-day price activity (daily count of price history entries)
        $sevenDayActivity = PriceHistory::whereIn('list_item_id', function ($q) use ($listIds) {
            $q->select('id')->from('list_items')->whereIn('shopping_list_id', $listIds);
        })
            ->where('captured_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(captured_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Store leaderboard (top stores by savings)
        $storeLeaderboard = DB::table('item_vendor_prices')
            ->join('list_items', 'item_vendor_prices.list_item_id', '=', 'list_items.id')
            ->whereIn('list_items.shopping_list_id', $listIds)
            ->whereNotNull('item_vendor_prices.current_price')
            ->select(
                'item_vendor_prices.vendor',
                DB::raw('COUNT(*) as items_count'),
                DB::raw('MIN(item_vendor_prices.current_price) as lowest_price'),
                DB::raw('AVG(item_vendor_prices.current_price) as avg_price')
            )
            ->groupBy('item_vendor_prices.vendor')
            ->orderBy('items_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['data' => [
            'total_lists' => $totalLists,
            'total_items' => $totalItems,
            'price_drops' => $priceDrops,
            'all_time_lows' => $allTimeLows,
            'total_savings' => round($totalSavings, 2),
            'below_target' => $belowTarget,
            'needs_refresh' => $needsRefresh,
            'pending_shares' => $pendingShares,
            'recent_drops' => $recentDrops,
            'seven_day_activity' => $sevenDayActivity,
            'store_leaderboard' => $storeLeaderboard,
        ]]);
    }
}
