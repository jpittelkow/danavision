<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get user's lists (owned + shared)
        $listIds = $user->shoppingLists()->pluck('id')
            ->merge($user->sharedLists()->pluck('shopping_list_id'));

        // Get items with recent price drops
        $recentDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Get all-time low items
        $allTimeLows = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('lowest_price')
            ->whereColumn('current_price', '<=', 'lowest_price')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Calculate stats
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

        return Inertia::render('Dashboard', [
            'stats' => [
                'lists_count' => $listIds->unique()->count(),
                'items_count' => $totalItems,
                'items_with_drops' => $itemsWithDrops,
                'total_potential_savings' => round($potentialSavings, 2),
            ],
            'recent_drops' => $recentDrops,
            'all_time_lows' => $allTimeLows,
        ]);
    }
}
