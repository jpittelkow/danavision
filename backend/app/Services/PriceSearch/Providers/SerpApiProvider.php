<?php

namespace App\Services\PriceSearch\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://serpapi.com/search';

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * Search Google Shopping via SERP API.
     *
     * @return array Array of structured product results
     */
    public function search(string $query, array $options = []): array
    {
        if (!$this->isAvailable()) {
            Log::warning('SerpApiProvider: API key not configured');
            return [];
        }

        try {
            $params = [
                'engine' => 'google_shopping',
                'q' => $query,
                'api_key' => $this->apiKey,
                'num' => $options['limit'] ?? 20,
            ];

            if (!empty($options['location'])) {
                $params['location'] = $options['location'];
            }

            if (!empty($options['gl'])) {
                $params['gl'] = $options['gl'];
            }

            if (!empty($options['hl'])) {
                $params['hl'] = $options['hl'];
            }

            $response = Http::timeout(30)->get(self::BASE_URL, $params);

            // SerpAPI returns 400 for unrecognized location strings (e.g. raw street addresses).
            // Retry without location to still return results.
            if ($response->status() === 400 && isset($params['location'])) {
                Log::warning('SerpApiProvider: retrying without location (400 from SerpAPI)', [
                    'location' => $params['location'],
                    'query' => $query,
                ]);

                unset($params['location']);
                $response = Http::timeout(30)->get(self::BASE_URL, $params);
            }

            if (!$response->successful()) {
                Log::error('SerpApiProvider: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'location' => $params['location'] ?? null,
                    'query' => $query,
                ]);
                return [];
            }

            $data = $response->json();

            return $this->formatResults($data);
        } catch (\Exception $e) {
            Log::error('SerpApiProvider: search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getName(): string
    {
        return 'serpapi';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Format raw SERP API shopping results into structured array.
     */
    private function formatResults(array $data): array
    {
        $results = [];
        $shoppingResults = $data['shopping_results'] ?? [];

        foreach ($shoppingResults as $item) {
            $results[] = [
                'product_name' => $item['title'] ?? '',
                'price' => $this->extractPrice($item),
                'retailer' => $item['source'] ?? $item['merchant'] ?? '',
                'url' => $item['link'] ?? $item['product_link'] ?? '',
                'in_stock' => !isset($item['out_of_stock']) || !$item['out_of_stock'],
                'image_url' => $item['thumbnail'] ?? '',
                'rating' => $item['rating'] ?? null,
                'reviews_count' => $item['reviews'] ?? null,
                'delivery' => $item['delivery'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Extract numeric price from SERP API result.
     */
    private function extractPrice(array $item): ?float
    {
        if (isset($item['extracted_price'])) {
            return (float) $item['extracted_price'];
        }

        if (isset($item['price'])) {
            $cleaned = preg_replace('/[^0-9.]/', '', (string) $item['price']);
            return $cleaned !== '' ? (float) $cleaned : null;
        }

        return null;
    }
}
