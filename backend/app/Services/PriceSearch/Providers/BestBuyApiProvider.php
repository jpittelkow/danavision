<?php

namespace App\Services\PriceSearch\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BestBuyApiProvider implements PriceProviderInterface
{
    private const PRODUCTS_URL = 'https://api.bestbuy.com/v1/products';

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function search(string $query, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $limit = min($options['limit'] ?? 10, 100);

        // Best Buy uses a keyword search syntax with separate filter segments
        $searchQuery = '(search=' . urlencode($query) . ')';

        // Filter by local store availability when a store ID is provided
        $storeId = $options['bestbuy_store_id'] ?? null;
        if ($storeId) {
            $searchQuery .= '&stores(storeId=' . $storeId . ')';
        }

        try {
            $response = Http::timeout(15)
                ->get(self::PRODUCTS_URL . $searchQuery, [
                    'apiKey' => $this->apiKey,
                    'format' => 'json',
                    'pageSize' => $limit,
                    'show' => 'sku,name,salePrice,regularPrice,url,image,upc,inStoreAvailability,onlineAvailability,shortDescription,manufacturer,modelNumber',
                    'sort' => 'bestSellingRank.asc',
                ]);

            if (!$response->successful()) {
                Log::warning('BestBuyApiProvider: Product search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $data = $response->json();

            return $this->formatResults($data['products'] ?? []);
        } catch (\Exception $e) {
            Log::error('BestBuyApiProvider: Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getName(): string
    {
        return 'bestbuy';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search for Best Buy store locations near coordinates.
     *
     * @return array Array of store locations
     */
    public function searchStores(float $lat, float $lng, int $radiusMiles = 25): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $areaQuery = '(area(' . $lat . ',' . $lng . ',' . $radiusMiles . '))';

        try {
            $response = Http::timeout(15)
                ->get('https://api.bestbuy.com/v1/stores' . $areaQuery, [
                    'apiKey' => $this->apiKey,
                    'format' => 'json',
                    'pageSize' => 10,
                    'show' => 'storeId,storeType,name,address,city,region,postalCode,lat,lng,phone,hours',
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            return array_map(fn (array $store) => [
                'store_id' => $store['storeId'] ?? null,
                'name' => $store['name'] ?? 'Best Buy',
                'address' => $this->formatAddress($store),
                'latitude' => $store['lat'] ?? null,
                'longitude' => $store['lng'] ?? null,
                'phone' => $store['phone'] ?? null,
            ], $data['stores'] ?? []);
        } catch (\Exception $e) {
            Log::error('BestBuyApiProvider: Store search error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Format Best Buy API product results into standard provider format.
     */
    private function formatResults(array $products): array
    {
        $results = [];

        foreach ($products as $product) {
            $price = $this->extractPrice($product);

            $results[] = [
                'product_name' => $product['name'] ?? '',
                'price' => $price,
                'retailer' => 'Best Buy',
                'url' => $product['url'] ?? '',
                'in_stock' => $this->checkInStock($product),
                'image_url' => $product['image'] ?? '',
                'upc' => $product['upc'] ?? null,
                'package_size' => null,
                'provider' => 'bestbuy',
            ];
        }

        return $results;
    }

    /**
     * Extract the best price from Best Buy product data.
     */
    private function extractPrice(array $product): ?float
    {
        $salePrice = $product['salePrice'] ?? null;
        $regularPrice = $product['regularPrice'] ?? null;

        // Prefer sale price if available
        if ($salePrice !== null) {
            return (float) $salePrice;
        }

        if ($regularPrice !== null) {
            return (float) $regularPrice;
        }

        return null;
    }

    /**
     * Check if product is in stock (online or in-store).
     */
    private function checkInStock(array $product): bool
    {
        return ($product['onlineAvailability'] ?? false) || ($product['inStoreAvailability'] ?? false);
    }

    /**
     * Format a Best Buy store address into a string.
     */
    private function formatAddress(array $store): string
    {
        $parts = array_filter([
            $store['address'] ?? null,
            $store['city'] ?? null,
            $store['region'] ?? null,
            $store['postalCode'] ?? null,
        ]);

        return implode(', ', $parts);
    }
}
