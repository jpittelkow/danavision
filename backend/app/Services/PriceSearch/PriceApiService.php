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

        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

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
     * Add a custom provider at runtime.
     */
    public function addProvider(PriceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }
}
