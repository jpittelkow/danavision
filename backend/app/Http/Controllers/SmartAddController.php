<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Services\AI\AIService;
use App\Services\AI\AIPriceSearchService;
use App\Services\AI\MultiAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'upc' => null,
            'is_generic' => false,
            'unit_of_measure' => null,
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

        // Search for prices if we identified a product using AI
        $priceResults = [];
        $searchError = null;

        if (!empty($analysis['product_name'])) {
            $searchQuery = $analysis['product_name'];
            if (!empty($analysis['brand']) && !str_contains(strtolower($analysis['product_name']), strtolower($analysis['brand']))) {
                $searchQuery = $analysis['brand'] . ' ' . $analysis['product_name'];
            }

            $priceService = AIPriceSearchService::forUser($userId);
            $searchResult = $priceService->search($searchQuery, [
                'is_generic' => $analysis['is_generic'] ?? false,
                'unit_of_measure' => $analysis['unit_of_measure'] ?? null,
            ]);
            $priceResults = $searchResult->results;
            $searchError = $searchResult->error;
            
            // Update analysis with any generic info from the search
            if ($searchResult->isGeneric && !$analysis['is_generic']) {
                $analysis['is_generic'] = true;
                $analysis['unit_of_measure'] = $searchResult->unitOfMeasure;
            }
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
     * Search for a product by text query using AI.
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

        // Use AI to classify the query first (for generic item detection)
        $genericInfo = $this->classifySearchQuery($validated['query'], $userId);

        // Search using AI-powered price search
        $priceService = AIPriceSearchService::forUser($userId);
        $searchResult = $priceService->search($validated['query'], [
            'is_generic' => $genericInfo['is_generic'],
            'unit_of_measure' => $genericInfo['unit_of_measure'],
        ]);

        // Use search result's generic info if available
        $isGeneric = $searchResult->isGeneric || $genericInfo['is_generic'];
        $unitOfMeasure = $searchResult->unitOfMeasure ?? $genericInfo['unit_of_measure'];

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'analysis' => [
                'product_name' => $validated['query'],
                'brand' => null,
                'model' => null,
                'category' => $genericInfo['category'],
                'is_generic' => $isGeneric,
                'unit_of_measure' => $unitOfMeasure,
                'search_terms' => [],
                'confidence' => 100,
                'error' => null,
                'providers_used' => $searchResult->providersUsed,
            ],
            'price_results' => $searchResult->results,
            'search_error' => $searchResult->error,
            'search_query' => $validated['query'],
        ]);
    }

    /**
     * Get detailed price information for a specific product.
     * Called when user clicks "Add" to get retailer pricing options.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPriceDetails(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'upc' => ['nullable', 'string', 'max:20'],
        ]);

        $userId = $request->user()->id;

        try {
            // Search for detailed pricing using AI-powered price search
            $priceService = AIPriceSearchService::forUser($userId);
            
            // If UPC is provided, search by UPC for more accurate results
            $searchQuery = $validated['product_name'];
            if (!empty($validated['upc'])) {
                $searchQuery = $validated['upc'] . ' ' . $validated['product_name'];
            }
            
            $searchResult = $priceService->search($searchQuery);

            return response()->json([
                'results' => $searchResult->results,
                'lowest_price' => $searchResult->lowestPrice,
                'highest_price' => $searchResult->highestPrice,
                'providers_used' => $searchResult->providersUsed,
                'is_generic' => $searchResult->isGeneric,
                'unit_of_measure' => $searchResult->unitOfMeasure,
                'error' => $searchResult->error,
            ]);
        } catch (\Exception $e) {
            \Log::error('Price details search failed', [
                'product_name' => $validated['product_name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'results' => [],
                'lowest_price' => null,
                'highest_price' => null,
                'providers_used' => [],
                'is_generic' => false,
                'unit_of_measure' => null,
                'error' => 'Failed to fetch price details: ' . $e->getMessage(),
            ]);
        }
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
            'uploaded_image' => ['nullable', 'string'], // Base64 data URL
            'sku' => ['nullable', 'string', 'max:100'],
            'upc' => ['nullable', 'string', 'max:20'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'current_retailer' => ['nullable', 'string', 'max:255'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['in:low,medium,high'],
            'is_generic' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
        ]);

        $list = ShoppingList::findOrFail($validated['list_id']);
        $this->authorize('update', $list);

        // Handle uploaded image (base64)
        $uploadedImagePath = null;
        if (!empty($validated['uploaded_image'])) {
            $uploadedImagePath = $this->saveUploadedImage($validated['uploaded_image'], $request->user()->id);
        }

        $list->items()->create([
            'added_by_user_id' => $request->user()->id,
            'product_name' => $validated['product_name'],
            'product_query' => $validated['product_query'] ?? $validated['product_name'],
            'product_url' => $validated['product_url'] ?? null,
            'product_image_url' => $validated['product_image_url'] ?? null,
            'uploaded_image_path' => $uploadedImagePath,
            'sku' => $validated['sku'] ?? null,
            'upc' => $validated['upc'] ?? null,
            'current_price' => $validated['current_price'] ?? null,
            'lowest_price' => $validated['current_price'] ?? null,
            'highest_price' => $validated['current_price'] ?? null,
            'current_retailer' => $validated['current_retailer'] ?? null,
            'target_price' => $validated['target_price'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'is_generic' => $validated['is_generic'] ?? false,
            'unit_of_measure' => $validated['unit_of_measure'] ?? null,
            'last_checked_at' => ($validated['current_price'] ?? null) ? now() : null,
        ]);

        return redirect()->route('lists.show', $list)
            ->with('success', 'Item added to ' . $list->name . '!');
    }

    /**
     * Save an uploaded base64 image to storage.
     */
    protected function saveUploadedImage(string $base64Data, int $userId): ?string
    {
        try {
            // Parse the base64 data URL
            $imageData = $base64Data;
            $extension = 'jpg';

            if (str_starts_with($base64Data, 'data:')) {
                $parts = explode(',', $base64Data, 2);
                if (count($parts) === 2) {
                    preg_match('/data:image\/(.*?);base64/', $parts[0], $matches);
                    $extension = $matches[1] ?? 'jpg';
                    if ($extension === 'jpeg') {
                        $extension = 'jpg';
                    }
                    $imageData = $parts[1];
                }
            }

            // Decode the base64 data
            $decodedImage = base64_decode($imageData);
            if ($decodedImage === false) {
                return null;
            }

            // Generate a unique filename
            $filename = 'item-images/' . $userId . '/' . Str::uuid() . '.' . $extension;

            // Save to storage
            Storage::disk('public')->put($filename, $decodedImage);

            return $filename;
        } catch (\Exception $e) {
            \Log::error('Failed to save uploaded image: ' . $e->getMessage());
            return null;
        }
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
    "category": "Product category (e.g., Electronics, Kitchen Appliance, Produce, Dairy, etc.)",
    "upc": "123456789012 or null if not visible or not applicable",
    "is_generic": false,
    "unit_of_measure": null,
    "search_terms": ["array", "of", "suggested", "search", "terms", "for", "price", "lookup"],
    "confidence": 85
}

