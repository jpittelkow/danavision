<?php

namespace App\Services\PriceSearch\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalmartApiProvider implements PriceProviderInterface
{
    private const SEARCH_URL = 'https://developer.api.walmart.com/api-proxy/service/affil/product/v2/search';

    private const LOOKUP_URL = 'https://developer.api.walmart.com/api-proxy/service/affil/product/v2/items';

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * Search Walmart products.
     *
     * Note: The Walmart Affiliate API provides national pricing only.
     * Location-specific pricing is not available through this API.
     * The shop_local/location options are intentionally ignored.
     */
    public function search(string $query, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $limit = min($options['limit'] ?? 10, 25);

        $params = [
            'query' => $query,
            'numItems' => $limit,
            'format' => 'json',
        ];

        if (!empty($options['sort'])) {
            $params['sort'] = $options['sort'];
        }

        if (!empty($options['category_id'])) {
            $params['categoryId'] = $options['category_id'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'WM_SEC.ACCESS_TOKEN' => $this->getAccessToken(),
                    'WM_CONSUMER.ID' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get(self::SEARCH_URL, $params);

            if (!$response->successful()) {
                Log::warning('WalmartApiProvider: Product search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $data = $response->json();

            return $this->formatResults($data['items'] ?? []);
        } catch (\Exception $e) {
            Log::error('WalmartApiProvider: Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getName(): string
    {
        return 'walmart';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get access token for Walmart Affiliate API.
     *
     * The Affiliate API uses the API key directly as authentication.
     */
    private function getAccessToken(): string
    {
        return $this->apiKey ?? '';
    }

    /**
     * Format Walmart API product results into standard provider format.
     */
    private function formatResults(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $price = $this->extractPrice($item);

            $results[] = [
                'product_name' => $item['name'] ?? '',
                'price' => $price,
                'retailer' => 'Walmart',
                'url' => $item['productUrl'] ?? $item['addToCartUrl'] ?? '',
                'in_stock' => $this->checkInStock($item),
                'image_url' => $item['largeImage'] ?? $item['mediumImage'] ?? $item['thumbnailImage'] ?? '',
                'upc' => $item['upc'] ?? null,
                'package_size' => $item['size'] ?? null,
                'provider' => 'walmart',
            ];
        }

        return $results;
    }

    /**
     * Extract the best price from Walmart product data.
     */
    private function extractPrice(array $item): ?float
    {
        // Prefer sale price over MSRP
        $salePrice = $item['salePrice'] ?? null;
        $msrp = $item['msrp'] ?? null;

        if ($salePrice !== null) {
            return (float) $salePrice;
        }

        if ($msrp !== null) {
            return (float) $msrp;
        }

        return null;
    }

    /**
     * Check if product is available for purchase.
     */
    private function checkInStock(array $item): bool
    {
        $stock = $item['stock'] ?? null;

        if ($stock === 'Available') {
            return true;
        }

        if ($stock === 'Not available') {
            return false;
        }

        // If availableOnline is set, use that
        if (isset($item['availableOnline'])) {
            return (bool) $item['availableOnline'];
        }

        // Default to true if we have a price
        return isset($item['salePrice']) || isset($item['msrp']);
    }
}
