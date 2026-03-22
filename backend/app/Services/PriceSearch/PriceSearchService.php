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

        $resultsJson = json_encode($rawResults, JSON_PRETTY_PRINT);
        $itemName = $item->product_name ?? 'Unknown Item';
        $itemUpc = $item->upc ?? '';
        $itemSku = $item->sku ?? '';

        $prompt = <<<PROMPT
        Analyze the following shopping search results for the product: "{$itemName}" (UPC: {$itemUpc}, SKU: {$itemSku}).

        Raw search results:
        {$resultsJson}

        Please structure these results into a clean list of vendor prices. For each result:
        1. Verify the product is a genuine match (not an accessory or unrelated item)
        2. Normalize the price to a numeric value
        3. Include the retailer name and URL
        4. Flag if the item appears out of stock
        5. Remove duplicate listings from the same retailer (keep the lowest price)

        Return a JSON array of objects with these keys:
        - product_name (string): The exact product name
        - price (number|null): The price in USD, null if unavailable
        - retailer (string): The retailer/vendor name
        - url (string): Direct link to the product
        - in_stock (boolean): Whether the item appears to be in stock
        - image_url (string): Product image URL
        - relevance_score (number): 0-100 how relevant this result is to the searched item
        - notes (string|null): Any relevant notes (e.g., "bulk pack", "subscription price")

        Return ONLY the JSON array, no other text.
        PROMPT;

        $systemPrompt = 'You are a price comparison assistant. Return only valid JSON arrays. Do not include markdown formatting or code blocks.';

        try {
            $result = $this->llmOrchestrator->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if (!$result['success']) {
                Log::warning('PriceSearchService: LLM aggregation failed', [
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return $rawResults;
            }

            $structured = $this->parseLlmResponse($result['response']);

            if ($structured === null) {
                Log::warning('PriceSearchService: failed to parse LLM response as JSON', [
                    'response' => $result['response'],
                ]);
                return $rawResults;
            }

            return $this->sortByRelevanceAndPrice($structured);
        } catch (\Exception $e) {
            Log::error('PriceSearchService: aggregation error', [
                'error' => $e->getMessage(),
            ]);
            return $rawResults;
        }
    }

    /**
     * Aggregate raw results using LLM for a text query (no ListItem context).
     */
    private function aggregateRawResults(string $query, array $rawResults, User $user): array
    {
        $resultsJson = json_encode($rawResults, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
        Analyze the following shopping search results for: "{$query}".

        Raw search results:
        {$resultsJson}

        Structure these results into a clean, deduplicated list of vendor prices.
        Remove irrelevant results, normalize prices, and sort by best value.

        Return a JSON array of objects with these keys:
        - product_name (string)
        - price (number|null)
        - retailer (string)
        - url (string)
        - in_stock (boolean)
        - image_url (string)
        - relevance_score (number): 0-100
        - notes (string|null)

        Return ONLY the JSON array, no other text.
        PROMPT;

        $systemPrompt = 'You are a price comparison assistant. Return only valid JSON arrays. Do not include markdown formatting or code blocks.';

        try {
            $result = $this->llmOrchestrator->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if (!$result['success']) {
                return $rawResults;
            }

            $structured = $this->parseLlmResponse($result['response']);

            if ($structured === null) {
                return $rawResults;
            }

            return $this->sortByRelevanceAndPrice($structured);
        } catch (\Exception $e) {
            Log::error('PriceSearchService: text aggregation error', [
                'error' => $e->getMessage(),
            ]);
            return $rawResults;
        }
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

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        // Ensure it's a list of arrays (not an associative object)
        if (empty($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

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
