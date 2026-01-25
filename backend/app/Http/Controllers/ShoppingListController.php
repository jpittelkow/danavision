<?php

namespace App\Http\Controllers;

use App\Jobs\AI\FirecrawlDiscoveryJob;
use App\Models\AIJob;
use App\Models\Setting;
use App\Models\ShoppingList;
use App\Services\Crawler\StoreDiscoveryService;
use App\Traits\SuppressesVendors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
    use SuppressesVendors;
    /**
     * Display all lists for the user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $ownedLists = $user->shoppingLists()
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get();

        $sharedLists = $user->sharedLists()
            ->with(['shoppingList' => fn ($q) => $q->withCount('items')])
            ->where('accepted_at', '!=', null)
            ->get()
            ->pluck('shoppingList');

        return Inertia::render('Lists/Index', [
            'owned_lists' => $ownedLists,
            'shared_lists' => $sharedLists,
        ]);
    }

    /**
     * Show form for creating a new list.
     */
    public function create(): Response
    {
        return Inertia::render('Lists/Create');
    }

    /**
     * Store a new list.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notify_on_any_drop' => ['boolean'],
            'notify_on_threshold' => ['boolean'],
            'threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'shop_local' => ['boolean'],
        ]);

        $list = $request->user()->shoppingLists()->create($validated);

        return redirect()->route('lists.show', $list)
            ->with('success', 'List created successfully!');
    }

    /**
     * Display a specific list.
     */
    public function show(Request $request, ShoppingList $list): Response
    {
        $this->authorize('view', $list);

        // Load items with vendor prices, ordered by updated_at
        $list->load([
            'items' => fn ($q) => $q->orderByDesc('updated_at'),
            'items.vendorPrices' => fn ($q) => $q->orderBy('current_price'),
            'shares.user',
        ]);

        $userId = $request->user()->id;

        // Transform items to include computed properties
        $items = $list->items->map(function ($item) use ($userId) {
            // Filter vendor prices to exclude suppressed vendors
            $filteredVendorPrices = $this->filterSuppressedVendorPrices($item->vendorPrices, $userId);

            return [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'product_url' => $item->product_url,
                'product_image_url' => $item->getDisplayImageUrl(),
                'sku' => $item->sku,
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
                'purchased_at' => $item->purchased_at?->toISOString(),
                'last_checked_at' => $item->last_checked_at?->toISOString(),
                'is_at_all_time_low' => $item->isAtAllTimeLow(),
                'price_change_percent' => $item->priceChangePercent(),
                'vendor_prices' => $filteredVendorPrices->values()->map(function ($vp) {
                    return [
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
                    ];
                }),
            ];
        });

        // Add items back to list as array
        $listData = $list->toArray();
        $listData['items'] = $items;

        return Inertia::render('Lists/Show', [
            'list' => $listData,
            'can_edit' => $request->user()->can('update', $list),
            'can_share' => $request->user()->can('share', $list),
        ]);
    }

    /**
     * Show form for editing a list.
     */
    public function edit(ShoppingList $list): Response
    {
        $this->authorize('update', $list);

        return Inertia::render('Lists/Edit', [
            'list' => $list,
        ]);
    }

    /**
     * Update a list.
     */
    public function update(Request $request, ShoppingList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notify_on_any_drop' => ['boolean'],
            'notify_on_threshold' => ['boolean'],
            'threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'shop_local' => ['boolean'],
        ]);

        $list->update($validated);

        return redirect()->route('lists.show', $list)
            ->with('success', 'List updated successfully!');
    }

    /**
     * Delete a list.
     */
    public function destroy(ShoppingList $list): RedirectResponse
    {
        $this->authorize('delete', $list);

        $list->delete();

        return redirect()->route('lists.index')
            ->with('success', 'List deleted successfully!');
    }

    /**
     * Refresh prices for all items in the list using Crawl4AI.
     */
    public function refresh(Request $request, ShoppingList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $userId = $request->user()->id;
        $discoveryService = StoreDiscoveryService::forUser($userId);

        // Check if price discovery is available (Crawl4AI + AI provider)
        if (!$discoveryService->isAvailable()) {
            return back()->with('error', 'Price discovery is not available. Please configure an AI provider in Settings.');
        }

        $items = $list->items()->where('is_purchased', false)->get();
        
        if ($items->isEmpty()) {
            return back()->with('error', 'No items to refresh.');
        }
        
        // Dispatch async jobs for each item
        $jobCount = 0;
        foreach ($items as $item) {
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
                    'source' => 'list_refresh',
                ],
                relatedItemId: $item->id,
                relatedListId: $list->id,
            );

            FirecrawlDiscoveryJob::dispatch($aiJob->id, $userId)
                ->delay(now()->addSeconds($jobCount * 2)); // Stagger jobs to avoid rate limits
            $jobCount++;
        }
        
        return back()->with('success', "Refreshing prices for {$jobCount} items. This may take a few minutes.");
    }
}
