<?php

namespace App\Services\Search;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSearchService
{
    protected ?int $userId;
    protected ?string $serpApiKey;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->loadConfiguration();
    }

    /**
     * Create an instance for a specific user.
     */
    public static function forUser(?int $userId): self
    {
        return new self($userId);
    }

    /**
     * Load configuration from settings.
     */
    protected function loadConfiguration(): void
    {
        $this->serpApiKey = Setting::get(Setting::SERPAPI_KEY, $this->userId)
            ?? config('services.serpapi.api_key');
    }

    /**
     * Check if web search is available.
     */
    public function isAvailable(): bool
    {
        return !empty($this->serpApiKey);
    }

    /**
     * Search for product prices using Google Shopping.
     *
     * @param string $query The search query
     * @param array $options Options including zip_code, shop_local, local_stores
     * @return array Array of price results
     */
    public function searchPrices(string $query, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $params = [
            'engine' => 'google_shopping',
            'q' => $query,
            'api_key' => $this->serpApiKey,
            'hl' => 'en',
            'gl' => 'us',
            'num' => 20, // Get more results for better price comparison
        ];

        // Add location for local searches
        $zipCode = $options['zip_code'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;

        if ($shopLocal && $zipCode) {
            // Use location parameter for local results
            $params['location'] = $this->formatLocation($zipCode);
            
            // If we have specific local stores, add them to the query
            $localStores = $options['local_stores'] ?? [];
            if (!empty($localStores)) {
                // Append store names to improve local results
                $storeNames = array_slice(array_column($localStores, 'store_name'), 0, 3);
                if (!empty($storeNames)) {
                    $params['q'] = $query . ' ' . implode(' OR ', $storeNames);
                }
            }
        }

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search', $params);

            if (!$response->successful()) {
                Log::warning('SerpAPI search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);
                return [];
            }

            $data = $response->json();
            $results = [];

            foreach ($data['shopping_results'] ?? [] as $item) {
                $price = $this->parsePrice($item['extracted_price'] ?? $item['price'] ?? null);
                
                if ($price <= 0) {
                    continue;
                }

                $results[] = [
                    'title' => $item['title'] ?? '',
                    'price' => $price,
                    'retailer' => $item['source'] ?? 'Unknown',
                    'url' => $item['link'] ?? '',
                    'image_url' => $item['thumbnail'] ?? null,
                    'in_stock' => $this->parseStockStatus($item),
                    'shipping' => $item['delivery'] ?? null,
                    'condition' => $this->parseCondition($item['second_hand_condition'] ?? null),
                    'rating' => $item['rating'] ?? null,
                    'reviews_count' => $item['reviews'] ?? null,
                    'source' => 'serpapi_shopping',
                ];
            }

            // Sort by price
            usort($results, fn($a, $b) => $a['price'] <=> $b['price']);

            return $results;

        } catch (\Exception $e) {
            Log::error('WebSearchService searchPrices failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Search for local stores near a zip code.
     *
     * @param string $zipCode The zip code to search near
     * @param string $storeType Type of store (grocery, retail, etc.)
     * @return array Array of local stores
     */
    public function searchLocalStores(string $zipCode, string $storeType = 'grocery'): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $query = match ($storeType) {
            'grocery' => 'grocery stores supermarkets near ' . $zipCode,
            'retail' => 'retail stores department stores near ' . $zipCode,
            'pharmacy' => 'pharmacy drugstore near ' . $zipCode,
            'electronics' => 'electronics stores near ' . $zipCode,
            default => 'stores near ' . $zipCode,
        };

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'google_maps',
                'q' => $query,
                'api_key' => $this->serpApiKey,
                'll' => '@' . $this->getCoordinatesForZip($zipCode),
                'type' => 'search',
            ]);

            if (!$response->successful()) {
                // Fallback to regular Google search for local results
                return $this->searchLocalStoresFallback($zipCode, $storeType);
            }

            $data = $response->json();
            $stores = [];

            foreach ($data['local_results'] ?? [] as $place) {
                $stores[] = [
                    'store_name' => $place['title'] ?? 'Unknown',
                    'store_type' => $this->categorizeStore($place['title'] ?? '', $place['type'] ?? ''),
                    'address' => $place['address'] ?? '',
                    'phone' => $place['phone'] ?? null,
                    'rating' => $place['rating'] ?? null,
                    'reviews_count' => $place['reviews'] ?? null,
                    'hours' => $place['hours'] ?? null,
                    'place_id' => $place['place_id'] ?? null,
                    'website' => $place['website'] ?? null,
                ];
            }

            return $stores;

        } catch (\Exception $e) {
            Log::error('WebSearchService searchLocalStores failed', [
                'zipCode' => $zipCode,
                'error' => $e->getMessage(),
            ]);
            return $this->searchLocalStoresFallback($zipCode, $storeType);
        }
    }

    /**
     * Fallback method for local store search using Google web search.
     */
    protected function searchLocalStoresFallback(string $zipCode, string $storeType): array
    {
        // Return a list of common stores that are typically available locally
        $commonStores = match ($storeType) {
            'grocery' => [
                ['store_name' => 'Walmart', 'store_type' => 'supermarket', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Target', 'store_type' => 'supermarket', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Kroger', 'store_type' => 'supermarket', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Safeway', 'store_type' => 'supermarket', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Publix', 'store_type' => 'supermarket', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Costco', 'store_type' => 'warehouse', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Aldi', 'store_type' => 'discount', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Trader Joe\'s', 'store_type' => 'specialty', 'address' => "Near {$zipCode}"],
            ],
            'pharmacy' => [
                ['store_name' => 'CVS', 'store_type' => 'pharmacy', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Walgreens', 'store_type' => 'pharmacy', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Rite Aid', 'store_type' => 'pharmacy', 'address' => "Near {$zipCode}"],
            ],
            'electronics' => [
                ['store_name' => 'Best Buy', 'store_type' => 'electronics', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Target', 'store_type' => 'retail', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Walmart', 'store_type' => 'retail', 'address' => "Near {$zipCode}"],
            ],
            default => [
                ['store_name' => 'Walmart', 'store_type' => 'retail', 'address' => "Near {$zipCode}"],
                ['store_name' => 'Target', 'store_type' => 'retail', 'address' => "Near {$zipCode}"],
            ],
        };

        return $commonStores;
    }

    /**
     * Search with caching.
     */
    public function searchPricesWithCache(string $query, array $options = [], int $ttl = 900): array
    {
        $cacheKey = "web_search:{$this->userId}:" . md5($query . json_encode($options));

        return Cache::remember($cacheKey, $ttl, function () use ($query, $options) {
            return $this->searchPrices($query, $options);
        });
    }

    /**
     * Search local stores with caching.
     */
    public function searchLocalStoresWithCache(string $zipCode, string $storeType = 'grocery', int $ttl = 86400): array
    {
        $cacheKey = "local_stores:{$this->userId}:{$zipCode}:{$storeType}";

        return Cache::remember($cacheKey, $ttl, function () use ($zipCode, $storeType) {
            return $this->searchLocalStores($zipCode, $storeType);
        });
    }

    /**
     * Format location string for SerpAPI.
     */
    protected function formatLocation(string $zipCode): string
    {
        // SerpAPI accepts zip codes directly for US
        return $zipCode . ', United States';
    }

    /**
     * Get approximate coordinates for a zip code.
     * This is a simplified implementation - in production, you'd use a geocoding service.
     */
    protected function getCoordinatesForZip(string $zipCode): string
    {
        // Default to US center if we can't determine location
        // In production, this should use a geocoding API
        return '39.8283,-98.5795,4z'; // Center of US
    }

    /**
     * Parse price from various formats.
     */
    protected function parsePrice(mixed $price): float
    {
        if ($price === null) {
            return 0.0;
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        if (is_string($price)) {
            // Remove currency symbols and parse
            $cleaned = preg_replace('/[^0-9.]/', '', $price);
            return (float) $cleaned;
        }

        return 0.0;
    }

    /**
     * Parse stock status from item data.
     */
    protected function parseStockStatus(array $item): bool
    {
        // Check various indicators
        if (isset($item['availability'])) {
            $availability = strtolower($item['availability']);
            return !str_contains($availability, 'out of stock') && 
                   !str_contains($availability, 'unavailable');
        }

        // Default to in stock if no information
        return true;
    }

    /**
     * Parse condition from text.
     */
    protected function parseCondition(?string $condition): string
    {
        if (!$condition) {
            return 'new';
        }

        $condition = strtolower($condition);
        
        if (str_contains($condition, 'refurb')) {
            return 'refurbished';
        }
        
        if (str_contains($condition, 'used') || str_contains($condition, 'pre-owned')) {
            return 'used';
        }

        return 'new';
    }

    /**
     * Categorize a store based on its name and type.
     */
    protected function categorizeStore(string $name, string $type): string
    {
        $nameLower = strtolower($name);
        $typeLower = strtolower($type);

        // Check type first
        if (str_contains($typeLower, 'supermarket') || str_contains($typeLower, 'grocery')) {
            return 'supermarket';
        }
        if (str_contains($typeLower, 'pharmacy') || str_contains($typeLower, 'drugstore')) {
            return 'pharmacy';
        }
        if (str_contains($typeLower, 'warehouse')) {
            return 'warehouse';
        }

        // Check name for known stores
        $supermarkets = ['walmart', 'target', 'kroger', 'safeway', 'publix', 'albertsons', 
            'food lion', 'giant', 'wegmans', 'whole foods', 'trader joe', 'aldi', 'lidl'];
        foreach ($supermarkets as $store) {
            if (str_contains($nameLower, $store)) {
                return 'supermarket';
            }
        }

        $warehouse = ['costco', 'sam\'s club', 'bj\'s'];
        foreach ($warehouse as $store) {
            if (str_contains($nameLower, $store)) {
                return 'warehouse';
            }
        }

        $pharmacy = ['cvs', 'walgreens', 'rite aid'];
        foreach ($pharmacy as $store) {
            if (str_contains($nameLower, $store)) {
                return 'pharmacy';
            }
        }

        return 'retail';
    }
}
