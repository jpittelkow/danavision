<?php

namespace App\Http\Controllers;

use App\Jobs\AI\FirecrawlDiscoveryJob;
use App\Jobs\AI\ProductIdentificationJob;
use App\Models\AIJob;
use App\Models\ShoppingList;
use App\Models\SmartAddQueueItem;
use App\Services\AI\AIService;
use App\Services\AI\MultiAIService;
use App\Services\Crawler\StoreDiscoveryService;
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

        // Get pending queue items for this user
        $queueItems = SmartAddQueueItem::forUser($request->user()->id)
            ->readyForReview()
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform queue items for the frontend
        $queue = $queueItems->map(function (SmartAddQueueItem $item) {
            return [
                'id' => $item->id,
                'status' => $item->status,
                'status_label' => $item->status_label,
                'source_type' => $item->source_type,
                'source_query' => $item->source_query,
                'display_title' => $item->display_title,
                'display_image' => $item->display_image,
                'suggestions_count' => $item->suggestions_count,
                'product_data' => $item->product_data,
                'providers_used' => $item->providers_used,
                'created_at' => $item->created_at->toIso8601String(),
                'expires_at' => $item->expires_at->toIso8601String(),
            ];
        });

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'queue' => $queue,
            'queueCount' => $queue->count(),
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

        // Price search now happens via Firecrawl after item is added to a list
        // Just return the analysis without inline price results
        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'analysis' => $analysis,
            'price_results' => [],
            'search_error' => null,
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

        // Price search now happens via Firecrawl after item is added to a list
        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'analysis' => [
                'product_name' => $validated['query'],
                'brand' => null,
                'model' => null,
                'category' => $genericInfo['category'],
                'is_generic' => $genericInfo['is_generic'],
                'unit_of_measure' => $genericInfo['unit_of_measure'],
                'search_terms' => [],
                'confidence' => 100,
                'error' => null,
                'providers_used' => [],
            ],
            'price_results' => [],
            'search_error' => null,
            'search_query' => $validated['query'],
        ]);
    }

    /**
     * Identify products from image or text query.
     * Returns up to 5 product suggestions for the user to select from.
     * 
     * This is Phase 1 of the Smart Add flow - product identification.
     * Price search happens after the item is added to a list.
     * 
     * Supports two modes:
     * - async=false (default): Synchronous processing, returns results immediately
     * - async=true: Dispatches a background job, returns job ID for polling
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function identify(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'image' => ['nullable', 'string'], // Base64 encoded image (data URL)
            'query' => ['nullable', 'string', 'max:500'],
            'async' => ['nullable', 'boolean'], // Use async job processing
        ]);

        // Must have either image or query
        if (empty($validated['image']) && empty($validated['query'])) {
            return response()->json([
                'error' => 'Please provide an image or search query.',
                'results' => [],
                'providers_used' => [],
            ], 422);
        }

        $userId = $request->user()->id;
        $useAsync = $validated['async'] ?? false;

        // Async mode: dispatch job and return job ID
        if ($useAsync) {
            return $this->identifyAsync($validated, $userId);
        }

        // Sync mode: process immediately and return results
        return $this->identifySync($validated, $userId);
    }

    /**
     * Process identification asynchronously using a background job.
     */
    protected function identifyAsync(array $validated, int $userId): \Illuminate\Http\JsonResponse
    {
        // Create the AIJob record
        $aiJob = AIJob::createJob(
            userId: $userId,
            type: AIJob::TYPE_PRODUCT_IDENTIFICATION,
            inputData: [
                'image' => $validated['image'] ?? null,
                'query' => $validated['query'] ?? null,
            ]
        );

        // Dispatch the background job
        ProductIdentificationJob::dispatch($aiJob->id, $userId);

        return response()->json([
            'job_id' => $aiJob->id,
            'status' => 'pending',
            'message' => 'Product identification job started. Poll /api/ai-jobs/' . $aiJob->id . ' for status.',
        ], 202);
    }

    /**
     * Process identification synchronously (legacy behavior).
     */
    protected function identifySync(array $validated, int $userId): \Illuminate\Http\JsonResponse
    {
        try {
            $results = [];
            $providersUsed = [];
            $error = null;

            if (!empty($validated['image'])) {
                // Image-based identification
                $imageResult = $this->identifyFromImage($validated['image'], $validated['query'] ?? null, $userId);
                $results = $imageResult['results'];
                $providersUsed = $imageResult['providers_used'];
                $error = $imageResult['error'];
            } else {
                // Text-based identification
                $textResult = $this->identifyFromText($validated['query'], $userId);
                $results = $textResult['results'];
                $providersUsed = $textResult['providers_used'];
                $error = $textResult['error'];
            }

            // Limit to 5 results
            $results = array_slice($results, 0, 5);

            return response()->json([
                'results' => $results,
                'providers_used' => $providersUsed,
                'error' => $error,
            ]);

        } catch (\Exception $e) {
            \Log::error('Product identification failed', [
                'has_image' => !empty($validated['image']),
                'query' => $validated['query'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'results' => [],
                'providers_used' => [],
                'error' => 'Failed to identify product: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Identify product from an image using AI.
     */
    protected function identifyFromImage(string $imageData, ?string $context, int $userId): array
    {
        // Parse the base64 data URL
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

        $prompt = $this->buildProductIdentificationPrompt($context);

        // Try multi-AI service first for better accuracy
        $multiAI = MultiAIService::forUser($userId);
        
        if ($multiAI->isAvailable() && $multiAI->getProviderCount() > 1) {
            $result = $multiAI->analyzeImageWithAllProviders($base64, $mimeType, $prompt);
            
            if ($result['aggregated_response']) {
                $parsed = $this->parseProductSuggestions($result['aggregated_response']);
                $providersUsed = array_keys(
                    array_filter($result['individual_responses'], fn($r) => $r['error'] === null)
                );
                
                // Search for product images for each suggestion
                $parsed = $this->enrichProductSuggestionsWithImages($parsed);
                
                return [
                    'results' => $parsed,
                    'providers_used' => $providersUsed,
                    'error' => null,
                ];
            }
            
            return [
                'results' => [],
                'providers_used' => [],
                'error' => $result['error'] ?? 'All AI providers failed to respond',
            ];
        }

        // Fall back to single provider
        $aiService = AIService::forUser($userId);
        
        if (!$aiService) {
            return [
                'results' => [],
                'providers_used' => [],
                'error' => 'No AI provider configured. Please set up an AI provider in Settings.',
            ];
        }

        $response = $aiService->analyzeImage($base64, $mimeType, $prompt);
        $parsed = $this->parseProductSuggestions($response);
        $parsed = $this->enrichProductSuggestionsWithImages($parsed);
        
        return [
            'results' => $parsed,
            'providers_used' => [$aiService->getProviderType()],
            'error' => null,
        ];
    }

    /**
     * Identify product from text query using AI.
     */
    protected function identifyFromText(string $query, int $userId): array
    {
        $prompt = $this->buildTextIdentificationPrompt($query);

        // Try multi-AI service first
        $multiAI = MultiAIService::forUser($userId);
        
        if ($multiAI->isAvailable() && $multiAI->getProviderCount() > 1) {
            $result = $multiAI->processWithAllProviders($prompt);
            
            if ($result['aggregated_response']) {
                $parsed = $this->parseProductSuggestions($result['aggregated_response']);
                $providersUsed = array_keys(
                    array_filter($result['individual_responses'], fn($r) => $r['error'] === null)
                );
                
                // Search for product images for each suggestion
                $parsed = $this->enrichProductSuggestionsWithImages($parsed);
                
                return [
                    'results' => $parsed,
                    'providers_used' => $providersUsed,
                    'error' => null,
                ];
            }
            
            return [
                'results' => [],
                'providers_used' => [],
                'error' => $result['error'] ?? 'All AI providers failed to respond',
            ];
        }

        // Fall back to single provider
        $aiService = AIService::forUser($userId);
        
        if (!$aiService) {
            return [
                'results' => [],
                'providers_used' => [],
                'error' => 'No AI provider configured. Please set up an AI provider in Settings.',
            ];
        }

        $response = $aiService->complete($prompt);
        $parsed = $this->parseProductSuggestions($response);
        $parsed = $this->enrichProductSuggestionsWithImages($parsed);
        
        return [
            'results' => $parsed,
            'providers_used' => [$aiService->getProviderType()],
            'error' => null,
        ];
    }

    /**
     * Build prompt for product identification from image.
     */
    protected function buildProductIdentificationPrompt(?string $context): string
    {
        $contextPart = '';
        if ($context) {
            $contextPart = "Additional context from user: {$context}\n\n";
        }

        return <<<PROMPT
{$contextPart}Analyze this product image and identify what product it is. Return up to 5 possible product matches, ranked by confidence.

Return a JSON array with the following format:
[
    {
        "product_name": "Full product name including brand and model",
        "brand": "Brand/manufacturer name",
        "model": "Model number if identifiable",
        "category": "Product category (e.g., Electronics, Groceries, Home & Kitchen)",
        "upc": "12-digit UPC barcode if known, null otherwise",
        "image_url": "Direct URL to a product image if you know one, null otherwise",
        "is_generic": false,
        "unit_of_measure": null,
        "confidence": 95
    }
]

IMPORTANT GUIDELINES:
1. Return up to 5 possible matches, ranked by confidence (highest first)
2. Be specific - include brand and model when identifiable
3. Confidence score should be 0-100 based on how certain you are
4. For branded products, set is_generic: false
5. For generic items (produce, bulk goods, deli items), set is_generic: true and unit_of_measure to appropriate unit (lb, oz, each, dozen, gallon, etc.)
6. Generic items do NOT have UPCs - use null
7. If you recognize the product and know its UPC, include it
8. If multiple interpretations are possible, include them as separate results
9. For image_url, provide a direct link to a product image from a major retailer (Amazon, Walmart, Target, Best Buy, manufacturer site) if you know one. Use stable CDN URLs when possible. Set to null if unknown.

Examples:
- Sony WH-1000XM5 headphones → is_generic: false, upc: "027242917576"
- Organic bananas → is_generic: true, unit_of_measure: "lb", upc: null, image_url: null
- iPhone 15 Pro → is_generic: false, upc: null (varies by carrier/storage)

Only return the JSON array, no other text.
PROMPT;
    }

    /**
     * Build prompt for product identification from text query.
     */
    protected function buildTextIdentificationPrompt(string $query): string
    {
        return <<<PROMPT
Based on this search query, identify what product the user is looking for: "{$query}"

Return up to 5 possible product matches that could match this query, ranked by how likely they are what the user wants.

Return a JSON array with the following format:
[
    {
        "product_name": "Full product name including brand and model",
        "brand": "Brand/manufacturer name",
        "model": "Model number if applicable",
        "category": "Product category (e.g., Electronics, Groceries, Home & Kitchen)",
        "upc": "12-digit UPC barcode if known, null otherwise",
        "image_url": "Direct URL to a product image if you know one, null otherwise",
        "is_generic": false,
        "unit_of_measure": null,
        "confidence": 95
    }
]

IMPORTANT GUIDELINES:
1. Return up to 5 possible matches, ranked by confidence (highest first)
2. Think about what products the user might be searching for
3. Include popular/common variants if the query is general
4. Confidence score should be 0-100 based on how likely this is what the user wants
5. For branded products, set is_generic: false
6. For generic items (produce, bulk goods, meat, dairy), set is_generic: true and unit_of_measure to appropriate unit (lb, oz, each, dozen, gallon, etc.)
7. Generic items do NOT have UPCs - use null
8. For image_url, provide a direct link to a product image from a major retailer (Amazon, Walmart, Target, Best Buy, manufacturer site) if you know one. Use stable CDN URLs when possible. Set to null if unknown.

Examples:
- Query "airpods" might return: AirPods Pro 2, AirPods 3rd Gen, AirPods Max, etc.
- Query "milk" might return: various milk types with is_generic: true, image_url: null
- Query "sony headphones" might return: WH-1000XM5, WH-1000XM4, WF-1000XM5, etc.

Only return the JSON array, no other text.
PROMPT;
    }

    /**
     * Parse product suggestions from AI response.
     */
    protected function parseProductSuggestions(string $response): array
    {
        $results = [];

        // Try to extract JSON array from the response
        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                foreach ($parsed as $item) {
                    if (isset($item['product_name']) || isset($item['brand'])) {
                        $results[] = [
                            'product_name' => $item['product_name'] ?? ($item['brand'] . ' ' . ($item['model'] ?? '')),
                            'brand' => $item['brand'] ?? null,
                            'model' => $item['model'] ?? null,
                            'category' => $item['category'] ?? null,
                            'upc' => $item['upc'] ?? null,
                            'is_generic' => (bool) ($item['is_generic'] ?? false),
                            'unit_of_measure' => $item['unit_of_measure'] ?? null,
                            'confidence' => (int) ($item['confidence'] ?? 50),
                            'image_url' => $item['image_url'] ?? null, // Use AI-provided image URL
                        ];
                    }
                }
            }
        }

        // Sort by confidence descending
        usort($results, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $results;
    }

    /**
     * Enrich product suggestions with images.
     * AI now provides image URLs directly in its response.
     * This method validates and returns the results.
     */
    protected function enrichProductSuggestionsWithImages(array $results): array
    {
        // AI now provides image URLs directly in the response
        // Just validate that image URLs look reasonable
        foreach ($results as $i => $result) {
            if (!empty($result['image_url'])) {
                // Basic validation - ensure it's a valid URL
                if (!filter_var($result['image_url'], FILTER_VALIDATE_URL)) {
                    $results[$i]['image_url'] = null;
                }
            }
        }
        
        return $results;
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
            'product_url' => ['nullable', 'string', 'max:2048'], // Allow empty strings
            'product_image_url' => ['nullable', 'string', 'max:2048'], // Allow empty strings
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
            'skip_price_search' => ['nullable', 'boolean'], // Option to skip background price search
        ]);

        // Clean up empty URL strings
        if (empty($validated['product_url'])) {
            $validated['product_url'] = null;
        }
        if (empty($validated['product_image_url'])) {
            $validated['product_image_url'] = null;
        }

        $list = ShoppingList::findOrFail($validated['list_id']);
        $this->authorize('update', $list);

        // Handle uploaded image (base64)
        $uploadedImagePath = null;
        if (!empty($validated['uploaded_image'])) {
            $uploadedImagePath = $this->saveUploadedImage($validated['uploaded_image'], $request->user()->id);
        }

        $item = $list->items()->create([
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

        // Dispatch background job to search for prices using Crawl4AI
        // This runs asynchronously after the item is added
        if (!($validated['skip_price_search'] ?? false)) {
            $userId = $request->user()->id;
            $discoveryService = StoreDiscoveryService::forUser($userId);

            if ($discoveryService->isAvailable()) {
                $aiJob = AIJob::createJob(
                    userId: $userId,
                    type: AIJob::TYPE_FIRECRAWL_DISCOVERY,
                    inputData: [
                        'product_name' => $item->product_name,
                        'product_query' => $item->product_query ?? $item->product_name,
                        'upc' => $item->upc ?? null,
                        'is_generic' => $item->is_generic ?? false,
                        'unit_of_measure' => $item->unit_of_measure ?? null,
                        'shop_local' => $list->shop_local ?? false,
                        'source' => 'initial_discovery',
                    ],
                    relatedItemId: $item->id,
                    relatedListId: $list->id,
                );

                // Dispatch the discovery job after response is sent
                // This ensures the redirect happens immediately while price search runs in background
                FirecrawlDiscoveryJob::dispatch($aiJob->id, $userId)
                    ->afterResponse();
            }
            // If AI provider is not configured, item is still added but no price search runs
            // User will see a message in the UI to configure AI in Settings
        }

        // Redirect to the item page so user can see prices as they load
        return redirect()->route('items.show', $item)
            ->with('success', 'Item added to ' . $list->name . '! Searching for prices in background...');
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

    // ==========================================
    // Review Queue Methods
    // ==========================================

    /**
     * Get the user's review queue (pending product identifications).
     * Returns JSON for AJAX requests or renders the Smart Add page with queue data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|Response
     */
    public function queue(Request $request)
    {
        $queueItems = SmartAddQueueItem::forUser($request->user()->id)
            ->readyForReview()
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform queue items for the frontend
        $transformed = $queueItems->map(function (SmartAddQueueItem $item) {
            return [
                'id' => $item->id,
                'status' => $item->status,
                'status_label' => $item->status_label,
                'source_type' => $item->source_type,
                'source_query' => $item->source_query,
                'display_title' => $item->display_title,
                'display_image' => $item->display_image,
                'suggestions_count' => $item->suggestions_count,
                'product_data' => $item->product_data,
                'providers_used' => $item->providers_used,
                'created_at' => $item->created_at->toIso8601String(),
                'expires_at' => $item->expires_at->toIso8601String(),
            ];
        });

        // If AJAX request, return JSON
        if ($request->wantsJson()) {
            return response()->json([
                'queue' => $transformed,
                'count' => $transformed->count(),
            ]);
        }

        // Otherwise render the Smart Add page with queue data
        $lists = $request->user()
            ->shoppingLists()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('SmartAdd', [
            'lists' => $lists,
            'queue' => $transformed,
            'queueCount' => $transformed->count(),
        ]);
    }

    /**
     * Dismiss a queue item (mark as dismissed).
     *
     * @param Request $request
     * @param SmartAddQueueItem $queueItem
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function dismissQueueItem(Request $request, SmartAddQueueItem $queueItem)
    {
        // Ensure the queue item belongs to the user
        if ($queueItem->user_id !== $request->user()->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403);
        }

        $queueItem->markAsDismissed();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Item dismissed');
    }

    /**
     * Add a queue item to a shopping list.
     *
     * @param Request $request
     * @param SmartAddQueueItem $queueItem
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function addQueueItemToList(Request $request, SmartAddQueueItem $queueItem)
    {
        // Ensure the queue item belongs to the user
        if ($queueItem->user_id !== $request->user()->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403);
        }

        $validated = $request->validate([
            'list_id' => ['required', 'exists:shopping_lists,id'],
            'selected_index' => ['required', 'integer', 'min:0', 'max:4'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);

        $list = ShoppingList::findOrFail($validated['list_id']);
        $this->authorize('update', $list);

        // Get the selected product from the queue item
        $productData = $queueItem->product_data;
        $selectedIndex = $validated['selected_index'];
        
        if (!isset($productData[$selectedIndex])) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Invalid product selection'], 422);
            }
            return back()->with('error', 'Invalid product selection');
        }

        $product = $productData[$selectedIndex];

        // Create the list item
        $item = $list->items()->create([
            'added_by_user_id' => $request->user()->id,
            'product_name' => $validated['product_name'] ?? $product['product_name'],
            'product_query' => $product['product_name'],
            'product_image_url' => $product['image_url'] ?? null,
            'uploaded_image_path' => $queueItem->source_image_path,
            'upc' => $product['upc'] ?? null,
            'target_price' => $validated['target_price'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'is_generic' => $product['is_generic'] ?? false,
            'unit_of_measure' => $product['unit_of_measure'] ?? null,
        ]);

        // Mark the queue item as added
        $queueItem->markAsAdded($item->id, $selectedIndex);

        // Dispatch background job to search for prices using Crawl4AI
        $userId = $request->user()->id;
        $discoveryService = StoreDiscoveryService::forUser($userId);

        if ($discoveryService->isAvailable()) {
            $aiJob = AIJob::createJob(
                userId: $userId,
                type: AIJob::TYPE_FIRECRAWL_DISCOVERY,
                inputData: [
                    'product_name' => $item->product_name,
                    'product_query' => $item->product_query ?? $item->product_name,
                    'upc' => $item->upc ?? null,
                    'is_generic' => $item->is_generic ?? false,
                    'unit_of_measure' => $item->unit_of_measure ?? null,
                    'shop_local' => $list->shop_local ?? false,
                    'source' => 'queue_add',
                ],
                relatedItemId: $item->id,
                relatedListId: $list->id,
            );

            FirecrawlDiscoveryJob::dispatch($aiJob->id, $userId)
                ->afterResponse();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'item_id' => $item->id,
                'message' => 'Item added to ' . $list->name,
            ]);
        }

        // Redirect to the item page
        return redirect()->route('items.show', $item)
            ->with('success', 'Item added to ' . $list->name . '! Price search running in background.');
    }

}
