<?php

namespace App\Services\Crawler;

use App\Models\Setting;
use App\Models\Store;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * StoreAutoConfigService
 *
 * Automatically configures stores for price discovery by:
 * 1. Crawling store websites to find search functionality
 * 2. Using AI to detect search URL patterns
 * 3. Generating URL templates with {query} placeholders
 * 4. Validating that detected templates work correctly
 *
 * This service reduces manual configuration effort when adding
 * new stores to the Store Registry.
 */
class StoreAutoConfigService
{
    /**
     * Firecrawl API base URL.
     */
    protected const FIRECRAWL_BASE_URL = 'https://api.firecrawl.dev/v1';

    /**
     * Request timeout in seconds.
     */
    protected const TIMEOUT = 60;

    /**
     * The user ID for fetching API keys.
     */
    protected int $userId;

    /**
     * The Firecrawl API key.
     */
    protected ?string $firecrawlApiKey;

    /**
     * The AI service for intelligent analysis.
     */
    protected ?AIService $aiService = null;

    /**
     * Create a new StoreAutoConfigService instance.
     *
     * @param int $userId The user ID for API key retrieval
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->firecrawlApiKey = Setting::get(Setting::FIRECRAWL_API_KEY, $userId);
    }

    /**
     * Create an instance for a specific user.
     *
     * @param int $userId
     * @return self
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Check if the service is available (has required API keys).
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->firecrawlApiKey);
    }

    /**
     * Get the AI service instance.
     *
     * @return AIService
     */
    protected function getAIService(): AIService
    {
        if ($this->aiService === null) {
            $this->aiService = AIService::forUser($this->userId);
        }
        return $this->aiService;
    }

