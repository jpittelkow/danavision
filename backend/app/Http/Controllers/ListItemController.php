<?php

namespace App\Http\Controllers;

use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\Setting;
use App\Models\ShoppingList;
use App\Services\AI\AIPriceSearchService;
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
     * Refresh price for a single item using AI search.
     */
    public function refresh(Request $request, ListItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $priceService = AIPriceSearchService::forUser($request->user()->id);
        
        // Check if either AI or web search is available
        if (!$priceService->isAvailable() && !$priceService->isWebSearchAvailable()) {
            return back()->with('error', 'No AI providers or web search configured. Please set up an AI provider or SerpAPI key in Settings.');
        }

        // Get user's home zip code for local searches
        $homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $request->user()->id);

        // Determine if shop_local is enabled (item-level takes precedence over list-level)
        $shopLocal = $item->shouldShopLocal();

        $searchQuery = $item->product_query ?? $item->product_name;
        $searchResult = $priceService->search($searchQuery, [
            'is_generic' => $item->is_generic ?? false,
            'unit_of_measure' => $item->unit_of_measure,
            'shop_local' => $shopLocal,
            'zip_code' => $homeZipCode,
        ]);

        if ($searchResult->hasError()) {
            return back()->with('error', 'AI price search failed: ' . $searchResult->error);
        }

        if (!$searchResult->hasResults()) {
            return back()->with('error', 'No prices found for this item.');
        }

        // Update vendor prices
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
                $vendorPrice = $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'product_url' => $result['url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $result['in_stock'] ?? true,
                    'last_checked_at' => now(),
                ]);
            }

            // Track lowest price
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
            }
        }

        // Update main item with best price
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);
            
            // Capture price history
            PriceHistory::captureFromItem($item, 'user_refresh');
        }

        // Update generic info from search if not already set
        if ($searchResult->isGeneric && !$item->is_generic) {
            $item->update([
                'is_generic' => true,
                'unit_of_measure' => $searchResult->unitOfMeasure,
            ]);
        }

        $providerNames = implode(', ', array_map('ucfirst', $searchResult->providersUsed));
        return back()->with('success', "Prices updated using {$providerNames}!");
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
}
