<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\DealScan;
use App\Models\ListItem;
use App\Models\ScannedDeal;
use App\Models\ShoppingList;
use App\Services\Deals\DealMatchingService;
use App\Services\Deals\DealPricingService;
use App\Services\Deals\DealScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealScanController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DealScannerService $dealScannerService,
        private DealMatchingService $dealMatchingService,
        private DealPricingService $dealPricingService,
    ) {}

    /**
     * Upload image(s) for deal extraction via AI vision.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'image', 'max:10240'],
            'scan_type' => ['nullable', 'string', 'in:coupon,circular,flyer'],
        ]);

        $user = $request->user();
        $scanType = $request->input('scan_type');
        $scans = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('deal-scans', 'local');
            $fullPath = storage_path('app/' . $path);
            $scans[] = $this->dealScannerService->scanImage($fullPath, $user, $scanType);
        }

        return $this->createdResponse('Scan processed', ['data' => $scans]);
    }

    /**
     * Create a deal manually (no image/AI).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'product_description' => ['nullable', 'string', 'max:1000'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'deal_type' => ['nullable', 'string', 'in:coupon,circular,flyer,bogo,clearance'],
            'discount_type' => ['required', 'string', 'in:amount_off,percent_off,fixed_price,bogo,buy_x_get_y'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $deal = $this->dealScannerService->createManualDeal($request->user(), $validated);

        return $this->createdResponse('Deal created', ['data' => $deal]);
    }

    /**
     * Get pending scanned deals for review.
     */
    public function queue(Request $request): JsonResponse
    {
        $scans = $this->dealScannerService->getQueue($request->user());

        return response()->json(['data' => $scans]);
    }

    /**
     * Accept a single scanned deal.
     */
    public function acceptDeal(Request $request, ScannedDeal $deal): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        if ($deal->status !== 'pending') {
            return $this->errorResponse('This deal has already been processed.', 422);
        }

        $deal = $this->dealScannerService->acceptDeal($deal);

        // Auto-match to list items
        $this->dealMatchingService->matchDealToItems($deal, $request->user());

        return $this->successResponse('Deal accepted', ['data' => $deal->fresh(['store', 'matchedItem'])]);
    }

    /**
     * Accept all pending deals from a scan.
     */
    public function acceptAll(Request $request, DealScan $scan): JsonResponse
    {
        $this->authorizeScan($request, $scan);

        $count = $this->dealScannerService->acceptAllFromScan($scan);

        // Auto-match accepted deals
        $scan->deals()->where('status', 'active')->each(function ($deal) use ($request) {
            if (!$deal->matched_list_item_id) {
                $this->dealMatchingService->matchDealToItems($deal, $request->user());
            }
        });

        return $this->successResponse("Accepted {$count} deals", ['data' => ['count' => $count]]);
    }

    /**
     * Dismiss a scanned deal.
     */
    public function dismissDeal(Request $request, ScannedDeal $deal): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        $this->dealScannerService->dismissDeal($deal);

        return $this->deleteResponse('Deal dismissed');
    }

    /**
     * Update a deal (edit dates, discount, etc.).
     */
    public function update(Request $request, ScannedDeal $deal): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        $validated = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:255'],
            'product_description' => ['nullable', 'string', 'max:1000'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'deal_type' => ['sometimes', 'string', 'in:coupon,circular,flyer,bogo,clearance'],
            'discount_type' => ['sometimes', 'string', 'in:amount_off,percent_off,fixed_price,bogo,buy_x_get_y'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $deal = $this->dealScannerService->updateDeal($deal, $validated);

        return $this->successResponse('Deal updated', ['data' => $deal]);
    }

    /**
     * List deals with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:active,upcoming,expired'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);

        $deals = $this->dealScannerService->getDealLibrary(
            $request->user(),
            $request->input('status'),
            $request->input('store_id') ? (int) $request->input('store_id') : null,
        );

        return response()->json(['data' => $deals]);
    }

    /**
     * Get a single deal's details.
     */
    public function show(Request $request, ScannedDeal $deal): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        return response()->json(['data' => $deal->load(['store', 'matchedItem', 'scan'])]);
    }

    /**
     * Manually match a deal to a list item.
     */
    public function matchToItem(Request $request, ScannedDeal $deal, ListItem $item): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        // Verify the item belongs to the user
        if ($item->shoppingList->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this item.');
        }

        $deal = $this->dealMatchingService->manualMatch($deal, $item);

        return $this->successResponse('Deal matched to item', ['data' => $deal]);
    }

    /**
     * Remove a deal-to-item match.
     */
    public function unmatch(Request $request, ScannedDeal $deal): JsonResponse
    {
        $this->authorizeDeal($request, $deal);

        $deal = $this->dealMatchingService->unmatch($deal);

        return $this->successResponse('Deal unmatched', ['data' => $deal]);
    }

    /**
     * Get deal savings summary for a shopping list.
     */
    public function listSavings(Request $request, ShoppingList $list): JsonResponse
    {
        if ($list->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this list.');
        }

        $summary = $this->dealPricingService->getListSavingsSummary($list, $request->user());

        return response()->json(['data' => $summary]);
    }

    private function authorizeDeal(Request $request, ScannedDeal $deal): void
    {
        if ($deal->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this deal.');
        }
    }

    private function authorizeScan(Request $request, DealScan $scan): void
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this scan.');
        }
    }
}
