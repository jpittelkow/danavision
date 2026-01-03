<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Services\AI\AIService;
use App\Services\AI\MultiAIService;
use App\Services\PriceApi\PriceApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmartAddController extends Controller
{
    /**
     * Show the Smart Add page.
     */
    public function index(Request $request): Response
    {
        $lists = $request->user()
            ->shoppingLists()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
        ]);
    }

    /**
     * Analyze an image using AI (with multi-provider aggregation if available).
     */
    public function analyzeImage(Request $request): Response
    {
        $validated = $request->validate([
            'image' => ['required', 'string'], // Base64 encoded image (data URL)
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = $request->user()->id;
        $lists = $request->user()
            ->shoppingLists()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Parse the base64 data URL
        $imageData = $validated['image'];
        $mimeType = 'image/jpeg';
        $base64 = $imageData;

        if (str_starts_with($imageData, 'data:')) {
            $parts = explode(',', $imageData, 2);
            if (count($parts) === 2) {
                preg_match('/data:(.*?);base64/', $parts[0], $matches);
                $mimeType = $matches[1] ?? 'image/jpeg';
                $base64 = $parts[1];
            }
        }

        // Build the analysis prompt
        $prompt = $this->buildAnalysisPrompt($validated['description'] ?? null);

        // Try multi-AI service first for aggregation
        $multiAI = MultiAIService::forUser($userId);
        $analysis = [
            'product_name' => null,
            'brand' => null,
            'model' => null,
            'category' => null,
            'search_terms' => [],
            'confidence' => 0,
            'error' => null,
            'providers_used' => [],
        ];

        if ($multiAI->isAvailable() && $multiAI->getProviderCount() > 1) {
            // Use multi-provider aggregation
            $result = $multiAI->analyzeImageWithAllProviders($base64, $mimeType, $prompt);
            
            if ($result['aggregated_response']) {
                $parsed = $this->parseJsonResponse($result['aggregated_response']);
                $analysis = array_merge($analysis, $parsed);
                $analysis['providers_used'] = array_keys(
                    array_filter($result['individual_responses'], fn($r) => $r['error'] === null)
                );
            } else {
                $analysis['error'] = $result['error'] ?? 'All AI providers failed to respond';
            }
        } else {
            // Fall back to single provider
            $aiService = AIService::forUser($userId);
            
            if (!$aiService) {
                $analysis['error'] = 'No AI provider configured. Please set up an AI provider in Settings.';
            } else {
                try {
                    $response = $aiService->analyzeImage($base64, $mimeType, $prompt);
                    $parsed = $this->parseJsonResponse($response);
                    $analysis = array_merge($analysis, $parsed);
                    $analysis['providers_used'] = [$aiService->getProviderType()];
                } catch (\Exception $e) {
                    $analysis['error'] = 'AI analysis failed: ' . $e->getMessage();
                }
            }
        }

        // Search for prices if we identified a product
        $priceResults = [];
        $searchError = null;

        if (!empty($analysis['product_name'])) {
            $searchQuery = $analysis['product_name'];
            if (!empty($analysis['brand']) && !str_contains(strtolower($analysis['product_name']), strtolower($analysis['brand']))) {
                $searchQuery = $analysis['brand'] . ' ' . $analysis['product_name'];
            }

            $priceService = PriceApiService::forUser($userId);
            $searchResult = $priceService->search($searchQuery);
            $priceResults = $searchResult->results;
            $searchError = $searchResult->error;
        }

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'analysis' => $analysis,
            'price_results' => $priceResults,
            'search_error' => $searchError,
            'uploaded_image' => $validated['image'],
        ]);
    }

    /**
     * Search for a product by text query.
     */
    public function searchText(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;
        $lists = $request->user()
            ->shoppingLists()
            ->orderBy('name')
            ->get(['id', 'name']);

        $priceService = PriceApiService::forUser($userId);
        $searchResult = $priceService->search($validated['query']);

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'analysis' => [
                'product_name' => $validated['query'],
                'brand' => null,
                'model' => null,
                'category' => null,
                'search_terms' => [],
                'confidence' => 100,
                'error' => null,
                'providers_used' => [],
            ],
            'price_results' => $searchResult->results,
            'search_error' => $searchResult->error,
            'search_query' => $validated['query'],
        ]);
    }

    /**
     * Add the identified product to a shopping list.
     */
    public function addToList(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:shopping_lists,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'product_query' => ['nullable', 'string', 'max:255'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'product_image_url' => ['nullable', 'url', 'max:2048'],
            'sku' => ['nullable', 'string', 'max:100'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'current_retailer' => ['nullable', 'string', 'max:255'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['in:low,medium,high'],
        ]);

        $list = ShoppingList::findOrFail($validated['list_id']);
        $this->authorize('update', $list);

        $list->items()->create([
            'added_by_user_id' => $request->user()->id,
            'product_name' => $validated['product_name'],
            'product_query' => $validated['product_query'] ?? $validated['product_name'],
            'product_url' => $validated['product_url'] ?? null,
            'product_image_url' => $validated['product_image_url'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'current_price' => $validated['current_price'] ?? null,
            'lowest_price' => $validated['current_price'] ?? null,
            'highest_price' => $validated['current_price'] ?? null,
            'current_retailer' => $validated['current_retailer'] ?? null,
            'target_price' => $validated['target_price'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'last_checked_at' => $validated['current_price'] ? now() : null,
        ]);

        return redirect()->route('lists.show', $list)
            ->with('success', 'Item added to ' . $list->name . '!');
    }

    /**
     * Build the AI analysis prompt.
     */
    protected function buildAnalysisPrompt(?string $description): string
    {
        $contextPart = '';
        if ($description) {
            $contextPart = "Additional context from user: {$description}\n\n";
        }

        return <<<PROMPT
{$contextPart}Analyze this product image and identify the product. Return a JSON object with the following fields:
{
    "product_name": "The specific product name (include model number if visible)",
    "brand": "The brand name if visible or identifiable",
    "model": "The model number/name if visible",
    "category": "Product category (e.g., Electronics, Kitchen Appliance, Clothing, etc.)",
    "search_terms": ["array", "of", "suggested", "search", "terms", "for", "price", "lookup"],
    "confidence": 85
}

Guidelines:
- Be as specific as possible about the product identification
- If you can identify the exact make/model, include it
- The confidence score should be 0-100 based on how certain you are
- search_terms should include variations that would help find this product for price comparison
- If you cannot identify something with confidence, use null for that field
- Only return the JSON object, no other text
PROMPT;
    }

    /**
     * Parse JSON from AI response.
     */
    protected function parseJsonResponse(string $response): array
    {
        $defaults = [
            'product_name' => null,
            'brand' => null,
            'model' => null,
            'category' => null,
            'search_terms' => [],
            'confidence' => 0,
        ];

        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return array_merge($defaults, array_filter($json, fn($v) => $v !== null));
            }
        }

        return $defaults;
    }
}
