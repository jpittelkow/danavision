<?php

namespace App\Services\PriceSearch\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KrogerApiProvider implements PriceProviderInterface
{
    private const TOKEN_URL = 'https://api.kroger.com/v1/connect/oauth2/token';

    private const PRODUCTS_URL = 'https://api.kroger.com/v1/products';

    private const LOCATIONS_URL = 'https://api.kroger.com/v1/locations';

    private const TOKEN_CACHE_KEY = 'kroger_api_token';

    public function __construct(
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
    ) {}

    public function search(string $query, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            Log::warning('KrogerApiProvider: no access token available, skipping search', [
                'query' => $query,
            ]);

            return [];
        }

        $locationId = $options['kroger_location_id'] ?? null;
        $chainName = $options['kroger_chain_name'] ?? null;
        $limit = min($options['limit'] ?? 10, 50);

        $params = [
            'filter.term' => $query,
            'filter.limit' => $limit,
        ];

        if ($locationId) {
            $params['filter.locationId'] = $locationId;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get(self::PRODUCTS_URL, $params);

            if (!$response->successful()) {
                Log::warning('KrogerApiProvider: Product search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $data = $response->json();
            $results = $this->formatResults($data['data'] ?? [], $locationId, $chainName);

            Log::info('KrogerApiProvider: search complete', [
                'query' => $query,
                'results' => count($results),
                'location_id' => $locationId,
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('KrogerApiProvider: Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getName(): string
    {
        return 'kroger';
    }

    public function isAvailable(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Search for Kroger locations near a given lat/lng.
     *
     * @return array Array of locations with id, name, address, etc.
     */
    public function searchLocations(float $lat, float $lng, int $radiusMiles = 25): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get(self::LOCATIONS_URL, [
                    'filter.lat.near' => $lat,
                    'filter.lon.near' => $lng,
                    'filter.radiusInMiles' => $radiusMiles,
                    'filter.limit' => 10,
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            return array_map(fn (array $loc) => [
                'location_id' => $loc['locationId'] ?? null,
                'name' => $loc['name'] ?? '',
                'chain' => $loc['chain'] ?? 'Kroger',
                'address' => $this->formatAddress($loc['address'] ?? []),
                'latitude' => $loc['geolocation']['latitude'] ?? null,
                'longitude' => $loc['geolocation']['longitude'] ?? null,
                'phone' => $loc['phone'] ?? null,
            ], $data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('KrogerApiProvider: Location search error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get OAuth2 access token using client credentials flow.
     */
    private function getAccessToken(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(10)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'product.compact',
                ]);

            if (!$response->successful()) {
                Log::error('KrogerApiProvider: Token request failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if ($token !== null) {
                Cache::put(self::TOKEN_CACHE_KEY, $token, 1700);
            }

            return $token;
        } catch (\Exception $e) {
            Log::error('KrogerApiProvider: Token error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Format Kroger API product results into standard provider format.
     */
    private function formatResults(array $products, ?string $locationId, ?string $chainName = null): array
    {
        $results = [];

        foreach ($products as $product) {
            $price = $this->extractPrice($product, $locationId);
            $size = $product['items'][0]['size'] ?? null;

            $results[] = [
                'product_name' => $product['description'] ?? '',
                'price' => $price,
                'retailer' => $chainName ?? 'Kroger',
                'url' => "https://www.kroger.com/p/{$product['productId']}",
                'in_stock' => $this->checkInStock($product, $locationId),
                'image_url' => $this->extractImage($product),
                'upc' => $product['upc'] ?? null,
                'package_size' => $size,
                'provider' => 'kroger',
            ];
        }

        return $results;
    }

    /**
     * Extract the best price from Kroger product data.
     */
    private function extractPrice(array $product, ?string $locationId): ?float
    {
        // If we have location-specific pricing, use it
        $items = $product['items'] ?? [];
        if (empty($items)) {
            return null;
        }

        $item = $items[0];
        $price = $item['price']['regular'] ?? null;
        $promoPrice = $item['price']['promo'] ?? null;

        // Use promo price if available and lower
        if ($promoPrice !== null && ($price === null || $promoPrice < $price)) {
            return (float) $promoPrice;
        }

        return $price !== null ? (float) $price : null;
    }

    /**
     * Check if product is in stock at the given location.
     */
    private function checkInStock(array $product, ?string $locationId): bool
    {
        $items = $product['items'] ?? [];
        if (empty($items)) {
            return false;
        }

        // Kroger uses fulfillment data when location is specified
        $fulfillment = $items[0]['fulfillment'] ?? [];
        if (!empty($fulfillment)) {
            return ($fulfillment['inStore'] ?? false) || ($fulfillment['curbside'] ?? false) || ($fulfillment['delivery'] ?? false);
        }

        return true;
    }

    /**
     * Extract the best product image URL.
     */
    private function extractImage(array $product): string
    {
        $images = $product['images'] ?? [];
        foreach ($images as $imageGroup) {
            if (($imageGroup['perspective'] ?? '') === 'front') {
                $sizes = $imageGroup['sizes'] ?? [];
                foreach ($sizes as $size) {
                    if (($size['size'] ?? '') === 'medium' || ($size['size'] ?? '') === 'large') {
                        return $size['url'] ?? '';
                    }
                }
                // Fall back to first available size
                return $sizes[0]['url'] ?? '';
            }
        }

        // Fall back to any image
        return $images[0]['sizes'][0]['url'] ?? '';
    }

    /**
     * Format a Kroger address object into a string.
     */
    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['addressLine1'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zipCode'] ?? null,
        ]);

        return implode(', ', $parts);
    }
}
