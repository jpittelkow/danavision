<?php

namespace App\Services\Crawler;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FirecrawlService
 * 
 * Service for interacting with the Firecrawl.dev API for web crawling
 * and price extraction. Uses:
 * - Agent API (v2) for autonomous product discovery
 * - Scrape API (v1) for URL-based price refresh
 *
 * @see https://docs.firecrawl.dev/api-reference/endpoint/agent
 * @see https://docs.firecrawl.dev/api-reference/endpoint/scrape
 */
class FirecrawlService
{
    protected const BASE_URL_V1 = 'https://api.firecrawl.dev/v1';
    protected const BASE_URL_V2 = 'https://api.firecrawl.dev/v2';
    
    protected int $userId;
    protected ?string $apiKey = null;
    protected int $timeout = 120; // seconds for individual requests
    protected int $agentPollTimeout = 300; // 5 minutes max for agent jobs
    protected int $pollInterval = 5; // seconds between polls

    /**
     * Create a new FirecrawlService instance.
     *
     * @param int $userId The user ID to fetch API key for
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->apiKey = Setting::get(Setting::FIRECRAWL_API_KEY, $userId);
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
     * Check if the service is available (has API key configured).
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get the user's home address for local searches.
     *
     * @return string|null
     */
    protected function getUserAddress(): ?string
    {
        return Setting::get(Setting::HOME_ADDRESS, $this->userId);
    }

    /**
     * Get the user's zip code for local searches.
     *
     * @return string|null
     */
    protected function getUserZipCode(): ?string
    {
        return Setting::get(Setting::HOME_ZIP_CODE, $this->userId);
    }