    /**
     * Detect the search URL template for a store website.
     *
     * @param string $websiteUrl The store's website URL (homepage)
     * @return array{success: bool, template?: string, instructions?: array, error?: string}
     */
    public function detectSearchUrlTemplate(string $websiteUrl): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Firecrawl API key not configured',
            ];
        }

        // Ensure URL has protocol
        if (!str_starts_with($websiteUrl, 'http')) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        Log::info('StoreAutoConfigService: Starting search URL detection', [
            'user_id' => $this->userId,
            'website' => $websiteUrl,
        ]);

        try {
            // Step 1: Scrape the homepage to analyze structure
            $homepageContent = $this->scrapeWebsite($websiteUrl);
            if ($homepageContent === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to scrape website homepage',
                ];
            }

            // Step 2: Use AI to analyze the page and detect search pattern
            $searchPattern = $this->analyzeSearchPattern($websiteUrl, $homepageContent);
            if ($searchPattern === null) {
                // Fallback: Try common search URL patterns
                $searchPattern = $this->tryCommonPatterns($websiteUrl);
            }

            if ($searchPattern === null) {
                return [
                    'success' => false,
                    'error' => 'Could not detect search URL pattern',
                ];
            }

            // Step 3: Validate the detected template
            $isValid = $this->validateSearchUrl($searchPattern, 'test product');
            if (!$isValid) {
                Log::warning('StoreAutoConfigService: Detected template failed validation', [
                    'template' => $searchPattern,
                ]);
                // Still return the template but mark it as unvalidated
            }

            Log::info('StoreAutoConfigService: Search URL detection completed', [
                'website' => $websiteUrl,
                'template' => $searchPattern,
                'validated' => $isValid,
            ]);

            return [
                'success' => true,
                'template' => $searchPattern,
                'validated' => $isValid,
            ];

        } catch (\Exception $e) {
            Log::error('StoreAutoConfigService: Error detecting search URL', [
                'website' => $websiteUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect scrape instructions for a store (product page selectors).
     *
     * @param string $websiteUrl The store's website URL
     * @param string|null $sampleProductUrl Optional sample product URL for analysis
     * @return array{success: bool, instructions?: array, error?: string}
     */
    public function detectScrapeInstructions(string $websiteUrl, ?string $sampleProductUrl = null): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Firecrawl API key not configured',
            ];
        }

        Log::info('StoreAutoConfigService: Starting scrape instructions detection', [
            'user_id' => $this->userId,
            'website' => $websiteUrl,
            'sample_product' => $sampleProductUrl,
        ]);

        try {
            // If we have a sample product URL, analyze it
            if ($sampleProductUrl) {
                $content = $this->scrapeWebsite($sampleProductUrl);
                if ($content) {
                    $instructions = $this->analyzeProductPage($content);
                    if ($instructions) {
                        return [
                            'success' => true,
                            'instructions' => $instructions,
                        ];
                    }
                }
            }

            // Return generic instructions as fallback
            return [
                'success' => true,
                'instructions' => $this->getDefaultScrapeInstructions(),
            ];

        } catch (\Exception $e) {
            Log::error('StoreAutoConfigService: Error detecting scrape instructions', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Auto-configure a store with detected settings.
     *
     * @param Store $store The store to configure
     * @return array{success: bool, changes: array, error?: string}
     */
    public function autoConfigureStore(Store $store): array
    {
        $changes = [];
        $errors = [];

        // Skip if already configured
        if ($store->search_url_template) {
            return [
                'success' => true,
                'changes' => [],
                'message' => 'Store already has search URL template configured',
            ];
        }

        // Build website URL from domain
        $websiteUrl = 'https://' . preg_replace('/^www\./', '', $store->domain);

        // Detect search URL template
        $templateResult = $this->detectSearchUrlTemplate($websiteUrl);
        if ($templateResult['success'] && !empty($templateResult['template'])) {
            $store->search_url_template = $templateResult['template'];
            $changes['search_url_template'] = $templateResult['template'];
        } else {
            $errors[] = $templateResult['error'] ?? 'Failed to detect search URL';
        }

        // Mark as auto-configured if we made changes
        if (!empty($changes)) {
            $store->auto_configured = true;
            $store->save();

            Log::info('StoreAutoConfigService: Store auto-configured', [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'changes' => $changes,
            ]);

            return [
                'success' => true,
                'changes' => $changes,
            ];
        }

        return [
            'success' => false,
            'changes' => [],
            'error' => implode('; ', $errors),
        ];
    }

    /**
     * Scrape a website using Firecrawl.
     *
     * @param string $url
     * @return array|null
     */
    protected function scrapeWebsite(string $url): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->firecrawlApiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::FIRECRAWL_BASE_URL . '/scrape', [
                    'url' => $url,
                    'formats' => ['markdown', 'html', 'links'],
                    'onlyMainContent' => false,
                    'includeTags' => ['form', 'input', 'a', 'link'],
                ]);

            if (!$response->successful()) {
                Log::warning('StoreAutoConfigService: Scrape failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('data') ?? $response->json();

        } catch (\Exception $e) {
            Log::error('StoreAutoConfigService: Scrape exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Analyze page content to detect search URL pattern using AI.
     *
     * @param string $websiteUrl
     * @param array $pageContent
     * @return string|null The detected search URL template
     */
    protected function analyzeSearchPattern(string $websiteUrl, array $pageContent): ?string
    {
        $domain = parse_url($websiteUrl, PHP_URL_HOST);
        $markdown = $pageContent['markdown'] ?? '';
        $html = $pageContent['html'] ?? '';
        $links = $pageContent['links'] ?? [];

        // First, try to find search-related links
        $searchLinks = array_filter($links, function ($link) {
            $url = is_array($link) ? ($link['url'] ?? $link['href'] ?? '') : $link;
            return str_contains(strtolower($url), 'search') 
                || str_contains(strtolower($url), 'query')
                || str_contains(strtolower($url), 'q=');
        });

        // If we found search links, analyze them
        if (!empty($searchLinks)) {
            foreach ($searchLinks as $link) {
                $url = is_array($link) ? ($link['url'] ?? $link['href'] ?? '') : $link;
                $template = $this->extractTemplateFromUrl($url, $websiteUrl);
                if ($template) {
                    return $template;
                }
            }
        }

        // Use AI to analyze the page structure
        $aiService = $this->getAIService();
        if (!$aiService->isAvailable()) {
            Log::warning('StoreAutoConfigService: AI service not available for analysis');
            return null;
        }

        // Truncate content for AI prompt
        $truncatedMarkdown = substr($markdown, 0, 4000);
        $truncatedHtml = substr($html, 0, 4000);

        $prompt = <<<PROMPT
Analyze this website content and identify the search URL pattern.

Domain: {$domain}
Website URL: {$websiteUrl}

Page Content (Markdown):
{$truncatedMarkdown}

Page HTML (truncated):
{$truncatedHtml}

Your task:
1. Find how the website's product search works
2. Identify the search URL pattern (e.g., /search?q=, /s?k=, etc.)
3. Return ONLY the full search URL template with {query} as a placeholder

Examples of valid templates:
- https://example.com/search?q={query}
- https://example.com/s?k={query}
- https://example.com/search/{query}

Return ONLY the template URL, nothing else. If you cannot determine the pattern, return "UNKNOWN".
PROMPT;

        try {
            $result = $aiService->complete($prompt);
            $response = trim($result['response'] ?? '');

            if ($response === 'UNKNOWN' || empty($response)) {
                return null;
            }

            // Validate the response looks like a URL template
            if (str_contains($response, '{query}') && str_contains($response, 'http')) {
                // Clean up the response
                $response = preg_replace('/^["\']|["\']$/', '', $response);
                return $response;
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('StoreAutoConfigService: AI analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract a template from a URL that contains search parameters.
     *
     * @param string $url
     * @param string $baseUrl
     * @return string|null
     */
    protected function extractTemplateFromUrl(string $url, string $baseUrl): ?string
    {
        // Make URL absolute if relative
        if (!str_starts_with($url, 'http')) {
            $parsed = parse_url($baseUrl);
            $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            $url = rtrim($base, '/') . '/' . ltrim($url, '/');
        }

        // Parse the URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }

        // Check query string for search parameters
        $query = $parsed['query'] ?? '';
        parse_str($query, $params);

        // Common search parameter names
        $searchParams = ['q', 'query', 'search', 'k', 's', 'keyword', 'term', 'text'];

        foreach ($searchParams as $param) {
            if (isset($params[$param])) {
                // Found a search parameter, build the template
                $params[$param] = '{query}';
                $newQuery = http_build_query($params);
                // Decode {query} since http_build_query encodes it
                $newQuery = str_replace('%7Bquery%7D', '{query}', $newQuery);
                
                $template = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
                if (!empty($parsed['path'])) {
                    $template .= $parsed['path'];
                }
                $template .= '?' . $newQuery;
                
                return $template;
            }
        }

        return null;
    }

    /**
     * Try common search URL patterns for the domain.
     *
     * @param string $websiteUrl
     * @return string|null
     */
    protected function tryCommonPatterns(string $websiteUrl): ?string
    {
        $parsed = parse_url($websiteUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // Common patterns to try
        $patterns = [
            $base . '/search?q={query}',
            $base . '/search?query={query}',
            $base . '/s?k={query}',
            $base . '/search/{query}',
            $base . '/products?search={query}',
            $base . '/shop/search?q={query}',
            $base . '/catalogsearch/result/?q={query}',
        ];

        foreach ($patterns as $pattern) {
            $testUrl = str_replace('{query}', 'test', $pattern);
            if ($this->urlReturnsSearchResults($testUrl)) {
                Log::info('StoreAutoConfigService: Found working pattern', [
                    'pattern' => $pattern,
                ]);
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Check if a URL returns valid search results.
     *
     * @param string $url
     * @return bool
     */
    protected function urlReturnsSearchResults(string $url): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; DanaVision/1.0)',
                ])
                ->get($url);

            // Check for successful response
            if (!$response->successful()) {
                return false;
            }

            // Check that the page has product-related content
            $body = strtolower($response->body());
            $indicators = ['product', 'price', 'add to cart', 'results', 'items'];
            
            foreach ($indicators as $indicator) {
                if (str_contains($body, $indicator)) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate that a search URL template works.
     *
     * @param string $template
     * @param string $testQuery
     * @return bool
     */
    public function validateSearchUrl(string $template, string $testQuery = 'laptop'): bool
    {
        $testUrl = str_replace('{query}', urlencode($testQuery), $template);
        return $this->urlReturnsSearchResults($testUrl);
    }

    /**
     * Analyze a product page to detect useful selectors.
     *
     * @param array $pageContent
     * @return array|null
     */
    protected function analyzeProductPage(array $pageContent): ?array
    {
        // This would use AI to analyze the page structure
        // For now, return generic instructions
        return $this->getDefaultScrapeInstructions();
    }

    /**
     * Get default scrape instructions for generic stores.
     *
     * @return array
     */
    protected function getDefaultScrapeInstructions(): array
    {
        return [
            'price_hints' => [
                'Look for elements with class containing "price"',
                'Check meta tags for price information',
                'Look for structured data (JSON-LD) with price',
            ],
            'title_hints' => [
                'Use the h1 tag for product title',
                'Check meta title or og:title',
            ],
            'availability_hints' => [
                'Look for "Add to Cart" button presence',
                'Check for "Out of Stock" text',
                'Look for availability in structured data',
            ],
        ];
    }

    /**
     * Configure multiple stores in batch.
     *
     * @param array<Store> $stores
     * @param callable|null $progressCallback Called after each store with (int $current, int $total, Store $store, array $result)
     * @return array{total: int, configured: int, failed: int, results: array}
     */
    public function batchConfigureStores(array $stores, ?callable $progressCallback = null): array
    {
        $results = [];
        $configured = 0;
        $failed = 0;
        $total = count($stores);

        foreach ($stores as $index => $store) {
            $result = $this->autoConfigureStore($store);
            $results[$store->id] = $result;

            if ($result['success'] && !empty($result['changes'])) {
                $configured++;
            } else {
                $failed++;
            }

            if ($progressCallback) {
                $progressCallback($index + 1, $total, $store, $result);
            }

            // Small delay to avoid rate limiting
            if ($index < $total - 1) {
                usleep(500000); // 0.5 second
            }
        }

        return [
            'total' => $total,
            'configured' => $configured,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
