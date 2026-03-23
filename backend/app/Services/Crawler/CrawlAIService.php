<?php

namespace App\Services\Crawler;

use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrawlAIService
{
    private readonly bool $enabled;

    private readonly string $baseUrl;

    private readonly ?string $apiToken;

    public function __construct(
        private readonly SettingService $settingService,
    ) {
        $configuredUrl = rtrim(
            $this->settingService->get('price_search', 'crawl4ai_base_url') ?? 'http://crawl4ai:11235',
            '/'
        );

        // No SSRF validation here — this is a server-configured internal service URL
        // (typically a Docker container hostname), not user-supplied input.

        $this->enabled = (bool) $this->settingService->get('price_search', 'crawl4ai_enabled');
        $this->baseUrl = $configuredUrl;
        $this->apiToken = $this->settingService->get('price_search', 'crawl4ai_api_token');
    }

    /**
     * Check if CrawlAI is enabled and reachable.
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            Log::debug('CrawlAIService: Health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Scrape a URL and return the page content as markdown.
     */
    public function scrapeUrl(string $url, array $options = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $payload = [
                'urls' => $url,
                'priority' => $options['priority'] ?? 5,
                'js_only' => $options['js_only'] ?? false,
                'wait_for' => $options['wait_for'] ?? '',
                'screenshot' => false,
            ];

            $response = $this->request('POST', '/crawl', $payload);

            if (!$response->successful()) {
                Log::error('CrawlAIService: Scrape failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            // CrawlAI returns results in a 'result' or 'results' key
            $result = $data['result'] ?? $data['results'][0] ?? $data;

            return [
                'success' => true,
                'url' => $url,
                'content' => $result['markdown'] ?? $result['cleaned_html'] ?? '',
                'html' => $result['html'] ?? '',
                'metadata' => $result['metadata'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('CrawlAIService: Scrape exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Scrape a URL using CSS selectors for LLM-free extraction.
     * Uses the store's scrape_instructions to extract structured data without LLM.
     *
     * @param string $url The URL to scrape
     * @param array $selectors CSS selectors for extraction, e.g.:
     *   ['price_selector' => '.product-price', 'name_selector' => '.product-title']
     */
    public function scrapeWithCssExtraction(string $url, array $selectors): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $payload = [
                'urls' => $url,
                'css_selector' => $selectors['container_selector'] ?? 'body',
                'screenshot' => false,
            ];

            $response = $this->request('POST', '/crawl', $payload);

            if (!$response->successful()) {
                Log::error('CrawlAIService: CSS extraction failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();
            $result = $data['result'] ?? $data['results'][0] ?? $data;

            return [
                'success' => true,
                'url' => $url,
                'content' => $result['markdown'] ?? '',
                'html' => $result['html'] ?? '',
                'extracted_content' => $result['extracted_content'] ?? null,
                'metadata' => $result['metadata'] ?? [],
                'selectors' => $selectors,
            ];
        } catch (\Exception $e) {
            Log::error('CrawlAIService: CSS extraction exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Scrape a URL using LLM-based extraction.
     * Sends a natural language extraction prompt along with the page content.
     */
    public function scrapeWithLlmExtraction(string $url, string $extractionPrompt): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $payload = [
                'urls' => $url,
                'extraction_config' => [
                    'type' => 'llm',
                    'instruction' => $extractionPrompt,
                ],
                'screenshot' => false,
            ];

            $response = $this->request('POST', '/crawl', $payload);

            if (!$response->successful()) {
                Log::error('CrawlAIService: LLM extraction failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();
            $result = $data['result'] ?? $data['results'][0] ?? $data;

            return [
                'success' => true,
                'url' => $url,
                'content' => $result['markdown'] ?? '',
                'extracted_content' => $result['extracted_content'] ?? null,
                'metadata' => $result['metadata'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('CrawlAIService: LLM extraction exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Make a request to the CrawlAI API.
     */
    private function request(string $method, string $path, array $payload = []): \Illuminate\Http\Client\Response
    {
        $http = Http::timeout(90)->baseUrl($this->baseUrl);

        if ($this->apiToken) {
            $http = $http->withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ]);
        }

        return match (strtoupper($method)) {
            'POST' => $http->post($path, $payload),
            'GET' => $http->get($path, $payload),
            default => $http->post($path, $payload),
        };
    }
}
