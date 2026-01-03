<?php

namespace App\Services\PriceApi\Providers;

use App\Services\PriceApi\PriceProviderInterface;
use Illuminate\Support\Facades\Http;

class RainforestProvider implements PriceProviderInterface
{
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Search Amazon for products.
     */
    public function search(string $query, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $response = Http::get('https://api.rainforestapi.com/request', [
            'api_key' => $this->apiKey,
            'type' => 'search',
            'amazon_domain' => $options['domain'] ?? 'amazon.com',
            'search_term' => $query,
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $results = [];

        foreach ($data['search_results'] ?? [] as $item) {
            $price = $item['price']['value'] ?? $item['prices'][0]['value'] ?? null;
            
            if ($price === null) {
                continue;
            }

            $results[] = [
                'retailer' => 'Amazon',
                'retailer_logo' => 'https://www.amazon.com/favicon.ico',
                'price' => (float) $price,
                'currency' => $item['price']['currency'] ?? 'USD',
                'url' => $item['link'] ?? '',
                'in_stock' => ($item['availability']['raw'] ?? '') !== 'Out of Stock',
                'shipping' => $item['delivery']['tagline'] ?? null,
                'condition' => 'new',
                'title' => $item['title'] ?? '',
                'image_url' => $item['image'] ?? null,
                'rating' => $item['rating'] ?? null,
                'reviews_count' => $item['ratings_total'] ?? null,
                'asin' => $item['asin'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Check if provider is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Test connection to Rainforest API.
     */
    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::get('https://api.rainforestapi.com/request', [
            'api_key' => $this->apiKey,
            'type' => 'account',
        ]);

        return $response->successful();
    }
}
