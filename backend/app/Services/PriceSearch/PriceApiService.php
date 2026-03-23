<?php

namespace App\Services\PriceSearch;

use App\Services\Crawler\CrawlAIService;
use App\Services\LLM\LLMOrchestrator;
use App\Services\PriceSearch\Providers\BestBuyApiProvider;
use App\Services\PriceSearch\Providers\CrawlAIProvider;
use App\Services\PriceSearch\Providers\KrogerApiProvider;
use App\Services\PriceSearch\Providers\PriceProviderInterface;
use App\Services\PriceSearch\Providers\SerpApiProvider;
use App\Services\PriceSearch\Providers\WalmartApiProvider;
use App\Services\SettingService;
use Illuminate\Support\Facades\Log;

class PriceApiService
{
    /** @var PriceProviderInterface[] */
    private array $providers = [];

    public function __construct(
        private readonly SettingService $settingService,
        private readonly CrawlAIService $crawlAIService,
        private readonly LLMOrchestrator $llmOrchestrator,
    ) {
        $this->registerProviders();
    }

    /**
     * Search across all configured providers.
     *
     * @param string $query The product search query
     * @param array $options Search options (location, limit, etc.)
     * @return array Merged results from all available providers
     */
    public function search(string $query, array $options = []): array
    {
        $results = [];

        $available = [];
        $skipped = [];

        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                $skipped[] = $provider->getName();

                continue;
            }

            $available[] = $provider->getName();

            try {
                $providerResults = $provider->search($query, $options);
                foreach ($providerResults as &$result) {
                    $result['provider'] = $provider->getName();
                }
                unset($result);

                $results = array_merge($results, $providerResults);
            } catch (\Exception $e) {
                Log::warning('PriceApiService: provider search failed', [
                    'provider' => $provider->getName(),
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PriceApiService: search complete', [
            'query' => $query,
            'providers_used' => $available,
            'providers_skipped' => $skipped,
            'total_results' => count($results),
        ]);

        return $results;
    }

    /**
     * Get list of available providers based on API key configuration.
     *
     * @return array Array of provider info: ['name' => string, 'available' => bool]
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->providers as $provider) {
            $available[] = [
                'name' => $provider->getName(),
                'available' => $provider->isAvailable(),
            ];
        }

        return $available;
    }

    /**
     * Register all known price search providers.
     */
    private function registerProviders(): void
    {
        $serpApiKey = $this->settingService->get('price_search', 'serpapi_key');
        $walmartApiKey = $this->settingService->get('price_search', 'walmart_api_key');

        $this->providers = [
            app(KrogerApiProvider::class),
            new WalmartApiProvider($walmartApiKey),
            app(BestBuyApiProvider::class),
            new SerpApiProvider($serpApiKey),
            new CrawlAIProvider($this->crawlAIService, $this->llmOrchestrator),
        ];
    }

    /**
     * Fetch a product image URL for a given query using the first available provider.
     *
     * @param string $query The product search query
     * @return string|null The image URL or null if not found
     */
    public function fetchProductImage(string $query): ?string
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            try {
                $results = $provider->search($query, ['limit' => 1]);

                if (!empty($results[0]['image_url'])) {
                    return $results[0]['image_url'];
                }
            } catch (\Exception $e) {
                Log::debug('PriceApiService: image fetch failed', [
                    'provider' => $provider->getName(),
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Add a custom provider at runtime.
     */
    public function addProvider(PriceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }
}