Guidelines:
- Be as specific as possible about the product identification
- If you can identify the exact make/model, include it
- The confidence score should be 0-100 based on how certain you are
- search_terms should include variations that would help find this product for price comparison
- If you cannot identify something with confidence, use null for that field

UPC/Barcode:
- Include the UPC (Universal Product Code) if visible on the product packaging
- UPCs are 12-digit barcodes found on packaged retail products
- If the UPC is visible in the image, include it in the "upc" field
- If you can identify the product and know its UPC, include it
- Generic items like produce, bulk goods, and deli items do NOT have UPCs - use null for these

Generic vs Specific Items:
- Set "is_generic": true for items sold by weight, volume, or count without a specific SKU (e.g., fruits, vegetables, meat, bulk goods, dairy, deli items)
- Set "is_generic": false for branded products with specific model numbers or SKUs (e.g., electronics, appliances, specific packaged goods)
- If "is_generic" is true, set "unit_of_measure" to the most appropriate unit:
  - Weight: "lb" (pound), "oz" (ounce), "kg" (kilogram), "g" (gram)
  - Volume: "gallon", "liter", "quart", "pint", "fl_oz" (fluid ounce)
  - Count: "each", "dozen"
- Examples:
  - Blueberries → is_generic: true, unit_of_measure: "lb" or "oz", upc: null
  - Ground beef → is_generic: true, unit_of_measure: "lb", upc: null
  - Milk → is_generic: true, unit_of_measure: "gallon", upc: null
  - Eggs → is_generic: true, unit_of_measure: "dozen", upc: null
  - Avocados → is_generic: true, unit_of_measure: "each", upc: null
  - Sony WH-1000XM5 → is_generic: false, unit_of_measure: null, upc: "027242917576"

