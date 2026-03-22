<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\SearchHistory;
use App\Services\PriceSearch\PriceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PriceSearchService $priceSearchService
    ) {}

    /**
     * Text-based product search.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $results = $this->priceSearchService->searchByQuery(
            $validated['query'],
            $user,
            $validated['shop_local'] ?? false
        );

        // Log search history
        SearchHistory::create([
            'user_id' => $user->id,
            'query' => $validated['query'],
            'query_type' => 'text',
            'results_count' => count($results),
        ]);

        return response()->json(['data' => $results]);
    }

    /**
     * Image-based product search.
     */
    public function imageSearch(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
            'shop_local' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $file = $request->file('image');
        $path = $file->store('search-images', 'local');
        $fullPath = storage_path('app/' . $path);

        $results = $this->priceSearchService->searchByImage(
            $fullPath,
            $user,
            $request->boolean('shop_local'),
        );

        // Log search history
        SearchHistory::create([
            'user_id' => $user->id,
            'query' => 'image:' . $file->getClientOriginalName(),
            'query_type' => 'image',
            'results_count' => count($results),
            'image_path' => $path,
        ]);

        return response()->json(['data' => $results]);
    }

    /**
     * Get search history for the current user.
     */
    public function history(Request $request): JsonResponse
    {
        $history = SearchHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'query', 'query_type', 'results_count', 'created_at']);

        return response()->json(['data' => $history]);
    }
}
