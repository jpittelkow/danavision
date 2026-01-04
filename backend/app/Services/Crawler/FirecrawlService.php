<?php

namespace App\Services\Crawler;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FirecrawlService
 * 
 * Service for interacting with the Firecrawl.dev API for web crawling
 * and price extraction. Supports both Agent API (discovery) and 
 * Scrape API (URL-based refresh).
 *
 * @see https://docs.firecrawl.dev/features/agent
 * @see https://docs.firecrawl.dev/features/scrape
 */
class FirecrawlService
{
    protected const BASE_URL = 'https://api.firecrawl.dev/v1';
    
    protected int $userId;
    protected ?string $apiKey = null;
    protected int $timeout = 120; // seconds

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
     * Discover product prices using the Firecrawl Agent API.
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

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL . '/agent', [
                    'prompt' => $prompt,
                    'schema' => $this->buildPriceSchema($isGeneric),
                ]);

            if (!$response->successful()) {
                $errorMessage = $response->json('error') ?? $response->body();
                Log::warning('Firecrawl Agent API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product' => $productName,
                ]);
                return FirecrawlResult::error("Firecrawl API error: {$errorMessage}");
            }

            $data = $response->json();
            $results = $data['data'] ?? $data['results'] ?? [];

            // Normalize results
            $normalizedResults = $this->normalizeResults($results);

            Log::info('Firecrawl discovery completed', [
                'product' => $productName,
                'results_count' => count($normalizedResults),
                'shop_local' => $shopLocal,
            ]);

            return FirecrawlResult::success($normalizedResults, 'initial_discovery');

        } catch (\Exception $e) {
            Log::error('Firecrawl Agent API exception', [
                'product' => $productName,
                'error' => $e->getMessage(),
            ]);
            return FirecrawlResult::error("Firecrawl request failed: {$e->getMessage()}");
        }
    }

    /**
     * Scrape specific URLs for price updates using the Firecrawl Scrape API.
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
            return FirecrawlResult::error('Firecrawl API key not configured');
        }

        if (empty($urls)) {
            return FirecrawlResult::error('No URLs provided for scraping');
        }

        $allResults = [];
        $errors = [];

        foreach ($urls as $url) {
            try {
                $result = $this->scrapeSingleUrl($url, $options);
                if ($result !== null) {
                    $allResults[] = $result;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to scrape {$url}: {$e->getMessage()}";
                Log::warning('Firecrawl scrape error for URL', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allResults) && !empty($errors)) {
            return FirecrawlResult::error(implode('; ', $errors));
        }

        Log::info('Firecrawl URL scraping completed', [
            'urls_count' => count($urls),
            'results_count' => count($allResults),
            'errors_count' => count($errors),
        ]);

        return FirecrawlResult::success($allResults, 'daily_refresh');
    }

    /**
     * Scrape a single URL for price data.
     *
     * @param string $url The URL to scrape
     * @param array $options Additional options
     * @return array|null The scraped data or null on failure
     */
    protected function scrapeSingleUrl(string $url, array $options = []): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post(self::BASE_URL . '/scrape', [
                'url' => $url,
                'formats' => [
                    [
                        'type' => 'json',
                        'schema' => $this->buildScrapeSchema(),
                    ],
                ],
                'onlyMainContent' => true,
                'timeout' => $this->timeout * 1000, // milliseconds
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Scrape failed');
        }

        $data = $response->json('data') ?? $response->json();
        
        if (empty($data)) {
            return null;
        }

        // Extract JSON format result
        $jsonData = $data['json'] ?? $data;
        
        if (empty($jsonData) || !isset($jsonData['price'])) {
            return null;
        }

        return [
            'store_name' => $jsonData['store_name'] ?? $this->extractStoreFromUrl($url),
            'item_name' => $jsonData['item_name'] ?? $jsonData['title'] ?? null,
            'price' => (float) $jsonData['price'],
            'stock_status' => $this->normalizeStockStatus($jsonData['stock_status'] ?? null),
            'unit_of_measure' => $jsonData['unit_of_measure'] ?? null,
            'product_url' => $url,
        ];
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

        $prompt = "Find all stores selling '{$productName}'";
        
        if ($upc) {
            $prompt .= " (UPC: {$upc})";
        }

        $prompt .= ". Return current prices, stock availability, and product URLs.";

        // Add local store constraint
        if ($shopLocal) {
            $address = $this->getUserAddress();
            $zipCode = $this->getUserZipCode();
            $location = $address ?? $zipCode;

            if ($location) {
                $prompt .= "\n\n*** IMPORTANT: LOCAL STORES ONLY ***";
                $prompt .= "\nUser location: {$location}";
                $prompt .= "\nOnly return prices from physical stores near this location.";
                $prompt .= "\nPrioritize: Walmart, Target, Kroger, Costco, Publix, Safeway, Whole Foods, local grocery stores.";
                $prompt .= "\nExclude online-only retailers unless they have local pickup at a nearby store.";
            }
        }

        // Add generic item context
        if ($isGeneric) {
            $prompt .= "\n\nThis is a generic item (like produce or bulk goods).";
            if ($unitOfMeasure) {
                $prompt .= " Prices should be per {$unitOfMeasure}.";
            }
            $prompt .= "\nFocus on grocery stores and supermarkets.";
        }

        $prompt .= "\n\nFor each result, provide:";
        $prompt .= "\n- store_name: The retailer name";
        $prompt .= "\n- item_name: The exact product name as listed";
        $prompt .= "\n- price: Current price (number only, no currency symbol)";
        $prompt .= "\n- stock_status: 'in_stock', 'out_of_stock', or 'limited'";
        $prompt .= "\n- unit_of_measure: Unit if applicable (lb, oz, each, etc.)";
        $prompt .= "\n- product_url: Direct link to the product page";

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
