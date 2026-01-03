<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\AI\AIService;
use App\Services\PriceApi\PriceApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{

    /**
     * Show the search page.
     */
    public function index(Request $request): Response
    {
        $recentSearches = $request->user()
            ->searchHistory()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Search', [
            'recent_searches' => $recentSearches,
        ]);
    }

    /**
     * Perform a text search.
     */
    public function search(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $query = $validated['query'];

        // Save search history
        SearchHistory::create([
            'user_id' => $request->user()->id,
            'query' => $query,
            'search_type' => 'text',
        ]);

        // Get price results - use user-specific service
        $priceService = PriceApiService::forUser($request->user()->id);
        $searchResult = $priceService->search($query);

        $recentSearches = $request->user()
            ->searchHistory()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Search', [
            'recent_searches' => $recentSearches,
            'query' => $query,
            'results' => $searchResult->results,
            'search_error' => $searchResult->error,
        ]);
    }

    /**
     * Perform an image search.
     */
    public function imageSearch(Request $request): Response
    {
        $validated = $request->validate([
            'image' => ['required', 'string'], // Base64 encoded image (data URL)
            'description' => ['nullable', 'string', 'max:500'], // Optional description to help identify
        ]);

        // Get user's AI service
        $aiService = AIService::forUser($request->user()->id);
        
        $analysis = [
            'product_name' => null,
            'brand' => null,
            'error' => null,
        ];
        
        $results = [];
        $searchError = null;

        if (!$aiService) {
            $analysis['error'] = 'No AI provider configured. Please set up an AI provider in Settings.';
        } else {
            try {
                // Parse the base64 data URL
                $imageData = $validated['image'];
                $mimeType = 'image/jpeg';
                $base64 = $imageData;

                // Extract mime type and base64 from data URL if present
                if (str_starts_with($imageData, 'data:')) {
                    $parts = explode(',', $imageData, 2);
                    if (count($parts) === 2) {
                        preg_match('/data:(.*?);base64/', $parts[0], $matches);
                        $mimeType = $matches[1] ?? 'image/jpeg';
                        $base64 = $parts[1];
                    }
                }

                // Build the prompt
                $prompt = 'Identify this product. ';
                if (!empty($validated['description'])) {
                    $prompt .= "Context: {$validated['description']}. ";
                }
                $prompt .= 'Return a JSON object with the following fields: product_name (specific make/model if identifiable), brand (if identifiable), category (general category like "electronics", "appliance", etc.), confidence (0-1 how confident you are). Only return the JSON, no other text.';

                // Analyze the image
                $response = $aiService->analyzeImage($base64, $mimeType, $prompt);

                // Parse the JSON response
                $jsonMatch = [];
                if (preg_match('/\{[\s\S]*\}/', $response, $jsonMatch)) {
                    $parsed = json_decode($jsonMatch[0], true);
                    if ($parsed) {
                        $analysis['product_name'] = $parsed['product_name'] ?? null;
                        $analysis['brand'] = $parsed['brand'] ?? null;
                        $analysis['category'] = $parsed['category'] ?? null;
                        $analysis['confidence'] = $parsed['confidence'] ?? null;
                    }
                }

                // If no product name found, set error
                if (empty($analysis['product_name'])) {
                    $analysis['error'] = 'Could not identify the product. Please try a clearer image or add more description.';
                }
            } catch (\Exception $e) {
                $analysis['error'] = 'AI analysis failed: ' . $e->getMessage();
            }
        }

        // Search for identified product if we have a name
        if (!empty($analysis['product_name'])) {
            $priceService = PriceApiService::forUser($request->user()->id);
            $searchResult = $priceService->search($analysis['product_name']);
            $results = $searchResult->results;
            $searchError = $searchResult->error;

            // Save search history
            SearchHistory::create([
                'user_id' => $request->user()->id,
                'query' => $analysis['product_name'],
                'search_type' => 'image',
            ]);
        }

        $recentSearches = $request->user()
            ->searchHistory()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Search', [
            'recent_searches' => $recentSearches,
            'query' => $analysis['product_name'] ?? '',
            'results' => $results,
            'image_analysis' => $analysis,
            'search_error' => $searchError ?? $analysis['error'],
        ]);
    }
}
