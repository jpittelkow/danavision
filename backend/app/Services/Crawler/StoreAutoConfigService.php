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
     * Known retail chains with their subsidiaries and search URL templates.
     * This helps configure stores that share the same backend (e.g., Kroger subsidiaries).
     */
    protected const KNOWN_CHAINS = [
        'kroger' => [
            'domain' => 'kroger.com',
            'template' => 'https://www.kroger.com/search?query={query}&searchType=default_search',
            'subsidiaries' => [
                'metro market',
                'pick n save',
                'pick \'n save',
                'mariano\'s',
                'marianos',
                'fred meyer',
                'ralphs',
                'king soopers',
                'fry\'s',
                'frys',
                'smith\'s',
                'smiths',
                'qfc',
                'quality food centers',
                'dillons',
                'city market',
                'baker\'s',
                'bakers',
                'gerbes',
                'jay c',
                'food 4 less',
                'foods co',
                'harris teeter',
                'ruler',
            ],
            'location_type' => 'store_id',
        ],
        'albertsons' => [
            'domain' => 'albertsons.com',
            'template' => 'https://www.albertsons.com/shop/search-results.html?q={query}',
            'subsidiaries' => [
                'safeway',
                'vons',
                'jewel-osco',
                'jewel osco',
                'acme',
                'shaw\'s',
                'shaws',
                'star market',
                'randalls',
                'tom thumb',
                'pavilions',
                'carrs',
                'haggen',
            ],
            'location_type' => 'store_id',
        ],
        'ahold_delhaize' => [
            'domain' => 'stopandshop.com',
            'template' => 'https://stopandshop.com/search?q={query}',
            'subsidiaries' => [
                'stop & shop',
                'stop and shop',
                'giant',
                'giant food',
                'food lion',
                'hannaford',
            ],
            'location_type' => 'store_id',
        ],
    ];

    /**
     * Known store search URL templates.
     * Maps domain -> template with placeholders:
     * - {query} = search query (required)
     * - {store_id} = store-specific location ID (optional)
     * - {zip} = user's zip code (optional)
     *
     * Stores are organized by category for maintainability.
     */
    protected const KNOWN_STORE_TEMPLATES = [
        // ============================================
        // GENERAL RETAILERS
        // ============================================
        'walmart.com' => [
            'template' => 'https://www.walmart.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false, // Generally national pricing
        ],
        'target.com' => [
            'template' => 'https://www.target.com/s?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'amazon.com' => [
            'template' => 'https://www.amazon.com/s?k={query}',
            'local_stock' => false,
            'local_price' => false,
        ],
        'costco.com' => [
            'template' => 'https://www.costco.com/CatalogSearch?keyword={query}',
            'local_stock' => true,
            'local_price' => true, // Warehouse-specific pricing
        ],
        'samsclub.com' => [
            'template' => 'https://www.samsclub.com/s/{query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'bjs.com' => [
            'template' => 'https://www.bjs.com/search/{query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'dollargeneral.com' => [
            'template' => 'https://www.dollargeneral.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'dollartree.com' => [
            'template' => 'https://www.dollartree.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'fivebelow.com' => [
            'template' => 'https://www.fivebelow.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'biglots.com' => [
            'template' => 'https://www.biglots.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // ELECTRONICS
        // ============================================
        'bestbuy.com' => [
            'template' => 'https://www.bestbuy.com/site/searchpage.jsp?st={query}',
            'local_stock' => true,
            'local_price' => false, // National pricing
        ],
        'newegg.com' => [
            'template' => 'https://www.newegg.com/p/pl?d={query}',
            'local_stock' => false,
            'local_price' => false,
        ],
        'bhphotovideo.com' => [
            'template' => 'https://www.bhphotovideo.com/c/search?q={query}',
            'local_stock' => false, // Primarily online
            'local_price' => false,
        ],
        'microcenter.com' => [
            'template' => 'https://www.microcenter.com/search/search_results.aspx?N=&Ntt={query}',
            'local_stock' => true,
            'local_price' => true, // Store-specific deals
        ],
        'gamestop.com' => [
            'template' => 'https://www.gamestop.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // GROCERY - NATIONAL CHAINS
        // ============================================
        'kroger.com' => [
            'template' => 'https://www.kroger.com/search?query={query}&searchType=default_search',
            'local_stock' => true,
            'local_price' => true,
        ],
        'albertsons.com' => [
            'template' => 'https://www.albertsons.com/shop/search-results.html?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'safeway.com' => [
            'template' => 'https://www.safeway.com/shop/search-results.html?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'publix.com' => [
            'template' => 'https://www.publix.com/search?query={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'heb.com' => [
            'template' => 'https://www.heb.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'meijer.com' => [
            'template' => 'https://www.meijer.com/search.html?searchTerm={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'aldi.us' => [
            'template' => 'https://www.aldi.us/search/?q={query}',
            'local_stock' => true,
            'local_price' => false, // Regional but consistent
        ],
        'lidl.com' => [
            'template' => 'https://www.lidl.com/search?query={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'traderjoes.com' => [
            'template' => 'https://www.traderjoes.com/home/search?q={query}',
            'local_stock' => true,
            'local_price' => false, // National pricing
        ],
        'wholefoodsmarket.com' => [
            'template' => 'https://www.wholefoodsmarket.com/search?text={query}',
            'local_stock' => true,
            'local_price' => true, // Amazon Prime pricing varies
        ],
        'sprouts.com' => [
            'template' => 'https://www.sprouts.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'foodlion.com' => [
            'template' => 'https://www.foodlion.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'giantfood.com' => [
            'template' => 'https://giantfood.com/search?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'stopandshop.com' => [
            'template' => 'https://stopandshop.com/search?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'wegmans.com' => [
            'template' => 'https://www.wegmans.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],

        // ============================================
        // HOME & HARDWARE
        // ============================================
        'homedepot.com' => [
            'template' => 'https://www.homedepot.com/s/{query}',
            'local_stock' => true,
            'local_price' => true, // Local store pricing
        ],
        'lowes.com' => [
            'template' => 'https://www.lowes.com/search?searchTerm={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'menards.com' => [
            'template' => 'https://www.menards.com/main/search.html?search={query}',
            'local_stock' => true,
            'local_price' => true, // Regional pricing
        ],
        'acehardware.com' => [
            'template' => 'https://www.acehardware.com/search?query={query}',
            'local_stock' => true,
            'local_price' => true, // Franchise pricing varies
        ],
        'truevalue.com' => [
            'template' => 'https://www.truevalue.com/search?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'harborfreight.com' => [
            'template' => 'https://www.harborfreight.com/catalogsearch/result?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // PHARMACY
        // ============================================
        'cvs.com' => [
            'template' => 'https://www.cvs.com/search?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false, // National pricing
        ],
        'walgreens.com' => [
            'template' => 'https://www.walgreens.com/search/results.jsp?Ntt={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'riteaid.com' => [
            'template' => 'https://www.riteaid.com/shop/catalogsearch/result?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // PET STORES
        // ============================================
        'petco.com' => [
            'template' => 'https://www.petco.com/shop/en/petcostore/search/{query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'petsmart.com' => [
            'template' => 'https://www.petsmart.com/search/?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'chewy.com' => [
            'template' => 'https://www.chewy.com/s?query={query}',
            'local_stock' => false, // Online only
            'local_price' => false,
        ],

        // ============================================
        // CLOTHING & DEPARTMENT STORES
        // ============================================
        'kohls.com' => [
            'template' => 'https://www.kohls.com/search.jsp?search={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'jcpenney.com' => [
            'template' => 'https://www.jcpenney.com/s/{query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'macys.com' => [
            'template' => 'https://www.macys.com/shop/featured/{query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'nordstrom.com' => [
            'template' => 'https://www.nordstrom.com/sr?keyword={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'tjmaxx.tjx.com' => [
            'template' => 'https://tjmaxx.tjx.com/store/jump/search?q={query}',
            'local_stock' => true, // Very store-specific
            'local_price' => true,
        ],
        'rossstores.com' => [
            'template' => 'https://www.rossstores.com/search?q={query}',
            'local_stock' => true,
            'local_price' => true,
        ],
        'oldnavy.gap.com' => [
            'template' => 'https://oldnavy.gap.com/browse/search.do?searchText={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // OFFICE SUPPLIES
        // ============================================
        'staples.com' => [
            'template' => 'https://www.staples.com/search?query={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'officedepot.com' => [
            'template' => 'https://www.officedepot.com/catalog/search.do?Ntt={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // BEAUTY
        // ============================================
        'ulta.com' => [
            'template' => 'https://www.ulta.com/search?query={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'sephora.com' => [
            'template' => 'https://www.sephora.com/search?keyword={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'sallybeauty.com' => [
            'template' => 'https://www.sallybeauty.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // SPORTING GOODS
        // ============================================
        'dickssportinggoods.com' => [
            'template' => 'https://www.dickssportinggoods.com/search/SearchDisplay?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'academy.com' => [
            'template' => 'https://www.academy.com/search?query={query}',
            'local_stock' => true,
            'local_price' => true, // Regional
        ],
        'rei.com' => [
            'template' => 'https://www.rei.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'cabelas.com' => [
            'template' => 'https://www.cabelas.com/shop/en/SearchDisplay?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'basspro.com' => [
            'template' => 'https://www.basspro.com/shop/en/SearchDisplay?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // AUTO PARTS
        // ============================================
        'autozone.com' => [
            'template' => 'https://www.autozone.com/searchresult?searchText={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'oreillyauto.com' => [
            'template' => 'https://www.oreillyauto.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'advanceautoparts.com' => [
            'template' => 'https://shop.advanceautoparts.com/web/SearchResults?searchTerm={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'napaonline.com' => [
            'template' => 'https://www.napaonline.com/en/search?text={query}',
            'local_stock' => true,
            'local_price' => true, // Franchise pricing
        ],

        // ============================================
        // CRAFT & HOBBY
        // ============================================
        'michaels.com' => [
            'template' => 'https://www.michaels.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'joann.com' => [
            'template' => 'https://www.joann.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'hobbylobby.com' => [
            'template' => 'https://www.hobbylobby.com/search?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],

        // ============================================
        // FURNITURE & HOME GOODS
        // ============================================
        'ikea.com' => [
            'template' => 'https://www.ikea.com/us/en/search/?q={query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'bedbathandbeyond.com' => [
            'template' => 'https://www.bedbathandbeyond.com/store/s/{query}',
            'local_stock' => true,
            'local_price' => false,
        ],
        'wayfair.com' => [
            'template' => 'https://www.wayfair.com/keyword.html?keyword={query}',
            'local_stock' => false, // Online only
            'local_price' => false,
        ],
    ];

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
     * Detect the search URL template for a store website using tiered approach.
     *
     * Tiers:
     * 1. Known Templates - Instant lookup from pre-configured database (free)
     * 2. Common Patterns - Test common URL patterns with validation (cheap)
     * 3. AI Analysis - Use AI to analyze page structure (medium cost)
     * 4. Firecrawl Agent - Interactive detection (expensive, requires user opt-in)
     *
     * @param string $websiteUrl The store's website URL (homepage)
     * @param bool $useAgent Whether to use Firecrawl Agent (Tier 4) if other tiers fail
     * @return array{success: bool, template?: string, validated?: bool, tier?: string, agent_available?: bool, local_stock?: bool, local_price?: bool, error?: string}
     */
    public function detectSearchUrlTemplate(string $websiteUrl, bool $useAgent = false): array
    {
        // Ensure URL has protocol
        if (!str_starts_with($websiteUrl, 'http')) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        $domain = $this->extractDomain($websiteUrl);

        Log::info('StoreAutoConfigService: Starting tiered search URL detection', [
            'user_id' => $this->userId,
            'website' => $websiteUrl,
            'domain' => $domain,
        ]);

        // ============================================
        // TIER 1: Known Templates (instant, free)
        // ============================================
        $tier1Result = $this->tier1KnownTemplates($domain);
        if ($tier1Result['success']) {
            Log::info('StoreAutoConfigService: Tier 1 success - known template found', [
                'domain' => $domain,
                'template' => $tier1Result['template'],
            ]);
            return array_merge($tier1Result, ['tier' => 'known_template']);
        }

        // ============================================
        // TIER 2: Common Patterns with Validation (fast, cheap)
        // ============================================
        $tier2Result = $this->tier2CommonPatterns($websiteUrl);
        if ($tier2Result['success']) {
            Log::info('StoreAutoConfigService: Tier 2 success - common pattern validated', [
                'domain' => $domain,
                'template' => $tier2Result['template'],
            ]);
            return array_merge($tier2Result, ['tier' => 'common_pattern']);
        }

        // ============================================
        // TIER 3: AI Analysis (medium cost)
        // Requires Firecrawl API for scraping
        // ============================================
        if ($this->isAvailable()) {
            $tier3Result = $this->tier3AIAnalysis($websiteUrl);
            if ($tier3Result['success']) {
                Log::info('StoreAutoConfigService: Tier 3 success - AI analysis found pattern', [
                    'domain' => $domain,
                    'template' => $tier3Result['template'],
                ]);
                return array_merge($tier3Result, ['tier' => 'ai_analysis']);
            }
        }

        // ============================================
        // TIER 4: Firecrawl Agent (expensive, requires opt-in)
        // ============================================
        if ($useAgent && $this->isAvailable()) {
            $tier4Result = $this->tier4FirecrawlAgent($websiteUrl);
            if ($tier4Result['success']) {
                Log::info('StoreAutoConfigService: Tier 4 success - Agent found pattern', [
                    'domain' => $domain,
                    'template' => $tier4Result['template'],
                ]);
                return array_merge($tier4Result, ['tier' => 'firecrawl_agent']);
            }
        }

        // All tiers failed
        Log::warning('StoreAutoConfigService: All detection tiers failed', [
            'domain' => $domain,
            'website' => $websiteUrl,
        ]);

        return [
            'success' => false,
            'error' => 'Could not detect search URL pattern using any method',
            'agent_available' => $this->isAvailable() && !$useAgent,
            'agent_cost_estimate' => '~50-100 API credits',
        ];
    }

    /**
     * Extract the main domain from a URL.
     *
     * @param string $url
     * @return string
     */
    protected function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        // Remove www. prefix
        return preg_replace('/^www\./', '', strtolower($host));
    }

    /**
     * Tier 1: Check known templates database.
     *
     * @param string $domain
     * @return array
     */
    protected function tier1KnownTemplates(string $domain): array
    {
        // Direct domain match
        if (isset(self::KNOWN_STORE_TEMPLATES[$domain])) {
            $config = self::KNOWN_STORE_TEMPLATES[$domain];
            return [
                'success' => true,
                'template' => $config['template'],
                'validated' => true, // Pre-configured templates are verified
                'local_stock' => $config['local_stock'] ?? false,
                'local_price' => $config['local_price'] ?? false,
            ];
        }

        // Try partial domain match (e.g., "walmart" matches "walmart.com")
        foreach (self::KNOWN_STORE_TEMPLATES as $knownDomain => $config) {
            if (str_contains($domain, str_replace('.com', '', $knownDomain)) ||
                str_contains($knownDomain, str_replace('.com', '', $domain))) {
                return [
                    'success' => true,
                    'template' => $config['template'],
                    'validated' => true,
                    'local_stock' => $config['local_stock'] ?? false,
                    'local_price' => $config['local_price'] ?? false,
                ];
            }
        }

        return ['success' => false];
    }

    /**
     * Tier 2: Try common URL patterns with HTTP validation.
     *
     * @param string $websiteUrl
     * @return array
     */
    protected function tier2CommonPatterns(string $websiteUrl): array
    {
        $parsed = parse_url($websiteUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // Expanded list of common patterns
        $patterns = [
            // Standard search patterns
            $base . '/search?q={query}',
            $base . '/search?query={query}',
            $base . '/search?searchTerm={query}',
            $base . '/search?keyword={query}',
            $base . '/search?text={query}',
            $base . '/search?Ntt={query}',
            $base . '/search/{query}',
            $base . '/s?k={query}',
            $base . '/s?q={query}',
            $base . '/s/{query}',
            // E-commerce platform patterns
            $base . '/catalogsearch/result/?q={query}', // Magento
            $base . '/catalogsearch/result?q={query}',
            $base . '/shop/search?q={query}',
            $base . '/shop/search-results.html?q={query}',
            $base . '/products?search={query}',
            $base . '/products?q={query}',
            // Search display patterns
            $base . '/searchresult?searchText={query}',
            $base . '/search/results.jsp?Ntt={query}',
            $base . '/site/searchpage.jsp?st={query}',
            $base . '/SearchDisplay?searchTerm={query}',
            // Additional patterns
            $base . '/search.html?searchTerm={query}',
            $base . '/catalog/search.do?Ntt={query}',
        ];

        foreach ($patterns as $pattern) {
            $testUrl = str_replace('{query}', 'test', $pattern);
            if ($this->urlReturnsSearchResults($testUrl)) {
                // Validate with a real product search
                $isValid = $this->validateSearchUrl($pattern, 'laptop');

                Log::info('StoreAutoConfigService: Tier 2 pattern found', [
                    'pattern' => $pattern,
                    'validated' => $isValid,
                ]);

                return [
                    'success' => true,
                    'template' => $pattern,
                    'validated' => $isValid,
                ];
            }
        }

        return ['success' => false];
    }

    /**
     * Tier 3: Use AI to analyze page structure.
     *
     * @param string $websiteUrl
     * @return array
     */
    protected function tier3AIAnalysis(string $websiteUrl): array
    {
        try {
            // Scrape the homepage
            $homepageContent = $this->scrapeWebsite($websiteUrl);
            if ($homepageContent === null) {
                return ['success' => false, 'error' => 'Failed to scrape website'];
            }

            // Use AI to analyze the page and detect search pattern
            $searchPattern = $this->analyzeSearchPattern($websiteUrl, $homepageContent);
            if ($searchPattern === null) {
                return ['success' => false];
            }

            // Validate the AI-detected template
            $isValid = $this->validateSearchUrl($searchPattern, 'test product');

            return [
                'success' => true,
                'template' => $searchPattern,
                'validated' => $isValid,
            ];

        } catch (\Exception $e) {
            Log::warning('StoreAutoConfigService: Tier 3 AI analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tier 4: Use Firecrawl Agent for interactive detection.
     * This is expensive and should only be used as a last resort with user opt-in.
     *
     * @param string $websiteUrl
     * @return array
     */
    protected function tier4FirecrawlAgent(string $websiteUrl): array
    {
        try {
            // Use Firecrawl's action/agent API to interact with the page
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->firecrawlApiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::FIRECRAWL_BASE_URL . '/scrape', [
                    'url' => $websiteUrl,
                    'formats' => ['markdown', 'html', 'links'],
                    'actions' => [
                        // Try to find and interact with search
                        [
                            'type' => 'click',
                            'selector' => 'input[type="search"], input[name="q"], input[name="query"], input[placeholder*="search" i], .search-input, #search',
                        ],
                        [
                            'type' => 'write',
                            'text' => 'test product',
                        ],
                        [
                            'type' => 'press',
                            'key' => 'Enter',
                        ],
                        [
                            'type' => 'wait',
                            'milliseconds' => 3000,
                        ],
                    ],
                    'waitFor' => 3000,
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'Firecrawl agent request failed'];
            }

            $data = $response->json('data') ?? $response->json();
            $finalUrl = $data['url'] ?? $data['metadata']['finalUrl'] ?? null;

            if ($finalUrl && str_contains($finalUrl, 'test') || str_contains($finalUrl, 'search')) {
                // Extract template from the final URL
                $template = $this->extractTemplateFromSearchResultUrl($finalUrl, 'test product');
                if ($template) {
                    $isValid = $this->validateSearchUrl($template, 'laptop');
                    return [
                        'success' => true,
                        'template' => $template,
                        'validated' => $isValid,
                    ];
                }
            }

            return ['success' => false, 'error' => 'Could not determine search URL from agent interaction'];

        } catch (\Exception $e) {
            Log::error('StoreAutoConfigService: Tier 4 agent failed', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract a template from a search result URL by replacing the query with a placeholder.
     *
     * @param string $url The search result URL
     * @param string $query The query that was searched
     * @return string|null
     */
    protected function extractTemplateFromSearchResultUrl(string $url, string $query): ?string
    {
        // URL encode the query for matching
        $encodedQuery = urlencode($query);

        // Try to find and replace the query in the URL
        if (str_contains($url, $encodedQuery)) {
            return str_replace($encodedQuery, '{query}', $url);
        }

        if (str_contains($url, $query)) {
            return str_replace($query, '{query}', $url);
        }

        // Try replacing common URL-encoded spaces
        $plusQuery = str_replace(' ', '+', $query);
        if (str_contains($url, $plusQuery)) {
            return str_replace($plusQuery, '{query}', $url);
        }

        return null;
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

    /**
     * Check if a store name matches any known subsidiary.
     *
     * @param string $storeName The store name to check
     * @return array|null Returns chain info if match found, null otherwise
     */
    public function detectSubsidiary(string $storeName): ?array
    {
        $normalizedName = strtolower(trim($storeName));

        foreach (self::KNOWN_CHAINS as $chainKey => $chainInfo) {
            foreach ($chainInfo['subsidiaries'] as $subsidiary) {
                // Check if the store name contains the subsidiary name
                if (str_contains($normalizedName, strtolower($subsidiary))) {
                    return [
                        'chain' => $chainKey,
                        'parent_domain' => $chainInfo['domain'],
                        'parent_template' => $chainInfo['template'],
                        'location_type' => $chainInfo['location_type'],
                        'matched_subsidiary' => $subsidiary,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Find or create a parent store for a subsidiary.
     *
     * @param string $storeName The subsidiary store name
     * @return Store|null The parent store if found/created
     */
    public function findOrCreateParentStore(string $storeName): ?Store
    {
        $subsidiaryInfo = $this->detectSubsidiary($storeName);
        if (!$subsidiaryInfo) {
            return null;
        }

        // Try to find existing parent store by domain
        $parentStore = Store::findByDomain($subsidiaryInfo['parent_domain']);

        if ($parentStore) {
            // Update template if not set
            if (empty($parentStore->search_url_template)) {
                $parentStore->update([
                    'search_url_template' => $subsidiaryInfo['parent_template'],
                ]);
            }
            return $parentStore;
        }

        // Create the parent store
        $chainName = ucfirst($subsidiaryInfo['chain']);
        $parentStore = Store::create([
            'name' => $chainName,
            'domain' => $subsidiaryInfo['parent_domain'],
            'search_url_template' => $subsidiaryInfo['parent_template'],
            'is_default' => false,
            'is_local' => false,
            'is_active' => true,
            'category' => Store::CATEGORY_GROCERY,
            'default_priority' => 60,
        ]);

        Log::info('StoreAutoConfigService: Created parent store for subsidiary', [
            'parent_store' => $parentStore->name,
            'subsidiary' => $storeName,
            'chain' => $subsidiaryInfo['chain'],
        ]);

        return $parentStore;
    }

    /**
     * Configure a store as a subsidiary of a known chain.
     *
     * @param Store $store The store to configure
     * @return array{success: bool, parent_store?: Store, message?: string}
     */
    public function configureAsSubsidiary(Store $store): array
    {
        $subsidiaryInfo = $this->detectSubsidiary($store->name);
        if (!$subsidiaryInfo) {
            return [
                'success' => false,
                'message' => 'Store is not a recognized subsidiary of any known chain',
            ];
        }

        // Find or create the parent store
        $parentStore = $this->findOrCreateParentStore($store->name);
        if (!$parentStore) {
            return [
                'success' => false,
                'message' => 'Could not find or create parent store',
            ];
        }

        // Link the store to its parent
        $store->update([
            'parent_store_id' => $parentStore->id,
        ]);

        Log::info('StoreAutoConfigService: Configured store as subsidiary', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'parent_store_id' => $parentStore->id,
            'parent_store_name' => $parentStore->name,
        ]);

        return [
            'success' => true,
            'parent_store' => $parentStore,
            'message' => "Linked to {$parentStore->name} for search functionality",
        ];
    }

    /**
     * Get information about a known chain by domain.
     *
     * @param string $domain The domain to look up
     * @return array|null Chain info or null if not known
     */
    public function getKnownChainInfo(string $domain): ?array
    {
        $normalizedDomain = preg_replace('/^www\./', '', strtolower($domain));

        foreach (self::KNOWN_CHAINS as $chainKey => $chainInfo) {
            if (str_contains($normalizedDomain, $chainInfo['domain'])) {
                return array_merge(['chain' => $chainKey], $chainInfo);
            }
        }

        return null;
    }

    /**
     * Get all known chains with their subsidiaries.
     *
     * @return array
     */
    public static function getKnownChains(): array
    {
        return self::KNOWN_CHAINS;
    }
}
