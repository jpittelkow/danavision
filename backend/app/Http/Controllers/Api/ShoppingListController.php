<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ShoppingList;
use App\Services\Shopping\ListAnalysisService;
use App\Services\Shopping\ShoppingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ShoppingListService $shoppingListService,
        private ListAnalysisService $listAnalysisService,
    ) {}

    /**
     * List user's shopping lists (owned + shared).
     */
    public function index(Request $request): JsonResponse
    {
        $lists = $this->shoppingListService->getListsForUser($request->user());

        return response()->json(['data' => $lists]);
    }

    /**
     * Create a new shopping list.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notify_on_any_drop' => ['nullable', 'boolean'],
            'notify_on_threshold' => ['nullable', 'boolean'],
            'threshold_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        $list = $this->shoppingListService->createList($request->user(), $validated);

        return $this->createdResponse('Shopping list created successfully', ['data' => $list]);
    }

    /**
     * Show a single shopping list with items and vendor prices.
     */
    public function show(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list);

        $list->load([
            'items' => function ($query) {
                $query->orderByRaw('is_purchased ASC, updated_at DESC');
            },
            'items.vendorPrices' => function ($query) {
                $query->orderBy('current_price', 'asc');
            },
            'items.vendorPrices.store:id,name,slug,is_local',
        ]);

        // Add computed fields
        $list->items_count = $list->items->count();
        $list->price_drops_count = $list->items->filter(function ($item) {
            return $item->current_price !== null
                && $item->previous_price !== null
                && (float) $item->current_price < (float) $item->previous_price;
        })->count();

        // Check if shared with anyone
        $list->is_shared = $list->shares()->count() > 0;

        return response()->json(['data' => $list]);
    }

    /**
     * Update a shopping list.
     */
    public function update(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list, 'edit');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notify_on_any_drop' => ['nullable', 'boolean'],
            'notify_on_threshold' => ['nullable', 'boolean'],
            'threshold_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        $list = $this->shoppingListService->updateList($list, $validated);

        return $this->successResponse('Shopping list updated successfully', ['data' => $list]);
    }

    /**
     * Delete a shopping list.
     */
    public function destroy(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list, 'owner');

        $this->shoppingListService->deleteList($list);

        return $this->deleteResponse('Shopping list deleted successfully');
    }

    /**
     * Trigger price refresh for all items in the list.
     */
    public function refresh(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list);

        $this->shoppingListService->refreshPrices($list);

        return $this->successResponse('Price refresh initiated');
    }

    /**
     * Trigger a fresh store-comparison analysis, cache results on the list.
     */
    public function analyze(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list);

        $analysis = $this->listAnalysisService->analyzeByStore($list, $request->user());

        $list->update([
            'analysis_data'    => $analysis,
            'last_analyzed_at' => now(),
        ]);

        return response()->json(['data' => $analysis]);
    }

    /**
     * Return the cached store-comparison analysis for a list.
     */
    public function analysis(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list);

        if ($list->analysis_data === null) {
            return response()->json(['data' => null, 'message' => 'No analysis yet. POST /analyze to generate one.'], 200);
        }

        return response()->json([
            'data'            => $list->analysis_data,
            'last_analyzed_at' => $list->last_analyzed_at?->toIso8601String(),
        ]);
    }

    /**
     * Authorize that the current user owns or has shared access to the list.
     */
    private function authorizeAccess(Request $request, ShoppingList $list, string $level = 'view'): void
    {
        $user = $request->user();

        if ($level === 'owner' && $list->user_id !== $user->id) {
            abort(403, 'Only the list owner can perform this action.');
        }

        if ($list->user_id !== $user->id) {
            $share = $list->shares()
                ->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->whereNull('declined_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (!$share) {
                abort(403, 'You do not have access to this list.');
            }

            if ($level === 'edit' && !$share->hasPermission('edit')) {
                abort(403, 'You do not have edit permission for this list.');
            }
        }
    }
}
