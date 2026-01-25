<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Crawl4AIService
 *
 * Service for scraping web pages using the local Crawl4AI Python service.
 * Runs inside the same container on localhost:5000.
 *
 * This service replaces Firecrawl for web scraping operations, providing:
 * - Single URL scraping with markdown output
 * - Batch URL scraping for multiple pages
 * - No external API costs (only LLM extraction costs remain)
 *
 * @see docs/adr/016-crawl4ai-integration.md
 */
class Crawl4AIService
{
    /**
     * Base URL for the local Crawl4AI Python service.
     */
    protected const BASE_URL = 'http://127.0.0.1:5000';

    /**
     * Default timeout in seconds for HTTP requests.
     */
    protected int $timeout = 60;

    /**
     * Patterns that may indicate bot detection, CAPTCHAs, or blocked content.
     */
    protected const SUSPICIOUS_PATTERNS = [
        'access denied',
        'robot check',
        'please verify',
        'captcha',
        'blocked',
        'unusual traffic',
        'not a robot',
        'enable javascript',
        'javascript is required',
        'sorry, we just need to make sure you\'re not a robot',
        'page could not be loaded',
        'checking your browser',
    ];

    /**
     * Scrape a single URL and return markdown content.
     *
     * @param string $url The URL to scrape
     * @param array $options Options including:
     *   - wait_for: CSS selector to wait for before scraping
     *   - timeout: Page load timeout in seconds (default 30)
     * @return array{success: bool, markdown: ?string, html: ?string, title: ?string, error: ?string}
     * @throws \RuntimeException If the service request fails
     * @throws \InvalidArgumentException If the URL is invalid
     */
    public function scrapeUrl(string $url, array $options = []): array
    {
        // Validate URL before sending to service
        if (!$this->isValidUrl($url)) {
            Log::warning('Crawl4AIService: Invalid URL provided', ['url' => $url]);
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        Log::info('Crawl4AIService: Scraping URL', ['url' => $url]);

        try {
            $start = microtime(true);
            $response = Http::timeout($this->timeout)
                ->post(self::BASE_URL . '/scrape', [
                    'url' => $url,
                    'wait_for' => $options['wait_for'] ?? null,
                    'timeout' => ($options['timeout'] ?? 30) * 1000,
                ]);
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if (!$response->successful()) {
                Log::error('Crawl4AIService: Request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'duration_ms' => $durationMs,
                ]);
                throw new \RuntimeException('Crawl4AI service request failed');
            }

            $data = $response->json();
            $markdown = $data['markdown'] ?? '';

            Log::info('Crawl4AIService: Scrape completed', [
                'url' => $url,
                'success' => $data['success'] ?? false,
                'markdown_length' => strlen($markdown),
                'duration_ms' => $durationMs,
            ]);

            $this->logContentQuality($url, $markdown, $data['success'] ?? false, $options['debug'] ?? false);

            return $data;
        } catch (\Exception $e) {
            Log::error('Crawl4AIService: Exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Scrape multiple URLs concurrently.
     *
     * @param array $urls Array of URLs to scrape
     * @param array $options Options including:
     *   - timeout: Page load timeout in seconds (default 30)
     * @return array Array of scrape results for each URL
     * @throws \RuntimeException If the batch request fails
     */
    public function scrapeUrls(array $urls, array $options = []): array
    {
        if (empty($urls)) {
            return [];
        }

        // Validate URLs before sending to service
        $validUrls = array_filter($urls, fn($url) => $this->isValidUrl($url));
        if (empty($validUrls)) {
            Log::warning('Crawl4AIService: No valid URLs provided for batch scraping');
            return [];
        }

        Log::info('Crawl4AIService: Batch scraping', [
            'count' => count($validUrls),
            'invalid_count' => count($urls) - count($validUrls),
        ]);

        try {
            $start = microtime(true);
            $response = Http::timeout($this->timeout * 2)
                ->post(self::BASE_URL . '/batch', [
                    'urls' => array_values($validUrls), // Re-index array
                    'timeout' => ($options['timeout'] ?? 30) * 1000,
                ]);
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if (!$response->successful()) {
                Log::error('Crawl4AIService: Batch request failed', [
                    'status' => $response->status(),
                    'urls_count' => count($validUrls),
                    'duration_ms' => $durationMs,
                ]);
                throw new \RuntimeException('Crawl4AI batch request failed');
            }

            $results = $response->json()['results'] ?? [];

            Log::info('Crawl4AIService: Batch scrape completed', [
                'urls_count' => count($validUrls),
                'results_count' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['success'] ?? false)),
                'duration_ms' => $durationMs,
            ]);

            $debug = $options['debug'] ?? false;
            foreach ($results as $index => $page) {
                $url = $validUrls[$index] ?? 'unknown';
                $markdown = $page['markdown'] ?? '';
                $this->logContentQuality($url, $markdown, $page['success'] ?? false, $debug);
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Crawl4AIService: Batch scrape exception', [
                'urls_count' => count($validUrls),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if Crawl4AI service is available.
     *
     * @return bool True if the service is running and healthy
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get(self::BASE_URL . '/health');
            return $response->successful() &&
                   ($response->json()['status'] ?? '') === 'ok';
        } catch (\Exception $e) {
            Log::warning('Crawl4AIService: Health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set request timeout.
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Log content quality details: first ~500 chars of markdown and any suspicious patterns.
     * Helps diagnose CAPTCHAs, blocks, or empty pages when 0 prices are found.
     *
     * @param string $url The URL that was scraped
     * @param string $markdown The markdown content returned
     * @param bool $success Whether the scrape reported success
     * @param bool $debug When true, use info level; otherwise debug (avoids flooding production logs)
     * @return void
     */
    protected function logContentQuality(string $url, string $markdown, bool $success, bool $debug = false): void
    {
        $sample = mb_substr($markdown, 0, 500);
        $suspicious = $this->detectSuspiciousContent($markdown);
        $level = $debug ? 'info' : 'debug';

        Log::log($level, 'Crawl4AIService: Content quality', [
            'url' => $url,
            'success' => $success,
            'markdown_length' => strlen($markdown),
            'content_sample' => $sample ?: '(empty)',
            'suspicious_patterns' => $suspicious,
        ]);

        if (!empty($suspicious)) {
            Log::warning('Crawl4AIService: Suspicious content detected (possible bot block or CAPTCHA)', [
                'url' => $url,
                'patterns' => $suspicious,
            ]);
        }
    }

    /**
     * Detect suspicious patterns in content that may indicate bot blocking or CAPTCHA.
     *
     * @param string $content The page content to check
     * @return array List of matched pattern names
     */
    protected function detectSuspiciousContent(string $content): array
    {
        $lower = strtolower($content);
        $found = [];

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $found[] = $pattern;
            }
        }

        return $found;
    }

    /**
     * Validate that a URL is properly formatted and uses http/https.
     *
     * @param string $url The URL to validate
     * @return bool True if valid, false otherwise
     */
    protected function isValidUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Use filter_var for basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Parse the URL and check scheme
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }

        // Only allow http and https schemes
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return true;
    }
}
