<?php

namespace App\Services\PriceApi;

use App\Models\Setting;
use App\Services\PriceApi\Providers\RainforestProvider;
use App\Services\PriceApi\Providers\SerpApiProvider;
use Illuminate\Support\Facades\Cache;

class PriceApiService
{
    protected ?int $userId;
    protected ?string $provider;
    protected ?PriceProviderInterface $providerInstance;
    protected array $suppressedVendors = [];
    protected ?string $homeZipCode = null;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->loadConfiguration();
    }

    /**
     * Create an instance for a specific user.
     */
    public static function forUser(?int $userId): self
    {
        return new self($userId);
    }

    /**
     * Load configuration from settings.
     */
    protected function loadConfiguration(): void
    {
        $this->provider = Setting::get(Setting::PRICE_API_PROVIDER, $this->userId);

        $apiKey = match ($this->provider) {
            'serpapi' => Setting::get(Setting::SERPAPI_KEY, $this->userId) 
                ?? config('services.serpapi.api_key'),
            'rainforest' => Setting::get(Setting::RAINFOREST_KEY, $this->userId) 
                ?? config('services.rainforest.api_key'),
            default => null,
        };

        $this->providerInstance = match ($this->provider) {
            'serpapi' => new SerpApiProvider($apiKey),
            'rainforest' => new RainforestProvider($apiKey),
            default => null,
        };

        // Load suppressed vendors
        $suppressedJson = Setting::get(Setting::SUPPRESSED_VENDORS, $this->userId);
        $this->suppressedVendors = $suppressedJson ? json_decode($suppressedJson, true) ?: [] : [];

        // Load home zip code for local searches
        $this->homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $this->userId);
    }

    /**
     * Get the suppressed vendors list.
     */
    public function getSuppressedVendors(): array
    {
        return $this->suppressedVendors;
    }

    /**
     * Get the home zip code.
     */
    public function getHomeZipCode(): ?string
    {
        return $this->homeZipCode;
    }

    /**
     * Filter results to remove suppressed vendors.
     */
    protected function filterSuppressedVendors(array $results): array
    {
        if (empty($this->suppressedVendors)) {
            return $results;
        }

        $suppressedLower = array_map('strtolower', $this->suppressedVendors);

        return array_values(array_filter($results, function ($result) use ($suppressedLower) {
            $retailer = strtolower($result['retailer'] ?? '');
            foreach ($suppressedLower as $suppressed) {
                // Check if retailer contains the suppressed term
                if (str_contains($retailer, $suppressed) || str_contains($suppressed, $retailer)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Check if price API is available.
     */
    public function isAvailable(): bool
    {
        return $this->providerInstance?->isConfigured() ?? false;
    }

    /**
     * Get the current provider.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Search for products.
     *
     * @param string $query The search query
     * @param string $type The type of search (product, etc.)
     * @param bool $localOnly If true, only return results from local stores
     * @param string|null $zipCode Override zip code for location-based search
     */
    public function search(string $query, string $type = 'product', bool $localOnly = false, ?string $zipCode = null): PriceSearchResult
    {
        if (!$this->isAvailable()) {
            return new PriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: 'Price API is not configured.',
            );
        }

        try {
            $options = ['type' => $type];

            // Add location for local-only searches
            if ($localOnly) {
                $location = $zipCode ?? $this->homeZipCode;
                if ($location) {
                    $options['location'] = $location;
                    $options['local_only'] = true;
                }
            }

            $results = $this->providerInstance->search($query, $options);

            // Filter out suppressed vendors
            $results = $this->filterSuppressedVendors($results);

            $prices = array_filter(array_column($results, 'price'));
            
            return new PriceSearchResult(
                query: $query,
                results: $results,
                lowestPrice: !empty($prices) ? min($prices) : null,
                highestPrice: !empty($prices) ? max($prices) : null,
                searchedAt: now(),
                error: null,
            );
        } catch (\Exception $e) {
            return new PriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Search with caching.
     */
    public function searchWithCache(string $query, string $type = 'product', bool $localOnly = false, int $ttl = 900): PriceSearchResult
    {
        $cacheKey = "price_search:{$this->userId}:" . md5($query . $type . ($localOnly ? ':local' : ''));

        return Cache::remember($cacheKey, $ttl, function () use ($query, $type, $localOnly) {
            return $this->search($query, $type, $localOnly);
        });
    }

    /**
     * Test the API connection.
     */
    public function testConnection(): bool
    {
        return $this->providerInstance?->testConnection() ?? false;
    }
}

class PriceSearchResult implements \JsonSerializable
{
    public function __construct(
        public string $query,
        public array $results,
        public ?float $lowestPrice,
        public ?float $highestPrice,
        public \DateTimeInterface $searchedAt,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'results' => $this->results,
            'lowest_price' => $this->lowestPrice,
            'highest_price' => $this->highestPrice,
            'searched_at' => $this->searchedAt->format(\DateTimeInterface::ATOM),
            'error' => $this->error,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
