<?php

namespace App\Services\AskDana;

use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Shopping\ListAnalysisService;
use App\Services\Shopping\ListItemService;
use App\Services\Shopping\PriceTrackingService;
use App\Services\Shopping\ShoppingListService;
use App\Services\Shopping\StoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AskDanaToolRegistry
{
    public function __construct(
        private readonly ShoppingListService $shoppingListService,
        private readonly ListItemService $listItemService,
        private readonly ListAnalysisService $listAnalysisService,
        private readonly PriceTrackingService $priceTrackingService,
        private readonly StoreService $storeService,
    ) {}

    /**
     * Get all tool definitions in Anthropic tool-use format.
     */
    public function getToolDefinitions(): array
    {
        return [
            // Read tools
            $this->defineGetShoppingLists(),
            $this->defineGetListItems(),
            $this->defineGetItemDetails(),
            $this->defineGetPriceHistory(),
            $this->defineGetDashboardStats(),
            $this->defineAnalyzeListByStore(),
            $this->defineGetPriceDrops(),
            $this->defineGetSavingsSummary(),
            $this->defineGetStores(),
            $this->defineSearchPrices(),
            // Write tools
            $this->defineAddItemToList(),
            $this->defineCreateShoppingList(),
            $this->defineMarkItemPurchased(),
            $this->defineRefreshListPrices(),
        ];
    }

    /**
     * Execute a tool by name with the given arguments.
     */
    public function execute(string $toolName, array $args, User $user): array
    {
        return match ($toolName) {
            'get_shopping_lists' => $this->executeGetShoppingLists($user),
            'get_list_items' => $this->executeGetListItems($args, $user),
            'get_item_details' => $this->executeGetItemDetails($args, $user),
            'get_price_history' => $this->executeGetPriceHistory($args, $user),
            'get_dashboard_stats' => $this->executeGetDashboardStats($user),
            'analyze_list_by_store' => $this->executeAnalyzeListByStore($args, $user),
            'get_price_drops' => $this->executeGetPriceDrops($user),
            'get_savings_summary' => $this->executeGetSavingsSummary($user),
            'get_stores' => $this->executeGetStores($user),
            'search_prices' => $this->executeSearchPrices($args, $user),
            'add_item_to_list' => $this->executeAddItemToList($args, $user),
            'create_shopping_list' => $this->executeCreateShoppingList($args, $user),
            'mark_item_purchased' => $this->executeMarkItemPurchased($args, $user),
            'refresh_list_prices' => $this->executeRefreshListPrices($args, $user),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    /**
     * Check if a tool is a write (mutating) tool.
     */
    public function isWriteTool(string $toolName): bool
    {
        return in_array($toolName, [
            'add_item_to_list',
            'create_shopping_list',
            'mark_item_purchased',
            'refresh_list_prices',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tool Definitions
    // ──────────────────────────────────────────────────────────────────────

    private function defineGetShoppingLists(): array
    {
        return [
            'name' => 'get_shopping_lists',
            'description' => 'Get all of the user\'s shopping lists with item counts and status. Use this to see what lists exist before querying items.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
            ],
        ];
    }

    private function defineGetListItems(): array
    {
        return [
            'name' => 'get_list_items',
            'description' => 'Get items in a specific shopping list. Can filter by purchased status.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'The shopping list ID'],
                    'purchased' => ['type' => 'boolean', 'description' => 'Filter: true for purchased items, false for unpurchased, omit for all'],
                ],
                'required' => ['list_id'],
            ],
        ];
    }

    private function defineGetItemDetails(): array
    {
        return [
            'name' => 'get_item_details',
            'description' => 'Get full details for a single item including all vendor prices and recent price history.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'item_id' => ['type' => 'integer', 'description' => 'The list item ID'],
                ],
                'required' => ['item_id'],
            ],
        ];
    }

    private function defineGetPriceHistory(): array
    {
        return [
            'name' => 'get_price_history',
            'description' => 'Get price history for an item. Returns timestamped price records useful for trend analysis and charts.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'item_id' => ['type' => 'integer', 'description' => 'The list item ID'],
                ],
                'required' => ['item_id'],
            ],
        ];
    }

    private function defineGetDashboardStats(): array
    {
        return [
            'name' => 'get_dashboard_stats',
            'description' => 'Get aggregated dashboard metrics: total lists/items, price drops, all-time lows, total savings, items below target, items needing refresh, recent drops, 7-day activity, and store leaderboard.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
            ],
        ];
    }

    private function defineAnalyzeListByStore(): array
    {
        return [
            'name' => 'analyze_list_by_store',
            'description' => 'Analyze a shopping list by store — returns per-store cost totals, coverage percentages, cheapest store, and optimal split-shopping strategy.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'The shopping list ID to analyze'],
                ],
                'required' => ['list_id'],
            ],
        ];
    }

    private function defineGetPriceDrops(): array
    {
        return [
            'name' => 'get_price_drops',
            'description' => 'Get all items across the user\'s lists where the current price is lower than the previous price.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
            ],
        ];
    }

    private function defineGetSavingsSummary(): array
    {
        return [
            'name' => 'get_savings_summary',
            'description' => 'Get total savings summary across all lists — how much the user is saving compared to highest known prices.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
            ],
        ];
    }

    private function defineGetStores(): array
    {
        return [
            'name' => 'get_stores',
            'description' => 'Get the user\'s active stores with their preferences (enabled, priority, favorite status).',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
            ],
        ];
    }

    private function defineSearchPrices(): array
    {
        return [
            'name' => 'search_prices',
            'description' => 'Search for product prices across multiple vendors and stores. Use this when the user asks about pricing for a specific product or wants to find deals.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Product name or search query (e.g. "organic whole milk", "Tide pods")'],
                ],
                'required' => ['query'],
            ],
        ];
    }

    private function defineAddItemToList(): array
    {
        return [
            'name' => 'add_item_to_list',
            'description' => 'Add a new item to a shopping list. This is a write action.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'The shopping list ID to add the item to'],
                    'product_name' => ['type' => 'string', 'description' => 'Name of the product'],
                    'product_query' => ['type' => 'string', 'description' => 'Search query for price lookups (optional, defaults to product_name)'],
                    'target_price' => ['type' => 'number', 'description' => 'Target price the user wants to pay (optional)'],
                ],
                'required' => ['list_id', 'product_name'],
            ],
        ];
    }

    private function defineCreateShoppingList(): array
    {
        return [
            'name' => 'create_shopping_list',
            'description' => 'Create a new shopping list. This is a write action.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Name for the shopping list'],
                    'description' => ['type' => 'string', 'description' => 'Optional description'],
                ],
                'required' => ['name'],
            ],
        ];
    }

    private function defineMarkItemPurchased(): array
    {
        return [
            'name' => 'mark_item_purchased',
            'description' => 'Mark an item as purchased. This is a write action.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'item_id' => ['type' => 'integer', 'description' => 'The list item ID'],
                    'price' => ['type' => 'number', 'description' => 'Actual purchase price (optional)'],
                ],
                'required' => ['item_id'],
            ],
        ];
    }

    private function defineRefreshListPrices(): array
    {
        return [
            'name' => 'refresh_list_prices',
            'description' => 'Trigger a price refresh for all items in a shopping list. This is a write action that makes external API calls.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'The shopping list ID to refresh'],
                ],
                'required' => ['list_id'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tool Execution
    // ──────────────────────────────────────────────────────────────────────

    private function executeGetShoppingLists(User $user): array
    {
        $lists = $this->shoppingListService->getListsForUser($user);
        $lists->loadCount([
            'items',
            'items as unpurchased_count' => fn ($q) => $q->where('is_purchased', false),
        ]);

        return [
            'lists' => $lists->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'is_active' => $list->is_active,
                'items_count' => $list->items_count,
                'unpurchased_count' => $list->unpurchased_count,
                'last_refreshed_at' => $list->last_refreshed_at?->toIso8601String(),
                'created_at' => $list->created_at->toIso8601String(),
            ])->values()->toArray(),
        ];
    }

    private function executeGetListItems(array $args, User $user): array
    {
        $list = $this->findUserList((int) $args['list_id'], $user);
        if (!$list) {
            return ['error' => 'Shopping list not found or access denied'];
        }

        $query = $list->items();
        if (isset($args['purchased'])) {
            $query->where('is_purchased', (bool) $args['purchased']);
        }

        $items = $query->get();

        return [
            'list_name' => $list->name,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'current_price' => $item->current_price,
                'previous_price' => $item->previous_price,
                'lowest_price' => $item->lowest_price,
                'highest_price' => $item->highest_price,
                'target_price' => $item->target_price,
                'current_retailer' => $item->current_retailer,
                'is_purchased' => $item->is_purchased,
                'in_stock' => $item->in_stock,
                'last_checked_at' => $item->last_checked_at?->toIso8601String(),
            ])->toArray(),
        ];
    }

    private function executeGetItemDetails(array $args, User $user): array
    {
        $item = $this->findUserItem((int) $args['item_id'], $user);
        if (!$item) {
            return ['error' => 'Item not found or access denied'];
        }

        $item->load(['vendorPrices.store', 'priceHistory' => fn ($q) => $q->orderBy('captured_at', 'desc')->limit(20)]);

        return [
            'item' => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'current_price' => $item->current_price,
                'previous_price' => $item->previous_price,
                'lowest_price' => $item->lowest_price,
                'highest_price' => $item->highest_price,
                'target_price' => $item->target_price,
                'current_retailer' => $item->current_retailer,
                'is_purchased' => $item->is_purchased,
                'vendor_prices' => $item->vendorPrices->map(fn ($vp) => [
                    'vendor' => $vp->store?->name ?? $vp->vendor,
                    'price' => $vp->current_price,
                    'unit_price' => $vp->unit_price,
                    'unit_type' => $vp->unit_type,
                    'on_sale' => $vp->on_sale,
                    'in_stock' => $vp->in_stock,
                ])->toArray(),
                'price_history' => $item->priceHistory->map(fn ($ph) => [
                    'price' => $ph->price,
                    'retailer' => $ph->retailer,
                    'captured_at' => $ph->captured_at->toIso8601String(),
                ])->toArray(),
            ],
        ];
    }

    private function executeGetPriceHistory(array $args, User $user): array
    {
        $item = $this->findUserItem((int) $args['item_id'], $user);
        if (!$item) {
            return ['error' => 'Item not found or access denied'];
        }

        $history = $this->listItemService->getHistory($item);

        return [
            'product_name' => $item->product_name,
            'history' => $history->map(fn ($ph) => [
                'price' => $ph->price,
                'retailer' => $ph->retailer,
                'captured_at' => $ph->captured_at->toIso8601String(),
            ])->toArray(),
        ];
    }

    private function executeGetDashboardStats(User $user): array
    {
        $listIds = ShoppingList::where('user_id', $user->id)->pluck('id');

        $totalItems = ListItem::whereIn('shopping_list_id', $listIds)->count();

        $priceDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->count();

        $allTimeLows = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('lowest_price')
            ->whereColumn('current_price', '<=', 'lowest_price')
            ->where('current_price', '>', 0)
            ->count();

        $savingsItems = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('highest_price')
            ->whereColumn('highest_price', '>', 'current_price')
            ->get();

        $totalSavings = $savingsItems->sum(fn ($item) => (float) $item->highest_price - (float) $item->current_price);

        $belowTarget = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('target_price')
            ->whereColumn('current_price', '<=', 'target_price')
            ->count();

        $needsRefresh = ListItem::whereIn('shopping_list_id', $listIds)
            ->where('is_purchased', false)
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subHours(24)))
            ->count();

        $recentDrops = ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->with('shoppingList:id,name')
            ->orderBy('last_checked_at', 'desc')
            ->limit(5)
            ->get(['id', 'shopping_list_id', 'product_name', 'current_price', 'previous_price', 'current_retailer', 'last_checked_at'])
            ->map(fn ($item) => [
                'product_name' => $item->product_name,
                'list_name' => $item->shoppingList?->name,
                'current_price' => $item->current_price,
                'previous_price' => $item->previous_price,
                'retailer' => $item->current_retailer,
            ])->toArray();

        return [
            'total_lists' => $listIds->count(),
            'total_items' => $totalItems,
            'price_drops' => $priceDrops,
            'all_time_lows' => $allTimeLows,
            'total_savings' => round($totalSavings, 2),
            'below_target' => $belowTarget,
            'needs_refresh' => $needsRefresh,
            'recent_drops' => $recentDrops,
        ];
    }

    private function executeAnalyzeListByStore(array $args, User $user): array
    {
        $list = $this->findUserList((int) $args['list_id'], $user);
        if (!$list) {
            return ['error' => 'Shopping list not found or access denied'];
        }

        return $this->listAnalysisService->analyzeByStore($list, $user);
    }

    private function executeGetPriceDrops(User $user): array
    {
        $drops = $this->shoppingListService->getPriceDrops($user);

        return [
            'price_drops' => $drops->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'current_price' => $item->current_price,
                'previous_price' => $item->previous_price,
                'savings' => round((float) $item->previous_price - (float) $item->current_price, 2),
                'current_retailer' => $item->current_retailer,
            ])->toArray(),
        ];
    }

    private function executeGetSavingsSummary(User $user): array
    {
        return $this->shoppingListService->getSavingsSummary($user);
    }

    private function executeGetStores(User $user): array
    {
        $stores = $this->storeService->getActiveStores($user);

        return [
            'stores' => collect($stores)->map(fn ($store) => [
                'id' => $store['id'],
                'name' => $store['name'],
                'category' => $store['category'] ?? null,
                'is_favorite' => $store['is_favorite'] ?? false,
                'user_enabled' => $store['user_enabled'] ?? true,
            ])->toArray(),
        ];
    }

    private function executeSearchPrices(array $args, User $user): array
    {
        $query = $args['query'] ?? '';
        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        try {
            $priceSearchService = app(\App\Services\PriceSearch\PriceSearchService::class);
            $results = $priceSearchService->searchByQuery($query, $user);

            return [
                'query' => $query,
                'results' => collect($results)->take(10)->map(fn ($r) => [
                    'product_name' => $r['product_name'] ?? $r['name'] ?? $query,
                    'price' => $r['price'] ?? null,
                    'retailer' => $r['retailer'] ?? $r['vendor'] ?? null,
                    'in_stock' => $r['in_stock'] ?? null,
                    'url' => $r['url'] ?? null,
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('AskDana: price search failed', ['query' => $query, 'error' => $e->getMessage()]);

            return ['error' => 'Price search is currently unavailable. Please try again later.'];
        }
    }

    // ── Write Tool Execution ─────────────────────────────────────────────

    private function executeAddItemToList(array $args, User $user): array
    {
        $list = $this->findUserList((int) $args['list_id'], $user);
        if (!$list) {
            return ['error' => 'Shopping list not found or access denied'];
        }

        $data = [
            'product_name' => $args['product_name'],
            'product_query' => $args['product_query'] ?? $args['product_name'],
        ];

        if (isset($args['target_price'])) {
            $data['target_price'] = $args['target_price'];
        }

        $item = $this->listItemService->addItem($list, $user, $data);

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'list_name' => $list->name,
            ],
        ];
    }

    private function executeCreateShoppingList(array $args, User $user): array
    {
        $data = ['name' => $args['name']];
        if (isset($args['description'])) {
            $data['description'] = $args['description'];
        }

        $list = $this->shoppingListService->createList($user, $data);

        return [
            'success' => true,
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
            ],
        ];
    }

    private function executeMarkItemPurchased(array $args, User $user): array
    {
        $item = $this->findUserItem((int) $args['item_id'], $user);
        if (!$item) {
            return ['error' => 'Item not found or access denied'];
        }

        $price = isset($args['price']) ? (float) $args['price'] : null;
        $this->listItemService->markPurchased($item, $price);

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'purchased_price' => $price,
            ],
        ];
    }

    private function executeRefreshListPrices(array $args, User $user): array
    {
        $list = $this->findUserList((int) $args['list_id'], $user);
        if (!$list) {
            return ['error' => 'Shopping list not found or access denied'];
        }

        $this->shoppingListService->refreshPrices($list);

        return [
            'success' => true,
            'message' => "Price refresh started for list \"{$list->name}\".",
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function findUserList(int $listId, User $user): ?ShoppingList
    {
        return ShoppingList::where('id', $listId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('shares', fn ($sq) => $sq->where('user_id', $user->id)->whereNotNull('accepted_at'));
            })
            ->first();
    }

    private function findUserItem(int $itemId, User $user): ?ListItem
    {
        return ListItem::where('id', $itemId)
            ->whereHas('shoppingList', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('shares', fn ($sq) => $sq->where('user_id', $user->id)->whereNotNull('accepted_at'));
            })
            ->first();
    }
}
