<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Crawler\CrawlAIService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class PriceSearchSettingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    /**
     * Test a price search provider's API key / connection.
     */
    public function testProvider(string $provider): JsonResponse
    {
        $testers = [
            'serpapi' => fn () => $this->testSerpApi(),
            'kroger' => fn () => $this->testKroger(),
            'walmart' => fn () => $this->testWalmart(),
            'bestbuy' => fn () => $this->testBestBuy(),
            'crawl4ai' => fn () => $this->testCrawlAI(),
            'firecrawl' => fn () => $this->testFirecrawl(),
            'google_places' => fn () => $this->testGooglePlaces(),
        ];

        if (!isset($testers[$provider])) {
            return $this->errorResponse("Unknown provider: {$provider}", 422);
        }

        try {
            $result = $testers[$provider]();

            if (!$result['success']) {
                return $this->dataResponse([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            return $this->dataResponse([
                'success' => true,
                'message' => $result['message'] ?? 'Connection successful',
                'details' => $result['details'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->dataResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function testSerpApi(): array
    {
        $apiKey = $this->settingService->get('price_search', 'serpapi_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $response = Http::timeout(15)->get('https://serpapi.com/search', [
            'engine' => 'google_shopping',
            'q' => 'test',
            'api_key' => $apiKey,
            'num' => 1,
        ]);

        if (!$response->successful()) {
            $body = $response->json();

            return [
                'success' => false,
                'error' => $body['error'] ?? "HTTP {$response->status()}: {$response->body()}",
            ];
        }

        $data = $response->json();
        $count = count($data['shopping_results'] ?? []);

        return [
            'success' => true,
            'message' => "Connected — {$count} result(s) returned",
            'details' => ['results_count' => $count],
        ];
    }

    private function testKroger(): array
    {
        $clientId = $this->settingService->get('price_search', 'kroger_client_id');
        $clientSecret = $this->settingService->get('price_search', 'kroger_client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'Client ID and/or Client Secret not configured'];
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(10)
            ->post('https://api.kroger.com/v1/connect/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'product.compact',
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "OAuth token request failed (HTTP {$response->status()})",
            ];
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            return ['success' => false, 'error' => 'Token response missing access_token'];
        }

        return [
            'success' => true,
            'message' => 'OAuth credentials valid — token obtained',
            'details' => ['token_type' => $data['token_type'] ?? 'bearer'],
        ];
    }

    private function testWalmart(): array
    {
        $apiKey = $this->settingService->get('price_search', 'walmart_api_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $apiKey,
                'WM_CONSUMER.ID' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get('https://developer.api.walmart.com/api-proxy/service/affil/product/v2/search', [
                'query' => 'test',
                'numItems' => 1,
                'format' => 'json',
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "API request failed (HTTP {$response->status()})",
            ];
        }

        $data = $response->json();
        $count = count($data['items'] ?? []);

        return [
            'success' => true,
            'message' => "Connected — {$count} result(s) returned",
            'details' => ['total_results' => $data['totalResults'] ?? $count],
        ];
    }

    private function testBestBuy(): array
    {
        $apiKey = $this->settingService->get('price_search', 'bestbuy_api_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $response = Http::timeout(15)
            ->get('https://api.bestbuy.com/v1/products(search=test)', [
                'apiKey' => $apiKey,
                'format' => 'json',
                'pageSize' => 1,
                'show' => 'sku,name',
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "API request failed (HTTP {$response->status()})",
            ];
        }

        $data = $response->json();
        $total = $data['total'] ?? count($data['products'] ?? []);

        return [
            'success' => true,
            'message' => "Connected — {$total} product(s) available",
            'details' => ['total_products' => $total],
        ];
    }

    private function testCrawlAI(): array
    {
        $enabled = (bool) $this->settingService->get('price_search', 'crawl4ai_enabled');
        if (!$enabled) {
            return ['success' => false, 'error' => 'Crawl4AI is not enabled'];
        }

        $baseUrl = rtrim(
            $this->settingService->get('price_search', 'crawl4ai_base_url') ?? 'http://127.0.0.1:11235',
            '/'
        );
        $token = $this->settingService->get('price_search', 'crawl4ai_api_token');

        $http = Http::timeout(10);
        if ($token) {
            $http = $http->withHeaders(['Authorization' => "Bearer {$token}"]);
        }

        $response = $http->get("{$baseUrl}/health");

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "Health check failed (HTTP {$response->status()})",
            ];
        }

        return [
            'success' => true,
            'message' => 'Crawl4AI service is healthy',
            'details' => ['base_url' => $baseUrl],
        ];
    }

    private function testFirecrawl(): array
    {
        $apiKey = $this->settingService->get('price_search', 'firecrawl_key');
        $enabled = (bool) $this->settingService->get('price_search', 'firecrawl_enabled');

        if (!$enabled) {
            return ['success' => false, 'error' => 'Firecrawl is not enabled'];
        }

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])
            ->get('https://api.firecrawl.dev/v1/crawl');

        // A 200 or 401 both tell us something — 401 means bad key, anything else is connectivity
        if ($response->status() === 401 || $response->status() === 403) {
            return ['success' => false, 'error' => 'Invalid API key (authentication failed)'];
        }

        // Firecrawl returns various success codes for valid keys
        if ($response->successful() || $response->status() === 404) {
            // 404 on GET /crawl is expected — it means auth passed but the endpoint needs POST
            return [
                'success' => true,
                'message' => 'API key is valid',
            ];
        }

        return [
            'success' => false,
            'error' => "API request failed (HTTP {$response->status()})",
        ];
    }

    private function testGooglePlaces(): array
    {
        $apiKey = $this->settingService->get('price_search', 'google_places_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        // Use the Place Search (Nearby) endpoint with minimal params to validate the key
        $response = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                'key' => $apiKey,
                'location' => '0,0',
                'radius' => 1,
                'type' => 'grocery_or_supermarket',
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "API request failed (HTTP {$response->status()})",
            ];
        }

        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN';

        if ($status === 'REQUEST_DENIED') {
            $errorMsg = $data['error_message'] ?? 'Request denied';

            return ['success' => false, 'error' => "Invalid key: {$errorMsg}"];
        }

        // ZERO_RESULTS and OK both indicate a valid key
        return [
            'success' => true,
            'message' => 'API key is valid',
            'details' => ['api_status' => $status],
        ];
    }
}
