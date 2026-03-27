<?php

namespace App\Services\PriceSearch;

use App\Models\ListItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\LLM\LLMOrchestrator;
use App\Services\SettingService;
use Illuminate\Support\Facades\Log;

class PriceSearchService
{
    public function __construct(
        private readonly LLMOrchestrator $llmOrchestrator,
        private readonly PriceApiService $priceApiService,
        private readonly SettingService $settingService,
        private readonly LocationOptionsResolver $locationOptionsResolver,
    ) {}

    /**
     * Search for prices for a given list item using SERP API and AI structuring.
     *
     * @param ListItem $item The list item to search prices for
     * @param User|null $user The user context for LLM calls (uses item owner if null)
     * @return array Array of structured vendor prices
     */
    public function searchPrices(ListItem $item, ?User $user = null): array
    {
        $user = $user ?? $item->shoppingList?->user;

        if (!$user) {
            Log::warning('PriceSearchService: no user context available', [
                'list_item_id' => $item->id,
            ]);
            return [];
        }

        $query = $this->buildSearchQuery($item);

        Log::info('PriceSearchService: searching prices', [
            'list_item_id' => $item->id,
            'query' => $query,
        ]);

        $options = ['limit' => 20];

        // Use local pricing if item or its list has shop_local enabled
        $shopLocal = $item->shop_local ?? $item->shoppingList?->shop_local ?? false;
        if ($shopLocal) {
            $locationOptions = $this->locationOptionsResolver->resolveOptions($user);
            $options = array_merge($options, $locationOptions);
        }

        $rawResults = $this->priceApiService->search($query, $options);

        if (empty($rawResults)) {
            Log::info('PriceSearchService: no raw results found', [
                'list_item_id' => $item->id,
            ]);
            return [];
        }

        $structured = $this->aggregatePrices($item, $rawResults);

        return $this->filterSuppressedVendors($structured, $user);
    }

    /**
     * Text-based product price search.
     *
     * @param string $query The search query text
     * @param User|null $user The user context for LLM calls
     * @param bool $shopLocal Whether to prioritize local retailers
     * @return array Array of structured vendor prices
     */
    public function searchByQuery(string $query, ?User $user = null, bool $shopLocal = false): array
    {
        $options = [
            'limit' => 20,
        ];

        if ($shopLocal && $user) {
            $locationOptions = $this->locationOptionsResolver->resolveOptions($user);
            $options = array_merge($options, $locationOptions);
        }

        $rawResults = $this->priceApiService->search($query, $options);

        if (empty($rawResults)) {
            return [];
        }

        if (!$user) {
            return $rawResults;
        }

        $structured = $this->aggregateRawResults($query, $rawResults, $user);

        return $this->filterSuppressedVendors($structured, $user);
    }

