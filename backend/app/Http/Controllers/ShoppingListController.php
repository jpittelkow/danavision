<?php

namespace App\Http\Controllers;

use App\Models\ItemVendorPrice;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Models\ShoppingList;
use App\Services\AI\AIPriceSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
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

        // Get suppressed vendors
        $suppressedVendors = $this->getSuppressedVendors($request->user()->id);

        // Transform items to include computed properties
        $items = $list->items->map(function ($item) use ($suppressedVendors) {
            // Filter vendor prices to exclude suppressed vendors
            $filteredVendorPrices = $item->vendorPrices->filter(function ($vp) use ($suppressedVendors) {
                return !$this->isVendorSuppressed($vp->vendor, $suppressedVendors);
            });

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
     * Refresh prices for all items in the list using AI search.
     */
    public function refresh(Request $request, ShoppingList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $priceService = AIPriceSearchService::forUser($request->user()->id);
        
        // Check if either AI or web search is available
        if (!$priceService->isAvailable() && !$priceService->isWebSearchAvailable()) {
            return back()->with('error', 'No AI providers or web search configured. Please set up an AI provider or SerpAPI key in Settings.');
        }

        $items = $list->items()->where('is_purchased', false)->get();
        
        if ($items->isEmpty()) {
            return back()->with('error', 'No items to refresh.');
        }

        // Get user's home zip code for local searches
        $homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $request->user()->id);

        // Get list-level shop_local setting
        $listShopLocal = $list->shop_local ?? false;

        $updated = 0;
        $errors = [];
        $providersUsed = [];

        foreach ($items as $item) {
            // Determine if shop_local is enabled (item-level takes precedence over list-level)
            $shopLocal = $item->shouldShopLocal();

            $searchQuery = $item->product_query ?? $item->product_name;
            $searchResult = $priceService->search($searchQuery, [
                'is_generic' => $item->is_generic ?? false,
                'unit_of_measure' => $item->unit_of_measure,
                'shop_local' => $shopLocal,
                'zip_code' => $homeZipCode,
            ]);

            // Track providers used
            $providersUsed = array_unique(array_merge($providersUsed, $searchResult->providersUsed));

            if ($searchResult->hasError() || !$searchResult->hasResults()) {
                $errors[] = $item->product_name;
                continue;
            }

            $lowestPrice = null;
            $lowestVendor = null;

            foreach ($searchResult->results as $result) {
                $vendor = ItemVendorPrice::normalizeVendor($result['retailer'] ?? 'Unknown');
                $price = (float) ($result['price'] ?? 0);
                
                if ($price <= 0) continue;

                // Find or create vendor price entry
                $vendorPrice = $item->vendorPrices()
                    ->where('vendor', $vendor)
                    ->first();

                if ($vendorPrice) {
                    $vendorPrice->updatePrice($price, $result['url'] ?? null, true);
                } else {
                    $item->vendorPrices()->create([
                        'vendor' => $vendor,
                        'product_url' => $result['url'] ?? null,
                        'current_price' => $price,
                        'lowest_price' => $price,
                        'highest_price' => $price,
                        'in_stock' => $result['in_stock'] ?? true,
                        'last_checked_at' => now(),
                    ]);
                }

                if ($lowestPrice === null || $price < $lowestPrice) {
                    $lowestPrice = $price;
                    $lowestVendor = $vendor;
                }
            }

            // Update main item with best price
            if ($lowestPrice !== null) {
                $item->updatePrice($lowestPrice, $lowestVendor);
                PriceHistory::captureFromItem($item, 'user_refresh');
                $updated++;
            }

            // Update generic info from search if not already set
            if ($searchResult->isGeneric && !$item->is_generic) {
                $item->update([
                    'is_generic' => true,
                    'unit_of_measure' => $searchResult->unitOfMeasure,
                ]);
            }
        }

        $providerNames = implode(', ', array_map('ucfirst', $providersUsed));
        $message = "Updated prices for {$updated} item" . ($updated !== 1 ? 's' : '') . " using {$providerNames}";
        
        if (!empty($errors)) {
            $message .= '. Failed: ' . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' and ' . (count($errors) - 3) . ' more';
            }
        }

        return back()->with('success', $message);
    }
}
