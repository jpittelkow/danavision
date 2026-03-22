<?php

namespace App\Services\Crawler;

use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirecrawlService
{
    private const BASE_URL = 'https://api.firecrawl.dev/v1';

    private readonly ?string $apiKey;

    public function __construct(
        private readonly SettingService $settingService,
    ) {
        $this->apiKey = $this->settingService->get('price_search', 'firecrawl_key');
    }

    /**
     * Scrape a single URL and return structured data.
     *
     * @param string $url The URL to scrape
     * @return array Structured scrape data including content, metadata, and extracted info
     */
    public function scrapeUrl(string $url): array
    {
        if (!$this->isAvailable()) {
            Log::warning('FirecrawlService: API key not configured');
            return [];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL . '/scrape', [
                    'url' => $url,
                    'formats' => ['markdown', 'html'],
                    'onlyMainContent' => true,
                ]);

            if (!$response->successful()) {
                Log::error('FirecrawlService: scrape request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            return [
                'success' => $data['success'] ?? false,
                'content' => $data['data']['markdown'] ?? '',
                'html' => $data['data']['html'] ?? '',
                'metadata' => $data['data']['metadata'] ?? [],
                'url' => $url,
            ];
        } catch (\Exception $e) {
            Log::error('FirecrawlService: scrape failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Search for products using Firecrawl's search API.
     *
     * @param string $query The search query
     * @param string|null $domain Optional domain to restrict search to
     * @return array Array of search results with content
     */
    public function searchProducts(string $query, ?string $domain = null): array
    {
        if (!$this->isAvailable()) {
            Log::warning('FirecrawlService: API key not configured');
            return [];
        }

        try {
            $searchQuery = $domain
                ? "site:{$domain} {$query}"
                : $query;

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL . '/search', [
                    'query' => $searchQuery,
                    'limit' => 10,
                    'scrapeOptions' => [
                        'formats' => ['markdown'],
                        'onlyMainContent' => true,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('FirecrawlService: search request failed', [
                    'query' => $searchQuery,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $results = [];

            foreach ($data['data'] ?? [] as $item) {
                $results[] = [
                    'url' => $item['url'] ?? '',
                    'title' => $item['metadata']['title'] ?? '',
                    'description' => $item['metadata']['description'] ?? '',
                    'content' => $item['markdown'] ?? '',
                    'metadata' => $item['metadata'] ?? [],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('FirecrawlService: search failed', [
                'query' => $query,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if the Firecrawl API is available (API key configured).
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}
