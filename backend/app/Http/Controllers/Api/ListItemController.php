<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\AIJob;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Services\LLM\LLMOrchestrator;
use App\Services\Shopping\ListItemService;
use App\Services\Shopping\PriceTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListItemController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ListItemService $listItemService,
        private PriceTrackingService $priceTrackingService,
    ) {}

    /**
     * List all items across all of the user's lists.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'list_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,purchased,all'],
            'price_status' => ['nullable', 'string', 'in:drop,all_time_low,below_target'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'sort' => ['nullable', 'string', 'in:name,price,updated'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $listIds = ShoppingList::where('user_id', $user->id)->pluck('id');

        $query = ListItem::whereIn('shopping_list_id', $listIds)
            ->with('shoppingList:id,name');

        if (!empty($validated['list_id'])) {
            $query->where('shopping_list_id', $validated['list_id']);
        }

        $status = $validated['status'] ?? 'active';
        if ($status === 'active') {
            $query->where('is_purchased', false);
        } elseif ($status === 'purchased') {
            $query->where('is_purchased', true);
        }

        if (!empty($validated['price_status'])) {
            match ($validated['price_status']) {
                'drop' => $query->whereNotNull('current_price')
                    ->whereNotNull('previous_price')
                    ->whereColumn('current_price', '<', 'previous_price'),
                'all_time_low' => $query->whereNotNull('current_price')
                    ->whereNotNull('lowest_price')
                    ->whereColumn('current_price', '<=', 'lowest_price')
                    ->where('current_price', '>', 0),
                'below_target' => $query->whereNotNull('current_price')
                    ->whereNotNull('target_price')
                    ->whereColumn('current_price', '<=', 'target_price'),
            };
        }

        if (!empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        $sort = $validated['sort'] ?? 'updated';
        $direction = $validated['direction'] ?? 'desc';
        match ($sort) {
            'name' => $query->orderBy('product_name', $direction),
            'price' => $query->orderBy('current_price', $direction),
            default => $query->orderBy('updated_at', $direction),
        };

        $items = $query->paginate(50);

        return response()->json($items);
    }

    /**
     * Add an item to a shopping list.
     */
    public function store(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeListAccess($request, $list, 'edit');

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'product_query' => ['nullable', 'string', 'max:500'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'url' => ['nullable', 'url', 'max:2048'],
            'product_image_url' => ['nullable', 'url', 'max:2048'],
            'upc' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:100'],
            'retailer' => ['nullable', 'string', 'max:255'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'is_generic' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'in:lb,oz,kg,g,gallon,liter,quart,pint,fl_oz,each,dozen'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        // Map frontend field names to model field names
        if (isset($validated['url']) && !isset($validated['product_url'])) {
            $validated['product_url'] = $validated['url'];
        }
        unset($validated['url']);

        if (isset($validated['retailer'])) {
            $validated['current_retailer'] = $validated['retailer'];
            unset($validated['retailer']);
        }

        $item = $this->listItemService->addItem($list, $request->user(), $validated);

        return $this->createdResponse('Item added to list', ['data' => $item]);
    }

    /**
     * Update a list item.
     */
    public function update(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item, 'edit');

        $validated = $request->validate([
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'product_query' => ['nullable', 'string', 'max:500'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'url' => ['nullable', 'url', 'max:2048'],
            'product_image_url' => ['nullable', 'url', 'max:2048'],
            'upc' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:100'],
            'retailer' => ['nullable', 'string', 'max:255'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'is_generic' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'in:lb,oz,kg,g,gallon,liter,quart,pint,fl_oz,each,dozen'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        // Map frontend field names to model field names
        if (isset($validated['url']) && !isset($validated['product_url'])) {
            $validated['product_url'] = $validated['url'];
        }
        unset($validated['url']);

        if (isset($validated['retailer'])) {
            $validated['current_retailer'] = $validated['retailer'];
            unset($validated['retailer']);
        }

        $item = $this->listItemService->updateItem($item, $validated);

        return $this->successResponse('Item updated', ['data' => $item]);
    }

    /**
     * Delete a list item.
     */
    public function destroy(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item, 'edit');

        $this->listItemService->deleteItem($item);

        return $this->deleteResponse('Item removed from list');
    }

    /**
     * Trigger price refresh for a single item.
     */
    public function refresh(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item);

        $job = $this->priceTrackingService->refreshItem($item);

        return $this->successResponse('Price refresh initiated', ['data' => $job]);
    }

    /**
     * Mark an item as purchased.
     */
    public function markPurchased(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item, 'edit');

        $price = $request->input('price') ? (float) $request->input('price') : null;

        $item = $this->listItemService->markPurchased($item, $price);

        return $this->successResponse('Item marked as purchased', ['data' => $item]);
    }

    /**
     * Get price history for a list item.
     */
    public function history(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item);

        $history = $this->listItemService->getHistory($item);

        return response()->json(['data' => $history]);
    }

    /**
     * Smart fill: use AI to enrich product details (SKU, UPC, brand, category, image URL).
     */
    public function smartFill(Request $request, ListItem $item): JsonResponse
    {
        $this->authorizeItemAccess($request, $item, 'edit');

        $user = $request->user();

        $aiJob = AIJob::create([
            'user_id' => $user->id,
            'type' => 'smart_fill',
            'status' => 'processing',
            'started_at' => now(),
            'related_item_id' => $item->id,
            'related_list_id' => $item->shopping_list_id,
            'input_data' => [
                'product_name' => $item->product_name,
                'upc' => $item->upc,
                'sku' => $item->sku,
            ],
        ]);

        try {
            $llm = app(LLMOrchestrator::class);

            $prompt = <<<PROMPT
            Enrich the following product with additional details:
            - Product Name: "{$item->product_name}"
            - Current UPC: {$item->upc}
            - Current SKU: {$item->sku}

            Please provide:
            - upc: The UPC/EAN barcode number (if you can identify it)
            - sku: A common SKU number
            - brand: The brand/manufacturer
            - category: Product category (e.g., "Groceries", "Electronics")
            - image_url: A direct URL to a product image (if you know one)
            - product_query: An optimized search query for finding this product's price

            Return a JSON object with these keys. Use null for any field you cannot determine.
            Return ONLY the JSON object, no other text.
            PROMPT;

            $result = $llm->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: 'You are a product data enrichment assistant. Return only valid JSON objects.',
                mode: 'single',
            );

            if ($result['success']) {
                $cleaned = preg_replace('/^```(?:json)?\s*/', '', trim($result['response']));
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
                $enriched = json_decode($cleaned, true);

                if (is_array($enriched)) {
                    $updates = [];
                    if (!empty($enriched['upc']) && empty($item->upc)) {
                        $updates['upc'] = $enriched['upc'];
                    }
                    if (!empty($enriched['sku']) && empty($item->sku)) {
                        $updates['sku'] = $enriched['sku'];
                    }
                    if (!empty($enriched['product_query']) && empty($item->product_query)) {
                        $updates['product_query'] = $enriched['product_query'];
                    }
                    if (!empty($enriched['image_url']) && empty($item->product_image_url)) {
                        $updates['product_image_url'] = $enriched['image_url'];
                    }

                    if (!empty($updates)) {
                        $item->update($updates);
                    }

                    $aiJob->markCompleted($enriched);
                } else {
                    $aiJob->markFailed('Failed to parse enrichment response');
                }
            } else {
                $aiJob->markFailed($result['error'] ?? 'LLM query failed');
            }
        } catch (\Exception $e) {
            Log::error('ListItemController: smart fill failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            $aiJob->markFailed($e->getMessage());
        }

        return $this->successResponse('Smart fill initiated', ['data' => [
            'item' => $item->fresh(),
            'job' => $aiJob->fresh(),
        ]]);
    }

    /**
     * Authorize that the current user has access to the list containing this item.
     */
    private function authorizeItemAccess(Request $request, ListItem $item, string $level = 'view'): void
    {
        $list = $item->shoppingList;
        $this->authorizeListAccess($request, $list, $level);
    }

    /**
     * Authorize that the current user owns or has shared access to the list.
     */
    private function authorizeListAccess(Request $request, ShoppingList $list, string $level = 'view'): void
    {
        $user = $request->user();

        if ($list->user_id === $user->id) {
            return;
        }

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
