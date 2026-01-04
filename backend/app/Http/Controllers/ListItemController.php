<?php

namespace App\Http\Controllers;

use App\Jobs\AI\FirecrawlDiscoveryJob;
use App\Jobs\AI\FirecrawlRefreshJob;
use App\Jobs\AI\SmartFillJob;
use App\Services\Crawler\FirecrawlService;
use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListItemController extends Controller
{
    /**
     * Display a single item with vendor prices and price history.
     */
    public function show(Request $request, ListItem $item): Response
    {
        $this->authorize('view', $item);

        // Load relationships
        $item->load([
            'vendorPrices' => fn ($q) => $q->orderBy('current_price'),
            'priceHistory' => fn ($q) => $q->orderByDesc('captured_at')->limit(100),
            'shoppingList',
        ]);

        // Get suppressed vendors
        $suppressedVendors = $this->getSuppressedVendors($request->user()->id);

        // Filter vendor prices to exclude suppressed vendors
        $filteredVendorPrices = $item->vendorPrices->filter(function ($vp) use ($suppressedVendors) {
            return !$this->isVendorSuppressed($vp->vendor, $suppressedVendors);
        });

        // Group price history by retailer for charting (also filter suppressed)
        $priceHistoryByRetailer = $item->priceHistory
            ->filter(fn ($ph) => !$this->isVendorSuppressed($ph->retailer, $suppressedVendors))
            ->groupBy('retailer')
            ->map(fn ($group) => $group->map(fn ($ph) => [
                'date' => $ph->captured_at->toISOString(),
                'price' => (float) $ph->price,
                'retailer' => $ph->retailer,
                'in_stock' => $ph->in_stock,
            ])->values())
            ->toArray();

        return Inertia::render('Items/Show', [
            'item' => [
                'id' => $item->id,
                'shopping_list_id' => $item->shopping_list_id,
                'product_name' => $item->product_name,
                'product_url' => $item->product_url,
                'product_image_url' => $item->getDisplayImageUrl(),
                'sku' => $item->sku,
                'upc' => $item->upc,
                'notes' => $item->notes,
                'target_price' => $item->target_price,
                'current_price' => $item->current_price,
                'previous_price' => $item->previous_price,
                'lowest_price' => $item->lowest_price,
                'highest_price' => $item->highest_price,
                'current_retailer' => $item->current_retailer,
                'priority' => $item->priority,
                'is_purchased' => $item->is_purchased,
                'is_generic' => $item->is_generic,
                'unit_of_measure' => $item->unit_of_measure,
                'shop_local' => $item->shop_local,
                'purchased_at' => $item->purchased_at?->toISOString(),
                'last_checked_at' => $item->last_checked_at?->toISOString(),
                'is_at_all_time_low' => $item->isAtAllTimeLow(),
                'vendor_prices' => $filteredVendorPrices->values()->map(fn ($vp) => [
                    'id' => $vp->id,
                    'vendor' => $vp->vendor,
                    'vendor_sku' => $vp->vendor_sku,
                    'product_url' => $vp->product_url,
                    'current_price' => $vp->current_price,
                    'previous_price' => $vp->previous_price,
                    'lowest_price' => $vp->lowest_price,
                    'on_sale' => $vp->on_sale,
                    'sale_percent_off' => $vp->sale_percent_off,
                    'in_stock' => $vp->in_stock,
                    'last_checked_at' => $vp->last_checked_at?->toISOString(),
                    'is_at_all_time_low' => $vp->isAtAllTimeLow(),
                ]),
            ],
            'list' => [
                'id' => $item->shoppingList->id,
                'name' => $item->shoppingList->name,
            ],
            'price_history' => $priceHistoryByRetailer,
            'can_edit' => $request->user()->can('update', $item),
        ]);
    }

    /**
     * Get the list of suppressed vendors for a user.
     */
    protected function getSuppressedVendors(int $userId): array
    {
        $suppressedJson = Setting::get(Setting::SUPPRESSED_VENDORS, $userId);
        return $suppressedJson ? json_decode($suppressedJson, true) ?: [] : [];
    }

    /**
     * Check if a vendor is suppressed.
     */
    protected function isVendorSuppressed(string $vendor, array $suppressedVendors): bool
    {
        if (empty($suppressedVendors)) {
            return false;
        }

        $vendorLower = strtolower($vendor);
        foreach ($suppressedVendors as $suppressed) {
            $suppressedLower = strtolower($suppressed);
            if (str_contains($vendorLower, $suppressedLower) || str_contains($suppressedLower, $vendorLower)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Store a new item in a list.
     */
    public function store(Request $request, ShoppingList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'product_query' => ['nullable', 'string', 'max:255'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'current_retailer' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['in:low,medium,high'],
            'is_generic' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'upc' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['added_by_user_id'] = $request->user()->id;
        $validated['product_query'] = $validated['product_query'] ?? $validated['product_name'];

        $list->items()->create($validated);

        return back()->with('success', 'Item added successfully!');
    }

    /**
     * Update an item.
     */
    public function update(Request $request, ListItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'product_query' => ['nullable', 'string', 'max:255'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'product_image_url' => ['nullable', 'url', 'max:2048'],
            'sku' => ['nullable', 'string', 'max:100'],
            'upc' => ['nullable', 'string', 'max:20'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['in:low,medium,high'],
            'shop_local' => ['nullable', 'boolean'],
            'is_generic' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
        ]);

        $item->update($validated);

        return back()->with('success', 'Item updated successfully!');
    }

    /**
     * Delete an item.
     */
    public function destroy(ListItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return back()->with('success', 'Item deleted successfully!');
    }

    /**
     * Refresh price for a single item using Firecrawl.
     * 
     * Always dispatches an async job for price refresh.
     * Returns JSON with job ID for polling.
     */
    public function refresh(Request $request, ListItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item);

        $useAsync = $request->boolean('async', false);
        $userId = $request->user()->id;

        // Check if Firecrawl is available
        $firecrawlService = FirecrawlService::forUser($userId);
        
        if (!$firecrawlService->isAvailable()) {
            $error = 'Firecrawl is not configured. Please set up a Firecrawl API key in Settings.';
            return $useAsync
                ? response()->json(['error' => $error], 422)
                : back()->with('error', $error);
        }

        // Check if item has vendor URLs for refresh, otherwise do discovery
        $vendorUrls = $item->vendorPrices()->pluck('product_url')->filter()->toArray();
        
        if (!empty($vendorUrls)) {
            // Has URLs - do a refresh
            $aiJob = AIJob::createJob(
                userId: $userId,
                type: AIJob::TYPE_FIRECRAWL_REFRESH,
                inputData: [
                    'product_name' => $item->product_name,
                    'product_query' => $item->product_query,
                    'product_urls' => $vendorUrls,
                    'is_generic' => $item->is_generic ?? false,
                    'unit_of_measure' => $item->unit_of_measure,
                    'shop_local' => $item->shouldShopLocal(),
                ],
                relatedItemId: $item->id,
                relatedListId: $item->shopping_list_id,
            );

            FirecrawlRefreshJob::dispatch($aiJob->id, $userId);
        } else {
            // No URLs yet - do a discovery
            $aiJob = AIJob::createJob(
                userId: $userId,
                type: AIJob::TYPE_FIRECRAWL_DISCOVERY,
                inputData: [
                    'product_name' => $item->product_name,
                    'product_query' => $item->product_query ?? $item->product_name,
                    'upc' => $item->upc ?? null,
                    'is_generic' => $item->is_generic ?? false,
                    'unit_of_measure' => $item->unit_of_measure ?? null,
                    'shop_local' => $item->shouldShopLocal(),
                    'source' => 'manual_refresh',
                ],
                relatedItemId: $item->id,
                relatedListId: $item->shopping_list_id,
            );

            FirecrawlDiscoveryJob::dispatch($aiJob->id, $userId);
        }

        if ($useAsync) {
            return response()->json([
                'job_id' => $aiJob->id,
                'status' => 'pending',
                'message' => 'Price refresh job started.',
            ], 202);
        }

        return back()->with('success', 'Price refresh started. Check back in a moment for updated prices.');
    }

    /**
     * Mark an item as purchased.
     */
    public function markPurchased(Request $request, ListItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'purchased_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item->update([
            'is_purchased' => true,
            'purchased_at' => now(),
            'purchased_price' => $validated['purchased_price'] ?? $item->current_price,
        ]);

        return back()->with('success', 'Item marked as purchased!');
    }

    /**
     * Smart fill item details using AI.
     *
     * Uses AI to analyze the product name and find additional information
     * including images, SKU/UPC, description, and suggested pricing.
     * 
     * Supports two modes:
     * - async=false (default): Synchronous processing
     * - async=true: Dispatches background job, returns job ID
     *
     * @param Request $request
     * @param ListItem $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function smartFill(Request $request, ListItem $item): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item);

        $userId = $request->user()->id;
        $useAsync = $request->boolean('async', false);

        // Check if AI is available
        $multiAI = \App\Services\AI\MultiAIService::forUser($userId);
        if (!$multiAI->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => 'No AI providers configured. Please set up an AI provider in Settings.',
            ], 422);
        }

        // Async mode: dispatch job and return JSON
        if ($useAsync) {
            $aiJob = AIJob::createJob(
                userId: $userId,
                type: AIJob::TYPE_SMART_FILL,
                inputData: [
                    'product_name' => $item->product_name,
                    'is_generic' => $item->is_generic ?? false,
                    'unit_of_measure' => $item->unit_of_measure,
                ],
                relatedItemId: $item->id,
                relatedListId: $item->shopping_list_id,
            );

            SmartFillJob::dispatch($aiJob->id, $userId);

            return response()->json([
                'job_id' => $aiJob->id,
                'status' => 'pending',
                'message' => 'Smart fill job started.',
            ], 202);
        }

        // Sync mode: process immediately (legacy behavior)
        $productName = $item->product_name;
        $providersUsed = [];

        // Build prompt for AI to analyze the product
        $prompt = $this->buildSmartFillPrompt($productName, $item);

        try {
            // Query AI for product information
            $result = $multiAI->processWithAllProviders($prompt);

            if ($result['error'] && !$result['aggregated_response']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'AI analysis failed',
                ], 422);
            }

            // Parse the AI response
            $aiData = $this->parseSmartFillResponse($result['aggregated_response']);

            // Track providers used
            $providersUsed = collect($result['individual_responses'] ?? [])
                ->filter(fn ($r) => $r['error'] === null)
                ->keys()
                ->toArray();

            // Build the response (price search now happens via Firecrawl after item is added)
            $response = [
                'success' => true,
                'product_image_url' => $aiData['image_url'] ?? null,
                'sku' => $aiData['sku'] ?? null,
                'upc' => $aiData['upc'] ?? null,
                'description' => $aiData['description'] ?? null,
                'suggested_target_price' => null, // Price search happens via Firecrawl
                'common_price' => null,
                'brand' => $aiData['brand'] ?? null,
                'category' => $aiData['category'] ?? null,
                'is_generic' => $aiData['is_generic'] ?? $item->is_generic ?? false,
                'unit_of_measure' => $aiData['unit_of_measure'] ?? $item->unit_of_measure,
                'providers_used' => array_unique($providersUsed),
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Smart fill failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Smart fill failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build the prompt for smart fill AI analysis.
     *
     * @param string $productName
     * @param ListItem $item
     * @return string
     */
    protected function buildSmartFillPrompt(string $productName, ListItem $item): string
    {
        $existingInfo = [];
        if ($item->sku) {
            $existingInfo[] = "SKU: {$item->sku}";
        }
        if ($item->upc) {
            $existingInfo[] = "UPC: {$item->upc}";
        }
        if ($item->notes) {
            $existingInfo[] = "Notes: {$item->notes}";
        }

        $existingContext = !empty($existingInfo)
            ? "\n\nExisting information about this item:\n" . implode("\n", $existingInfo)
            : '';

        return <<<PROMPT
Analyze this product and provide detailed information: "{$productName}"{$existingContext}

Return a JSON object with the following fields:
{
    "sku": "Product SKU/model number if known (e.g., 'WH-1000XM5' for Sony headphones)",
    "upc": "12-digit UPC barcode if this is a packaged retail product, null for generic items",
    "description": "A helpful 1-2 sentence description of the product for shopping purposes",
    "brand": "The brand name if identifiable",
    "category": "Product category (e.g., Electronics, Groceries, Home & Garden)",
    "image_url": "A valid product image URL if you know one from a major retailer",
    "is_generic": false,
    "unit_of_measure": null
}

Guidelines:
- SKU: Provide the manufacturer's SKU, model number, or part number if identifiable. This helps with price tracking.
- UPC: Only provide the 12-digit barcode for packaged retail products. Generic items (produce, bulk goods, deli) do NOT have UPCs - use null.
- Description: Write a brief, helpful description that would help someone shopping for this item.
- Brand: Identify the brand if possible from the product name.
- Category: Classify the product into a helpful category.
- Image URL: Only provide if you're confident it's a valid, direct image URL from a major retailer.
- is_generic: Set to true for items sold by weight/volume/count (produce, meat, dairy), false for branded products with SKUs.
- unit_of_measure: If is_generic is true, specify the unit (lb, oz, kg, gallon, each, dozen, etc.).

Examples:
- "Sony WH-1000XM5" → sku: "WH-1000XM5", upc: "027242917576", is_generic: false
- "Organic Blueberries" → sku: null, upc: null, is_generic: true, unit_of_measure: "lb"
- "iPhone 15 Pro Max 256GB" → sku: "MU773LL/A", upc: "194253389316", is_generic: false

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Parse the AI response for smart fill.
     *
     * @param string|null $response
     * @return array
     */
    protected function parseSmartFillResponse(?string $response): array
    {
        $defaults = [
            'sku' => null,
            'upc' => null,
            'description' => null,
            'brand' => null,
            'category' => null,
            'image_url' => null,
            'is_generic' => false,
            'unit_of_measure' => null,
        ];

        if (!$response) {
            return $defaults;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Ensure is_generic is a boolean
                if (isset($json['is_generic'])) {
                    $json['is_generic'] = (bool) $json['is_generic'];
                }
                return array_merge($defaults, array_filter($json, fn ($v) => $v !== null && $v !== ''));
            }
        }

        return $defaults;
    }

    /**
     * Get the active (pending/processing) job for an item.
     * 
     * Used by the frontend to poll for job status and show
     * real-time price update indicators.
     *
     * @param Request $request
     * @param ListItem $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function activeJob(Request $request, ListItem $item): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $item);

        // Find any active price-related job for this item
        $activeJob = AIJob::where('related_item_id', $item->id)
            ->whereIn('type', [
                AIJob::TYPE_PRICE_SEARCH,
                AIJob::TYPE_PRICE_REFRESH,
                AIJob::TYPE_FIRECRAWL_DISCOVERY,
                AIJob::TYPE_FIRECRAWL_REFRESH,
            ])
            ->whereIn('status', [AIJob::STATUS_PENDING, AIJob::STATUS_PROCESSING])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$activeJob) {
            // Also check for recently failed jobs (within last 5 minutes)
            $recentFailedJob = AIJob::where('related_item_id', $item->id)
                ->whereIn('type', [
                    AIJob::TYPE_PRICE_SEARCH,
                    AIJob::TYPE_PRICE_REFRESH,
                    AIJob::TYPE_FIRECRAWL_DISCOVERY,
                    AIJob::TYPE_FIRECRAWL_REFRESH,
                ])
                ->where('status', AIJob::STATUS_FAILED)
                ->where('completed_at', '>=', now()->subMinutes(5))
                ->orderBy('created_at', 'desc')
                ->first();

            if ($recentFailedJob) {
                return response()->json([
                    'job' => [
                        'id' => $recentFailedJob->id,
                        'status' => $recentFailedJob->status,
                        'progress' => $recentFailedJob->progress,
                        'type' => $recentFailedJob->type,
                        'error_message' => $recentFailedJob->error_message,
                    ],
                ]);
            }

            return response()->json(['job' => null]);
        }

        return response()->json([
            'job' => [
                'id' => $activeJob->id,
                'status' => $activeJob->status,
                'progress' => $activeJob->progress,
                'type' => $activeJob->type,
            ],
        ]);
    }
}