    /**
     * Discover product prices using the Firecrawl Agent API (v2).
     * 
     * The Agent API autonomously searches the web for product prices
     * without needing specific URLs. It can find products across
     * multiple retailers.
     *
     * @param string $productName The product name to search for
     * @param array $options Options including:
     *   - shop_local: bool - Whether to search only local stores
     *   - upc: string|null - Product UPC for more accurate matching
     *   - brand: string|null - Product brand
     *   - is_generic: bool - Whether this is a generic item (e.g., produce)
     *   - unit_of_measure: string|null - Unit of measure for generic items
     * @return FirecrawlResult
     */
    public function discoverProductPrices(string $productName, array $options = []): FirecrawlResult
    {
        if (!$this->isAvailable()) {
            Log::warning('FirecrawlService: API key not configured', ['user_id' => $this->userId]);
            return FirecrawlResult::error('Firecrawl API key not configured');
        }

        $shopLocal = $options['shop_local'] ?? false;
        $upc = $options['upc'] ?? null;
        $brand = $options['brand'] ?? null;
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;

        // Build the search query
        $searchQuery = $productName;
        if ($brand && !str_contains(strtolower($productName), strtolower($brand))) {
            $searchQuery = "{$brand} {$productName}";
        }

        // Build the agent prompt
        $prompt = $this->buildDiscoveryPrompt($searchQuery, [
            'shop_local' => $shopLocal,
            'upc' => $upc,
            'is_generic' => $isGeneric,
            'unit_of_measure' => $unitOfMeasure,
        ]);

        Log::info('FirecrawlService: Starting agent discovery', [
            'user_id' => $this->userId,
            'product' => $productName,
            'search_query' => $searchQuery,
            'shop_local' => $shopLocal,
            'is_generic' => $isGeneric,
        ]);

        try {
            // Start the agent job using v2 endpoint
            $startResponse = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL_V2 . '/agent', [
                    'prompt' => $prompt,
                    'schema' => $this->buildPriceSchema($isGeneric),
                    'maxCredits' => 100, // Limit credits per search
                ]);

            Log::info('FirecrawlService: Agent API initial response', [
                'status' => $startResponse->status(),
                'body_preview' => substr($startResponse->body(), 0, 500),
            ]);

            if (!$startResponse->successful()) {
                return $this->handleApiError($startResponse, $productName, 'Agent');
            }

            $startData = $startResponse->json();
            $jobId = $startData['id'] ?? $startData['jobId'] ?? null;

            // If no job ID, the response might contain results directly
            if (!$jobId) {
                Log::info('FirecrawlService: Agent returned immediate results', [
                    'response_keys' => array_keys($startData),
                ]);
                
                $results = $startData['data'] ?? $startData['results'] ?? $startData;
                if (is_array($results)) {
                    $normalizedResults = $this->normalizeResults($results);
                    return $this->createResultWithValidation($normalizedResults, $productName, 'initial_discovery');
                }
                
                return FirecrawlResult::error('Agent returned unexpected response format');
            }

            Log::info('FirecrawlService: Agent job started, polling for results', [
                'job_id' => $jobId,
                'product' => $productName,
            ]);

            // Poll for results
            return $this->pollAgentJob($jobId, $productName);

        } catch (\Exception $e) {
            Log::error('FirecrawlService: Agent API exception', [
                'product' => $productName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return FirecrawlResult::error("Firecrawl request failed: {$e->getMessage()}");
        }
    }

    /**
     * Poll the agent job until completion or timeout.
     *
     * @param string $jobId The agent job ID
     * @param string $productName For logging
     * @return FirecrawlResult
     */
    protected function pollAgentJob(string $jobId, string $productName): FirecrawlResult
    {
        $startTime = time();
        $attempts = 0;
        $maxAttempts = (int) ($this->agentPollTimeout / $this->pollInterval);

        while ($attempts < $maxAttempts) {
            $attempts++;
            $elapsed = time() - $startTime;

            Log::info('FirecrawlService: Polling agent job', [
                'job_id' => $jobId,
                'attempt' => $attempts,
                'elapsed_seconds' => $elapsed,
            ]);

            try {
                $statusResponse = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                    ])
                    ->get(self::BASE_URL_V2 . '/agent/' . $jobId);

                if (!$statusResponse->successful()) {
                    Log::warning('FirecrawlService: Agent status check failed', [
                        'job_id' => $jobId,
                        'status' => $statusResponse->status(),
                        'body' => $statusResponse->body(),
                    ]);
                    
                    // Don't fail immediately on status check error, might be transient
                    sleep($this->pollInterval);
                    continue;
                }

                $statusData = $statusResponse->json();
                $status = $statusData['status'] ?? 'unknown';

                Log::info('FirecrawlService: Agent job status', [
                    'job_id' => $jobId,
                    'status' => $status,
                    'response_keys' => array_keys($statusData),
                ]);

                if ($status === 'completed' || $status === 'done') {
                    $results = $statusData['data'] ?? $statusData['results'] ?? [];
                    $normalizedResults = $this->normalizeResults($results);
                    
                    Log::info('FirecrawlService: Agent job completed', [
                        'job_id' => $jobId,
                        'product' => $productName,
                        'raw_results_count' => is_array($results) ? count($results) : 0,
                        'normalized_results_count' => count($normalizedResults),
                        'elapsed_seconds' => time() - $startTime,
                    ]);

                    return $this->createResultWithValidation($normalizedResults, $productName, 'initial_discovery');
                }

                if ($status === 'failed' || $status === 'error') {
                    $error = $statusData['error'] ?? $statusData['message'] ?? 'Agent job failed';
                    Log::error('FirecrawlService: Agent job failed', [
                        'job_id' => $jobId,
                        'error' => $error,
                    ]);
                    return FirecrawlResult::error("Agent job failed: {$error}");
                }

                // Job still processing, wait and poll again
                sleep($this->pollInterval);

            } catch (\Exception $e) {
                Log::warning('FirecrawlService: Poll exception', [
                    'job_id' => $jobId,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                sleep($this->pollInterval);
            }
        }

        Log::error('FirecrawlService: Agent job timed out', [
            'job_id' => $jobId,
            'product' => $productName,
            'timeout_seconds' => $this->agentPollTimeout,
        ]);

        return FirecrawlResult::error("Agent job timed out after {$this->agentPollTimeout} seconds");
    }

    /**
     * Create a result with validation - fail if no results found.
     *
     * @param array $normalizedResults
     * @param string $productName
     * @param string $source
     * @return FirecrawlResult
     */
    protected function createResultWithValidation(array $normalizedResults, string $productName, string $source): FirecrawlResult
    {
        if (empty($normalizedResults)) {
            Log::warning('FirecrawlService: No price results found', [
                'product' => $productName,
                'source' => $source,
            ]);
            return FirecrawlResult::error("No price results found for '{$productName}'. The product may not be available online or the search terms need adjustment.");
        }

        Log::info('FirecrawlService: Discovery successful', [
            'product' => $productName,
            'results_count' => count($normalizedResults),
            'lowest_price' => min(array_column($normalizedResults, 'price')),
            'highest_price' => max(array_column($normalizedResults, 'price')),
        ]);

        return FirecrawlResult::success($normalizedResults, $source);
    }

    /**
     * Handle API error responses with detailed logging.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $productName
     * @param string $endpoint
     * @return FirecrawlResult
     */
    protected function handleApiError($response, string $productName, string $endpoint): FirecrawlResult
    {
        $statusCode = $response->status();
        $body = $response->body();
        $jsonError = $response->json('error') ?? $response->json('message') ?? null;
        $errorMessage = $jsonError ?? $body;

        Log::error("FirecrawlService: {$endpoint} API error", [
            'status' => $statusCode,
            'error' => $errorMessage,
            'product' => $productName,
            'body_preview' => substr($body, 0, 1000),
        ]);

        // Return helpful error messages based on status code
        $friendlyMessage = match ($statusCode) {
            400 => "Invalid request to Firecrawl: {$errorMessage}",
            401, 403 => 'Firecrawl API authentication failed. Please verify your API key in Settings.',
            402 => 'Firecrawl API payment required. Please check your account credits.',
            404 => "Firecrawl {$endpoint} endpoint not found. Your API plan may not include this feature.",
            429 => 'Firecrawl API rate limit exceeded. Please try again later.',
            default => "Firecrawl API error ({$statusCode}): {$errorMessage}",
        };

        return FirecrawlResult::error($friendlyMessage);
    }

    /**
     * Scrape specific URLs for price updates using the Firecrawl Scrape API (v1).
     * 
     * Used for daily price refreshes when we already have product URLs.
     *
     * @param array $urls Array of URLs to scrape
     * @param array $options Additional options
     * @return FirecrawlResult
     */
    public function scrapeProductUrls(array $urls, array $options = []): FirecrawlResult
    {
        if (!$this->isAvailable()) {
            Log::warning('FirecrawlService: API key not configured for scrape', ['user_id' => $this->userId]);
            return FirecrawlResult::error('Firecrawl API key not configured');
        }

        if (empty($urls)) {
            Log::warning('FirecrawlService: No URLs provided for scraping');
            return FirecrawlResult::error('No URLs provided for scraping');
        }

        Log::info('FirecrawlService: Starting URL scrape', [
            'user_id' => $this->userId,
            'urls_count' => count($urls),
            'urls' => array_slice($urls, 0, 5), // Log first 5 URLs
        ]);

        $allResults = [];
        $errors = [];

        foreach ($urls as $index => $url) {
            Log::info('FirecrawlService: Scraping URL', [
                'index' => $index + 1,
                'total' => count($urls),
                'url' => $url,
            ]);

            try {
                $result = $this->scrapeSingleUrl($url, $options);
                if ($result !== null) {
                    $allResults[] = $result;
                    Log::info('FirecrawlService: URL scrape successful', [
                        'url' => $url,
                        'price' => $result['price'],
                        'store' => $result['store_name'],
                    ]);
                } else {
                    Log::warning('FirecrawlService: URL scrape returned no price', [
                        'url' => $url,
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to scrape {$url}: {$e->getMessage()}";
                Log::warning('FirecrawlService: URL scrape error', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('FirecrawlService: URL scraping completed', [
            'urls_count' => count($urls),
            'results_count' => count($allResults),
            'errors_count' => count($errors),
        ]);

        if (empty($allResults)) {
            $errorMsg = !empty($errors) 
                ? implode('; ', array_slice($errors, 0, 3)) 
                : 'No price data could be extracted from the provided URLs';
            return FirecrawlResult::error($errorMsg);
        }

        return FirecrawlResult::success($allResults, 'daily_refresh');
    }

    /**
     * Scrape a single URL for price data using v1/scrape endpoint.
     *
     * @param string $url The URL to scrape
     * @param array $options Additional options
     * @return array|null The scraped data or null on failure
     */
    protected function scrapeSingleUrl(string $url, array $options = []): ?array
    {
        Log::debug('FirecrawlService: Sending scrape request', [
            'url' => $url,
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post(self::BASE_URL_V1 . '/scrape', [
                'url' => $url,
                'formats' => ['markdown', 'json'],
                'jsonOptions' => [
                    'schema' => $this->buildScrapeSchema(),
                    'prompt' => 'Extract the product price, name, stock status, and store name from this page.',
                ],
                'onlyMainContent' => true,
                'timeout' => $this->timeout * 1000, // milliseconds
            ]);

        Log::debug('FirecrawlService: Scrape response received', [
            'url' => $url,
            'status' => $response->status(),
            'body_length' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            Log::error('FirecrawlService: Scrape request failed', [
                'url' => $url,
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException("Scrape failed ({$response->status()}): {$error}");
        }

        $data = $response->json('data') ?? $response->json();
        
        Log::debug('FirecrawlService: Scrape data parsed', [
            'url' => $url,
            'data_keys' => is_array($data) ? array_keys($data) : 'not_array',
        ]);

        if (empty($data)) {
            return null;
        }

        // Extract JSON format result - check multiple possible locations
        $jsonData = $data['json'] ?? $data['extract'] ?? $data['llm_extraction'] ?? null;
        
        if (empty($jsonData)) {
            Log::warning('FirecrawlService: No structured data in scrape response', [
                'url' => $url,
                'available_keys' => array_keys($data),
            ]);
            return null;
        }

        // Handle both single object and array responses
        if (isset($jsonData[0]) && is_array($jsonData[0])) {
            $jsonData = $jsonData[0];
        }
        
        if (!isset($jsonData['price']) || $jsonData['price'] === null) {
            Log::warning('FirecrawlService: No price found in structured data', [
                'url' => $url,
                'json_data_keys' => array_keys($jsonData),
            ]);
            return null;
        }

        $result = [
            'store_name' => $jsonData['store_name'] ?? $this->extractStoreFromUrl($url),
            'item_name' => $jsonData['item_name'] ?? $jsonData['title'] ?? $jsonData['product_name'] ?? null,
            'price' => (float) $jsonData['price'],
            'stock_status' => $this->normalizeStockStatus($jsonData['stock_status'] ?? $jsonData['availability'] ?? null),
            'unit_of_measure' => $jsonData['unit_of_measure'] ?? $jsonData['unit'] ?? null,
            'product_url' => $url,
        ];

        Log::info('FirecrawlService: Extracted price data', [
            'url' => $url,
            'price' => $result['price'],
            'store' => $result['store_name'],
            'stock' => $result['stock_status'],
        ]);

        return $result;
    }

    /**
     * Build the discovery prompt for the Agent API.
     *
     * @param string $productName
     * @param array $options
     * @return string
     */
    protected function buildDiscoveryPrompt(string $productName, array $options): string
    {
        $shopLocal = $options['shop_local'] ?? false;
        $upc = $options['upc'] ?? null;
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;

        $prompt = "Search the web and find current prices for '{$productName}'";
        
        if ($upc) {
            $prompt .= " (UPC/barcode: {$upc})";
        }

        $prompt .= " from online retailers and stores.";

        // Add local store constraint
        if ($shopLocal) {
            $address = $this->getUserAddress();
            $zipCode = $this->getUserZipCode();
            $location = $address ?? $zipCode;

            if ($location) {
                $prompt .= "\n\n*** IMPORTANT: PRIORITIZE LOCAL STORES ***";
                $prompt .= "\nUser location: {$location}";
                $prompt .= "\nPrioritize stores near this location: Walmart, Target, Kroger, Costco, Publix, Safeway, Whole Foods, local grocery stores.";
                $prompt .= "\nInclude both local store websites and major online retailers.";
            }
        } else {
            $prompt .= "\n\nSearch major retailers including: Amazon, Walmart, Target, Best Buy, Costco, and specialty stores.";
        }

        // Add generic item context
        if ($isGeneric) {
            $prompt .= "\n\nThis is a generic/commodity item (like fresh produce or bulk goods).";
            if ($unitOfMeasure) {
                $prompt .= " Prices should be per {$unitOfMeasure}.";
            }
            $prompt .= "\nFocus on grocery stores, supermarkets, and food retailers.";
        }

        $prompt .= "\n\nFor each store/retailer found, extract and return:";
        $prompt .= "\n1. store_name: The retailer/store name (e.g., 'Amazon', 'Walmart', 'Target')";
        $prompt .= "\n2. item_name: The exact product name as listed on the store's website";
        $prompt .= "\n3. price: The current selling price as a number (no currency symbols, e.g., 29.99)";
        $prompt .= "\n4. stock_status: One of 'in_stock', 'out_of_stock', or 'limited_stock'";
        $prompt .= "\n5. unit_of_measure: Unit if applicable (lb, oz, each, pack, etc.)";
        $prompt .= "\n6. product_url: The direct URL to the product page on that store's website";
        
        $prompt .= "\n\nReturn results from multiple different stores to allow price comparison.";
        $prompt .= "\nOnly include results where you can confirm an actual price - do not guess or estimate prices.";

        Log::debug('FirecrawlService: Built discovery prompt', [
            'product' => $productName,
            'prompt_length' => strlen($prompt),
            'shop_local' => $shopLocal,
            'is_generic' => $isGeneric,
        ]);

        return $prompt;
    }

    /**
     * Build the JSON schema for Agent API discovery.
     *
     * @param bool $isGeneric Whether this is a generic item
     * @return array
     */
    protected function buildPriceSchema(bool $isGeneric = false): array
    {
        $properties = [
            'store_name' => [
                'type' => 'string',
                'description' => 'The name of the retailer/store',
            ],
            'item_name' => [
                'type' => 'string',
                'description' => 'The product name as listed by the store',
            ],
            'price' => [
                'type' => 'number',
                'description' => 'The current price (numeric value only)',
            ],
            'stock_status' => [
                'type' => 'string',
                'enum' => ['in_stock', 'out_of_stock', 'limited'],
                'description' => 'Product availability status',
            ],
            'product_url' => [
                'type' => 'string',
                'description' => 'Direct URL to the product page',
            ],
        ];

        // Add unit of measure for generic items
        $properties['unit_of_measure'] = [
            'type' => ['string', 'null'],
            'description' => 'Unit of measure (lb, oz, each, gallon, etc.) if applicable',
        ];

        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => ['store_name', 'item_name', 'price', 'product_url'],
            ],
        ];
    }

    /**
     * Build the JSON schema for Scrape API URL refresh.
     *
     * @return array
     */
    protected function buildScrapeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_name' => [
                    'type' => 'string',
                    'description' => 'The retailer name',
                ],
                'item_name' => [
                    'type' => 'string',
                    'description' => 'The product name',
                ],
                'price' => [
                    'type' => 'number',
                    'description' => 'The current price',
                ],
                'stock_status' => [
                    'type' => 'string',
                    'description' => 'Availability: in_stock, out_of_stock, or limited',
                ],
                'unit_of_measure' => [
                    'type' => ['string', 'null'],
                    'description' => 'Unit of measure if applicable',
                ],
            ],
            'required' => ['price'],
        ];
    }

    /**
     * Normalize results from Firecrawl API to a consistent format.
     *
     * @param array $results Raw results from Firecrawl
     * @return array Normalized results
     */
    protected function normalizeResults(array $results): array
    {
        $normalized = [];

        foreach ($results as $result) {
            // Skip results without price
            if (!isset($result['price']) || $result['price'] === null) {
                continue;
            }

            $price = (float) $result['price'];
            if ($price <= 0) {
                continue;
            }

            $normalized[] = [
                'store_name' => $result['store_name'] ?? $result['retailer'] ?? 'Unknown',
                'item_name' => $result['item_name'] ?? $result['title'] ?? $result['product_name'] ?? null,
                'price' => $price,
                'stock_status' => $this->normalizeStockStatus($result['stock_status'] ?? null),
                'unit_of_measure' => $result['unit_of_measure'] ?? null,
                'product_url' => $result['product_url'] ?? $result['url'] ?? null,
            ];
        }

        // Sort by price ascending
        usort($normalized, fn($a, $b) => $a['price'] <=> $b['price']);

        return $normalized;
    }

    /**
     * Normalize stock status to a consistent value.
     *
     * @param string|null $status
     * @return string
     */
    protected function normalizeStockStatus(?string $status): string
    {
        if ($status === null) {
            return 'in_stock';
        }

        $status = strtolower(trim($status));

        $inStockVariants = ['in_stock', 'in stock', 'available', 'yes', 'true', '1'];
        $outOfStockVariants = ['out_of_stock', 'out of stock', 'unavailable', 'no', 'false', '0', 'sold out'];
        $limitedVariants = ['limited', 'low stock', 'few left', 'limited stock'];

        if (in_array($status, $outOfStockVariants)) {
            return 'out_of_stock';
        }

        if (in_array($status, $limitedVariants)) {
            return 'limited_stock';
        }

        return 'in_stock';
    }

    /**
     * Extract store name from a URL.
     *
     * @param string $url
     * @return string
     */
    protected function extractStoreFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return 'Unknown';
        }

        // Remove www. prefix
        $host = preg_replace('/^www\./', '', $host);

        // Map common domains to store names
        $storeMap = [
            'amazon.com' => 'Amazon',
            'walmart.com' => 'Walmart',
            'target.com' => 'Target',
            'bestbuy.com' => 'Best Buy',
            'costco.com' => 'Costco',
            'kroger.com' => 'Kroger',
            'publix.com' => 'Publix',
            'safeway.com' => 'Safeway',
            'wholefoodsmarket.com' => 'Whole Foods',
            'homedepot.com' => 'Home Depot',
            'lowes.com' => "Lowe's",
            'ebay.com' => 'eBay',
            'newegg.com' => 'Newegg',
        ];

        foreach ($storeMap as $domain => $name) {
            if (str_contains($host, $domain)) {
                return $name;
            }
        }

        // Return the domain with first letter capitalized
        $parts = explode('.', $host);
        return ucfirst($parts[0]);
    }

    /**
     * Set the request timeout.
     *
     * @param int $seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}
