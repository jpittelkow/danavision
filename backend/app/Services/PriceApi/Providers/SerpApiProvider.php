<?php

namespace App\Services\PriceApi\Providers;

use App\Services\PriceApi\PriceProviderInterface;
use Illuminate\Support\Facades\Http;

class SerpApiProvider implements PriceProviderInterface
{
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Search Google Shopping for products.
     */
    public function search(string $query, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $response = Http::get('https://serpapi.com/search', [
            'engine' => 'google_shopping',
            'q' => $query,
            'api_key' => $this->apiKey,
            'hl' => $options['language'] ?? 'en',
            'gl' => $options['country'] ?? 'us',
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $results = [];

        foreach ($data['shopping_results'] ?? [] as $item) {
            $results[] = [
                'retailer' => $item['source'] ?? 'Unknown',
                'retailer_logo' => $item['thumbnail'] ?? null,
                'price' => $this->parsePrice($item['extracted_price'] ?? $item['price'] ?? '0'),
                'currency' => 'USD',
                'url' => $item['link'] ?? '',
                'in_stock' => true, // SerpAPI doesn't always provide stock info
                'shipping' => $item['delivery'] ?? null,
                'condition' => $this->parseCondition($item['second_hand_condition'] ?? null),
                'title' => $item['title'] ?? '',
                'image_url' => $item['thumbnail'] ?? null,
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
     * Test connection to SerpAPI.
     */
    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::get('https://serpapi.com/account', [
            'api_key' => $this->apiKey,
        ]);

        return $response->successful();
    }

    /**
     * Parse price from various formats.
     */
    protected function parsePrice(mixed $price): float
    {
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
     * Parse condition.
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
}