    /**
     * Image-based product price search.
     *
     * Uses LLM vision to identify the product, then searches for prices.
     *
     * @param string $imagePath Path to the uploaded image file
     * @param User $user The user context
     * @return array Array of structured vendor prices
     */
    public function searchByImage(string $imagePath, User $user, bool $shopLocal = false): array
    {
        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $result = $this->llmOrchestrator->visionQuery(
                user: $user,
                prompt: 'Identify the product in this image. Return ONLY the product name and brand as a short search query string, nothing else.',
                imageData: $imageData,
                mimeType: $mimeType,
                systemPrompt: 'You are a product identification assistant. Return only a concise search query for the product shown.',
                mode: 'single',
            );

            if (!$result['success'] || empty($result['response'])) {
                Log::warning('PriceSearchService: image identification failed', [
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return [];
            }

            $query = trim($result['response']);

            return $this->searchByQuery($query, $user, $shopLocal);
        } catch (\Exception $e) {
            Log::error('PriceSearchService: image search error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Use LLMOrchestrator to intelligently structure raw SERP results into vendor prices.
     *
     * @param ListItem $item The list item for context
     * @param array $rawResults Raw search results from SERP API
     * @return array Structured array of vendor prices
     */
    public function aggregatePrices(ListItem $item, array $rawResults): array
    {
        $user = $item->shoppingList?->user;

        if (!$user) {
            return $rawResults;
        }

        $itemName = $item->product_name ?? 'Unknown Item';
        $itemUpc = $item->upc ?? '';
        $itemSku = $item->sku ?? '';

        $indexedJson = $this->buildIndexedResultsJson($rawResults);

        $prompt = <<<PROMPT
        Analyze the following indexed shopping search results for the product: "{$itemName}" (UPC: {$itemUpc}, SKU: {$itemSku}).

        {$indexedJson}

        For each result, decide whether it is a genuine match for the product (not an accessory or unrelated item).
        Remove duplicates from the same retailer (keep the lowest price).

        Return a JSON array containing ONLY the results worth keeping. Each object must have:
        - index (number): The original result index
        - relevance_score (number): 0-100 how relevant this result is to the searched item
        - in_stock (boolean): Whether the item appears to be in stock
        - notes (string|null): Any relevant notes (e.g., "bulk pack", "subscription price")

        Omit irrelevant or duplicate results entirely. Return ONLY the JSON array, no other text.
        PROMPT;

        return $this->queryAndMergeAnnotations($rawResults, $prompt, $user, 'aggregation');
    }

    /**
     * Aggregate raw results using LLM for a text query (no ListItem context).
     */
    private function aggregateRawResults(string $query, array $rawResults, User $user): array
    {
        $indexedJson = $this->buildIndexedResultsJson($rawResults);

        $prompt = <<<PROMPT
        Analyze the following indexed shopping search results for: "{$query}".

        {$indexedJson}

        For each result, decide whether it is relevant to the search query.
        Remove duplicates from the same retailer (keep the lowest price).

        Return a JSON array containing ONLY the results worth keeping. Each object must have:
        - index (number): The original result index
        - relevance_score (number): 0-100 how relevant this result is
        - in_stock (boolean): Whether the item appears to be in stock
        - notes (string|null): Any relevant notes (e.g., "bulk pack", "subscription price")

        Omit irrelevant or duplicate results entirely. Return ONLY the JSON array, no other text.
        PROMPT;

        return $this->queryAndMergeAnnotations($rawResults, $prompt, $user, 'text aggregation');
    }

    /**
     * Build a compact indexed summary of raw results for LLM consumption.
     */
    private function buildIndexedResultsJson(array $rawResults): string
    {
        $indexed = [];
        foreach ($rawResults as $i => $result) {
            $indexed[] = [
                'index' => $i,
                'product_name' => $result['product_name'] ?? $result['title'] ?? null,
                'price' => $result['price'] ?? null,
                'retailer' => $result['retailer'] ?? $result['source'] ?? null,
            ];
        }

        return json_encode($indexed, JSON_PRETTY_PRINT);
    }

    /**
     * Send annotation prompt to LLM and merge results back with original data.
     */
    private function queryAndMergeAnnotations(array $rawResults, string $prompt, User $user, string $context): array
    {
        $systemPrompt = 'You are a price comparison assistant. Return only valid JSON arrays. Do not include markdown formatting or code blocks.';

        try {
            $result = $this->llmOrchestrator->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if (!$result['success']) {
                Log::warning("PriceSearchService: LLM {$context} failed", [
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return $rawResults;
            }

            $annotations = $this->parseLlmResponse($result['response']);

            if ($annotations === null) {
                Log::warning("PriceSearchService: failed to parse LLM {$context} response as JSON", [
                    'response' => $result['response'],
                ]);
                return $rawResults;
            }

            return $this->mergeAnnotations($rawResults, $annotations);
        } catch (\Exception $e) {
            Log::error("PriceSearchService: {$context} error", [
                'error' => $e->getMessage(),
            ]);
            return $rawResults;
        }
    }

    /**
     * Merge LLM annotations back into the original raw results.
     */
    private function mergeAnnotations(array $rawResults, array $annotations): array
    {
        $merged = [];

        foreach ($annotations as $annotation) {
            $index = $annotation['index'] ?? null;

            if ($index === null || !isset($rawResults[$index])) {
                continue;
            }

            $merged[] = array_merge($rawResults[$index], [
                'relevance_score' => $annotation['relevance_score'] ?? 50,
                'in_stock' => $annotation['in_stock'] ?? true,
                'notes' => $annotation['notes'] ?? null,
            ]);
        }

        return $this->sortByRelevanceAndPrice($merged);
    }

    /**
     * Build a search query string from a ListItem.
     */
    private function buildSearchQuery(ListItem $item): string
    {
        // Prefer product_query if set (usually more specific), fall back to product_name
        if ($item->product_query) {
            return $item->product_query;
        }

        $parts = [];

        if ($item->product_name) {
            $parts[] = $item->product_name;
        }

        if ($item->sku) {
            $parts[] = $item->sku;
        }

        if ($item->upc) {
            $parts[] = $item->upc;
        }

        return implode(' ', $parts);
    }

    /**
     * Parse an LLM response string into a validated array of result objects.
     * Returns null if the response cannot be parsed or is not a valid array of objects.
     */
    private function parseLlmResponse(string $response): ?array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/', '', trim($response));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $decoded = json_decode($cleaned, true);

        // If parsing failed, attempt to salvage truncated JSON arrays
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $decoded = $this->salvageTruncatedJson($cleaned);
            if ($decoded === null) {
                return null;
            }
        }

        // Ensure it's a list of arrays (not an associative object)
        if (empty($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Attempt to salvage a truncated JSON array by finding the last complete object.
     */
    private function salvageTruncatedJson(string $json): ?array
    {
        $json = trim($json);

        if (!str_starts_with($json, '[')) {
            return null;
        }

        $lastBrace = strrpos($json, '},');
        if ($lastBrace === false) {
            $lastBrace = strrpos($json, '}');
            if ($lastBrace === false) {
                return null;
            }
        }

        $decoded = json_decode(substr($json, 0, $lastBrace + 1) . ']', true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        Log::info('PriceSearchService: salvaged truncated JSON response', [
            'original_length' => strlen($json),
            'salvaged_items' => count($decoded),
        ]);

        return $decoded;
    }

    /**
     * Sort structured results by relevance score (desc), then price (asc).
     */
    private function sortByRelevanceAndPrice(array $results): array
    {
        usort($results, function (array $a, array $b): int {
            $relevanceDiff = ($b['relevance_score'] ?? 0) - ($a['relevance_score'] ?? 0);
            if ($relevanceDiff !== 0) {
                return $relevanceDiff;
            }
            return ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX);
        });

        return $results;
    }

    /**
     * Filter out results from vendors the user has suppressed.
     */
    private function filterSuppressedVendors(array $results, User $user): array
    {
        $suppressed = Setting::where('user_id', $user->id)
            ->where('group', 'shopping')
            ->where('key', 'suppressed_vendors')
            ->first();

        if (!$suppressed || empty($suppressed->value)) {
            return $results;
        }

        $suppressedList = array_map('strtolower', (array) $suppressed->value);

        return array_values(array_filter($results, function (array $result) use ($suppressedList) {
            $retailer = strtolower($result['retailer'] ?? '');
            return !in_array($retailer, $suppressedList);
        }));
    }
}