Only return the JSON object, no other text.
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
            'upc' => null,
            'is_generic' => false,
            'unit_of_measure' => null,
            'search_terms' => [],
            'confidence' => 0,
        ];

        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Ensure is_generic is a boolean
                if (isset($json['is_generic'])) {
                    $json['is_generic'] = (bool) $json['is_generic'];
                }
                return array_merge($defaults, array_filter($json, fn($v) => $v !== null));
            }
        }

        return $defaults;
    }

    /**
     * Classify a text search query to determine if it's a generic item.
     */
    protected function classifySearchQuery(string $query, int $userId): array
    {
        $defaults = [
            'is_generic' => false,
            'unit_of_measure' => null,
            'category' => null,
            'providers_used' => [],
        ];

        // Try to use AI to classify the query
        $aiService = AIService::forUser($userId);
        
        if (!$aiService) {
            // Fallback: use simple keyword matching for common generic items
            return $this->fallbackClassifyQuery($query);
        }

        try {
            $prompt = $this->buildClassificationPrompt($query);
            $response = $aiService->complete($prompt);
            
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'is_generic' => (bool) ($json['is_generic'] ?? false),
                        'unit_of_measure' => $json['unit_of_measure'] ?? null,
                        'category' => $json['category'] ?? null,
                        'providers_used' => [$aiService->getProviderType()],
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('AI classification failed: ' . $e->getMessage());
        }

        // Fallback to simple matching
        return $this->fallbackClassifyQuery($query);
    }

    /**
     * Build the prompt for classifying a search query.
     */
    protected function buildClassificationPrompt(string $query): string
    {
        return <<<PROMPT
Classify this product search query: "{$query}"

Determine if this is a generic item (sold by weight/volume/count) or a specific product (with SKU/model number).

Return a JSON object:
{
    "is_generic": true or false,
    "unit_of_measure": "lb", "oz", "kg", "g", "gallon", "liter", "quart", "pint", "fl_oz", "each", or "dozen" (only if is_generic is true, otherwise null),
    "category": "Product category"
}

Examples:
- "blueberries" → {"is_generic": true, "unit_of_measure": "lb", "category": "Produce"}
- "ground beef" → {"is_generic": true, "unit_of_measure": "lb", "category": "Meat"}
- "milk" → {"is_generic": true, "unit_of_measure": "gallon", "category": "Dairy"}
- "eggs" → {"is_generic": true, "unit_of_measure": "dozen", "category": "Dairy"}
- "Sony WH-1000XM5" → {"is_generic": false, "unit_of_measure": null, "category": "Electronics"}
- "iPhone 15" → {"is_generic": false, "unit_of_measure": null, "category": "Electronics"}

Only return the JSON object, no other text.
PROMPT;
    }

    /**
     * Fallback classification using keyword matching.
     */
    protected function fallbackClassifyQuery(string $query): array
    {
        $queryLower = strtolower($query);
        
        // Common generic items by category
        $genericPatterns = [
            // Produce - sold by lb
            'lb' => [
                'apple', 'banana', 'orange', 'grape', 'strawberr', 'blueberr', 'raspberr', 
                'blackberr', 'cherry', 'peach', 'pear', 'plum', 'mango', 'pineapple',
                'watermelon', 'cantaloupe', 'honeydew', 'kiwi', 'lemon', 'lime',
                'tomato', 'potato', 'onion', 'carrot', 'celery', 'broccoli', 'cauliflower',
                'lettuce', 'spinach', 'kale', 'cabbage', 'cucumber', 'zucchini', 'squash',
                'pepper', 'mushroom', 'garlic', 'ginger',
                'beef', 'chicken', 'pork', 'turkey', 'lamb', 'steak', 'ground',
                'bacon', 'sausage', 'ham', 'fish', 'salmon', 'shrimp', 'crab',
                'deli', 'cheese',
            ],
            // Volume - sold by gallon
            'gallon' => ['milk', 'juice', 'water'],
            // Count - sold by dozen
            'dozen' => ['egg', 'donut', 'bagel', 'roll'],
            // Count - sold by each
            'each' => ['avocado', 'coconut', 'pumpkin', 'melon'],
        ];

        foreach ($genericPatterns as $unit => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($queryLower, $pattern)) {
                    return [
                        'is_generic' => true,
                        'unit_of_measure' => $unit,
                        'category' => $this->guessCategoryFromQuery($queryLower),
                        'providers_used' => [],
                    ];
                }
            }
        }

        return [
            'is_generic' => false,
            'unit_of_measure' => null,
            'category' => null,
            'providers_used' => [],
        ];
    }

    /**
     * Guess the category from a query string.
     */
    protected function guessCategoryFromQuery(string $query): ?string
    {
        $categories = [
            'Produce' => ['apple', 'banana', 'orange', 'grape', 'berry', 'tomato', 'potato', 'onion', 'carrot', 'lettuce', 'spinach', 'broccoli', 'pepper', 'cucumber'],
            'Meat' => ['beef', 'chicken', 'pork', 'turkey', 'lamb', 'steak', 'ground', 'bacon', 'sausage', 'ham'],
            'Seafood' => ['fish', 'salmon', 'shrimp', 'crab', 'lobster', 'tuna'],
            'Dairy' => ['milk', 'cheese', 'yogurt', 'butter', 'cream', 'egg'],
            'Bakery' => ['bread', 'donut', 'bagel', 'roll', 'muffin', 'cake'],
            'Beverages' => ['juice', 'water', 'soda', 'coffee', 'tea'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($query, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Stream price search results using Server-Sent Events.
     * 
     * This provides real-time updates as AI providers search for prices,
     * showing which AI is being queried and results as they arrive.
     */
    public function streamSearch(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;
        $query = $validated['query'];

        return response()->stream(function () use ($userId, $query) {
            // Disable output buffering for real-time streaming
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send initial searching event
            $this->sendSSE('searching', [
                'api' => 'AI Search',
                'status' => 'Initializing AI-powered search...',
            ]);

            $priceService = AIPriceSearchService::forUser($userId);
            
            if (!$priceService->isAvailable()) {
                $this->sendSSE('error', [
                    'message' => 'No AI providers configured. Please set up an AI provider in Settings.',
                ]);
                $this->sendSSE('complete', [
                    'total' => 0,
                    'apis_queried' => [],
                ]);
                return;
            }

            $providerCount = $priceService->getProviderCount();
            
            // Send searching status
            $this->sendSSE('searching', [
                'api' => 'AI Providers',
                'status' => "Querying {$providerCount} AI provider" . ($providerCount > 1 ? 's' : '') . "...",
            ]);

            // Perform the AI search
            try {
                $searchResult = $priceService->search($query);
                
                // Stream each result individually with a small delay for visual effect
                $results = $searchResult->results;
                $total = count($results);
                
                // Send provider info
                $providersUsed = implode(', ', array_map('ucfirst', $searchResult->providersUsed));
                
                if ($total === 0) {
                    $this->sendSSE('searching', [
                        'api' => $providersUsed ?: 'AI',
                        'status' => 'No results found',
                    ]);
                } else {
                    $this->sendSSE('searching', [
                        'api' => $providersUsed ?: 'AI',
                        'status' => "Found {$total} results from {$providersUsed}, loading...",
                    ]);
                }

                foreach ($results as $index => $result) {
                    // Small delay between results for streaming effect
                    usleep(100000); // 100ms delay
                    
                    $this->sendSSE('result', [
                        'index' => $index,
                        'total' => $total,
                        'title' => $result['title'] ?? '',
                        'price' => $result['price'] ?? 0,
                        'url' => $result['url'] ?? '',
                        'image_url' => $result['image_url'] ?? null,
                        'retailer' => $result['retailer'] ?? 'Unknown',
                        'upc' => $result['upc'] ?? null,
                        'in_stock' => $result['in_stock'] ?? true,
                    ]);
                }

                // Send completion event
                $this->sendSSE('complete', [
                    'total' => $total,
                    'apis_queried' => $searchResult->providersUsed,
                    'lowest_price' => $searchResult->lowestPrice,
                    'highest_price' => $searchResult->highestPrice,
                    'is_generic' => $searchResult->isGeneric,
                    'unit_of_measure' => $searchResult->unitOfMeasure,
                ]);

            } catch (\Exception $e) {
                $this->sendSSE('error', [
                    'message' => 'AI search failed: ' . $e->getMessage(),
                ]);
                $this->sendSSE('complete', [
                    'total' => 0,
                    'apis_queried' => [],
                ]);
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Send a Server-Sent Event.
     */
    protected function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        // Flush output immediately
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
