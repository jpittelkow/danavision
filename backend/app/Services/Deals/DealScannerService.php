<?php

namespace App\Services\Deals;

use App\Models\AIJob;
use App\Models\DealScan;
use App\Models\ScannedDeal;
use App\Models\User;
use App\Services\LLM\LLMOrchestrator;
use App\Services\PriceSearch\VendorNameResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class DealScannerService
{
    public function __construct(
        private readonly LLMOrchestrator $llmOrchestrator,
        private readonly VendorNameResolver $vendorNameResolver,
    ) {}

    /**
     * Scan an image for deals/coupons using AI vision.
     */
    public function scanImage(string $imagePath, User $user, ?string $scanType = null): DealScan
    {
        $aiJob = AIJob::create([
            'user_id' => $user->id,
            'type' => 'deal_scan',
            'status' => 'pending',
            'input_data' => ['image_path' => $imagePath, 'scan_type' => $scanType],
        ]);

        $dealScan = DealScan::create([
            'user_id' => $user->id,
            'ai_job_id' => $aiJob->id,
            'image_path' => $imagePath,
            'scan_type' => $scanType ?? 'coupon',
            'status' => 'processing',
        ]);

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $prompt = $this->buildDealExtractionPrompt($scanType);
            $systemPrompt = $this->buildDealExtractionSystemPrompt();

            $result = $this->llmOrchestrator->visionQuery(
                user: $user,
                prompt: $prompt,
                imageData: $imageData,
                mimeType: $mimeType,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if ($result['success']) {
                $deals = $this->parseDealResponse($result['response']);
                $created = $this->createDealRecords($user, $deals, $dealScan, $aiJob);

                $dealScan->update([
                    'status' => 'completed',
                    'deals_extracted' => count($created),
                ]);

                $aiJob->markCompleted(['deals_count' => count($created)]);
            } else {
                $error = $result['error'] ?? 'Vision query failed';
                $dealScan->update([
                    'status' => 'failed',
                    'error_message' => $error,
                ]);
                $aiJob->markFailed($error);
            }
        } catch (\Exception $e) {
            Log::error('DealScannerService: scan failed', [
                'deal_scan_id' => $dealScan->id,
                'error' => $e->getMessage(),
            ]);

            $dealScan->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $aiJob->markFailed($e->getMessage());
        }

        return $dealScan->fresh(['deals']);
    }

    /**
     * Create a deal manually from user-entered form data.
     */
    public function createManualDeal(User $user, array $data): ScannedDeal
    {
        $storeId = null;
        $storeNameRaw = $data['store_name'] ?? null;

        if ($storeNameRaw) {
            $store = $this->vendorNameResolver->resolve($storeNameRaw);
            $storeId = $store?->id;
        } elseif (!empty($data['store_id'])) {
            $storeId = $data['store_id'];
        }

        $deal = ScannedDeal::create([
            'user_id' => $user->id,
            'store_id' => $storeId,
            'store_name_raw' => $storeNameRaw,
            'product_name' => $data['product_name'],
            'product_description' => $data['product_description'] ?? null,
            'deal_type' => $data['deal_type'] ?? 'coupon',
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'] ?? null,
            'sale_price' => $data['sale_price'] ?? null,
            'original_price' => $data['original_price'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'status' => 'active',
            'content_hash' => $this->computeContentHash($data),
            'confidence' => 1.00,
        ]);

        Log::info('DealScannerService: manual deal created', [
            'deal_id' => $deal->id,
            'product' => $deal->product_name,
        ]);

        return $deal->load('store');
    }

    /**
     * Get pending deals for review (grouped by scan).
     */
    public function getQueue(User $user): Collection
    {
        return DealScan::where('user_id', $user->id)
            ->whereHas('deals', fn ($q) => $q->where('status', 'pending'))
            ->with(['deals' => fn ($q) => $q->where('status', 'pending')->with('store')])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Accept a single deal.
     */
    public function acceptDeal(ScannedDeal $deal): ScannedDeal
    {
        $deal->update(['status' => 'active']);

        if ($deal->source_scan_id) {
            $deal->scan?->incrementAccepted();
        }

        Log::info('DealScannerService: deal accepted', [
            'deal_id' => $deal->id,
            'product' => $deal->product_name,
        ]);

        return $deal->fresh(['store', 'matchedItem']);
    }

    /**
     * Accept all pending deals from a scan.
     */
    public function acceptAllFromScan(DealScan $scan): int
    {
        $count = $scan->deals()
            ->where('status', 'pending')
            ->update(['status' => 'active']);

        $scan->increment('deals_accepted', $count);

        Log::info('DealScannerService: all deals accepted from scan', [
            'scan_id' => $scan->id,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Dismiss a deal.
     */
    public function dismissDeal(ScannedDeal $deal): void
    {
        $deal->update(['status' => 'dismissed']);

        if ($deal->source_scan_id) {
            $deal->scan?->incrementDismissed();
        }

        Log::info('DealScannerService: deal dismissed', [
            'deal_id' => $deal->id,
        ]);
    }

    /**
     * Get active, non-expired deals for a user.
     */
    public function getActiveDeals(User $user, ?int $storeId = null): Collection
    {
        $query = ScannedDeal::where('user_id', $user->id)
            ->active()
            ->with(['store', 'matchedItem']);

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->orderBy('valid_to', 'asc')->get();
    }

    /**
     * Get deals filtered by status for the deal library.
     */
    public function getDealLibrary(User $user, ?string $status = null, ?int $storeId = null): Collection
    {
        $query = ScannedDeal::where('user_id', $user->id)
            ->with(['store', 'matchedItem']);

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'upcoming') {
            $query->upcoming();
        } elseif ($status === 'expired') {
            $query->expired();
        } else {
            // All non-pending, non-dismissed deals
            $query->whereIn('status', ['active', 'expired']);
        }

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update a deal.
     */
    public function updateDeal(ScannedDeal $deal, array $data): ScannedDeal
    {
        $fillable = [
            'product_name', 'product_description', 'deal_type', 'discount_type',
            'discount_value', 'sale_price', 'original_price', 'conditions',
            'valid_from', 'valid_to',
        ];

        $deal->update(array_intersect_key($data, array_flip($fillable)));

        // Re-resolve store if store_name changed
        if (isset($data['store_name'])) {
            $store = $this->vendorNameResolver->resolve($data['store_name']);
            $deal->update([
                'store_id' => $store?->id,
                'store_name_raw' => $data['store_name'],
            ]);
        } elseif (isset($data['store_id'])) {
            $deal->update(['store_id' => $data['store_id']]);
        }

        return $deal->fresh(['store', 'matchedItem']);
    }

    /**
     * Build the vision prompt for deal extraction.
     */
    private function buildDealExtractionPrompt(?string $scanType = null): string
    {
        $typeHint = match ($scanType) {
            'circular' => 'weekly circular/ad',
            'flyer' => 'store flyer',
            default => 'coupon or advertisement',
        };

        return <<<PROMPT
        Analyze this image of a {$typeHint}. Extract EVERY deal visible in the image.

        For each deal, provide:
        - product_name: specific product name (e.g., "Cheerios Original 12oz")
        - store_name: the store offering the deal (e.g., "Kroger", "Walmart"), or null if not identifiable
        - deal_type: one of "coupon", "circular", "flyer", "bogo", "clearance"
        - discount_type: one of "amount_off", "percent_off", "fixed_price", "bogo", "buy_x_get_y"
        - discount_value: numeric value (e.g., 0.50 for $0.50 off, 25 for 25% off), null if not applicable
        - sale_price: final advertised price if shown (null if not stated)
        - original_price: regular/crossed-out price if shown (null if not stated)
        - valid_from: start date in YYYY-MM-DD format (null if not shown). Assume current year if only month/day shown
        - valid_to: end date in YYYY-MM-DD format (null if not shown). Assume current year if only month/day shown
        - conditions: object with any restrictions, e.g., {"min_purchase": 10.00, "limit_per_customer": 2, "requires_loyalty_card": true}
        - confidence: your confidence this extraction is correct (0.0-1.0)

        Important:
        - Extract ALL deals, even if there are dozens
        - If a date range like "Valid 3/15 - 3/21" is shown, assume the current year (2026)
        - For BOGO deals, set discount_type to "bogo" and discount_value to null
        - For "Buy 2 Get 1 Free" style deals, set discount_type to "buy_x_get_y" and include buy_quantity and get_quantity in conditions

        Return a JSON array of all deals found.
        Return ONLY the JSON array, no other text or markdown formatting.
        PROMPT;
    }

    /**
     * Build the system prompt for deal extraction.
     */
    private function buildDealExtractionSystemPrompt(): string
    {
        return 'You are a deal and coupon extraction assistant for a grocery shopping application. '
            . 'You are expert at reading coupons, weekly circulars, store flyers, and advertisements. '
            . 'Extract every visible deal with accurate pricing and date information. '
            . 'Always return valid JSON arrays. Do not include markdown formatting or code blocks.';
    }

    /**
     * Parse the AI response into a structured array of deals.
     */
    private function parseDealResponse(string $response): array
    {
        $cleaned = preg_replace('/^```(?:json)?\s*/', '', trim($response));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('DealScannerService: failed to parse deal response', [
                'response' => $response,
                'json_error' => json_last_error_msg(),
            ]);
            return [];
        }

        if (!is_array($parsed)) {
            return [];
        }

        return $parsed;
    }

    /**
     * Create ScannedDeal records from parsed AI response, with dedup.
     */
    private function createDealRecords(User $user, array $deals, DealScan $scan, AIJob $aiJob): array
    {
        $created = [];

        foreach ($deals as $dealData) {
            $productName = $dealData['product_name'] ?? null;
            if (!$productName) {
                continue;
            }

            $hash = $this->computeContentHash($dealData);

            // Skip duplicates for this user
            $existing = ScannedDeal::where('user_id', $user->id)
                ->where('content_hash', $hash)
                ->whereIn('status', ['pending', 'active'])
                ->first();

            if ($existing) {
                Log::info('DealScannerService: skipping duplicate deal', [
                    'product' => $productName,
                    'existing_id' => $existing->id,
                ]);
                continue;
            }

            // Resolve store
            $storeName = $dealData['store_name'] ?? null;
            $storeId = null;
            if ($storeName) {
                $store = $this->vendorNameResolver->resolve($storeName);
                $storeId = $store?->id;
            }

            $deal = ScannedDeal::create([
                'user_id' => $user->id,
                'ai_job_id' => $aiJob->id,
                'source_scan_id' => $scan->id,
                'store_id' => $storeId,
                'store_name_raw' => $storeName,
                'product_name' => $productName,
                'product_description' => $dealData['product_description'] ?? null,
                'deal_type' => $dealData['deal_type'] ?? $scan->scan_type,
                'discount_type' => $dealData['discount_type'] ?? 'amount_off',
                'discount_value' => $dealData['discount_value'] ?? null,
                'sale_price' => $dealData['sale_price'] ?? null,
                'original_price' => $dealData['original_price'] ?? null,
                'conditions' => $dealData['conditions'] ?? null,
                'valid_from' => $dealData['valid_from'] ?? null,
                'valid_to' => $dealData['valid_to'] ?? null,
                'status' => 'pending',
                'content_hash' => $hash,
                'confidence' => $dealData['confidence'] ?? null,
            ]);

            $created[] = $deal;
        }

        return $created;
    }

    /**
     * Compute a content hash for duplicate detection.
     */
    private function computeContentHash(array $dealData): string
    {
        $normalized = strtolower(trim($dealData['product_name'] ?? ''))
            . '|' . strtolower(trim($dealData['store_name'] ?? $dealData['store_name_raw'] ?? ''))
            . '|' . ($dealData['discount_type'] ?? '')
            . '|' . ($dealData['discount_value'] ?? '')
            . '|' . ($dealData['sale_price'] ?? '')
            . '|' . ($dealData['valid_from'] ?? '')
            . '|' . ($dealData['valid_to'] ?? '');

        return hash('sha256', $normalized);
    }
}
