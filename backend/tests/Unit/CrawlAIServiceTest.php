<?php

use App\Services\Crawler\CrawlAIService;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;

describe('CrawlAIService', function () {
    describe('isAvailable', function () {
        it('returns false when disabled', function () {
            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(false);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            expect($service->isAvailable())->toBeFalse();
        });

        it('returns false when health check fails', function () {
            Http::fake([
                'crawl4ai:11235/health' => Http::response('', 500),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            expect($service->isAvailable())->toBeFalse();
        });

        it('returns true when enabled and healthy', function () {
            Http::fake([
                'crawl4ai:11235/health' => Http::response(['status' => 'ok'], 200),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            expect($service->isAvailable())->toBeTrue();
        });
    });

    describe('scrapeUrl', function () {
        it('returns empty array when disabled', function () {
            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(false);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            expect($service->scrapeUrl('https://example.com'))->toBeEmpty();
        });

        it('sends correct payload and returns structured data', function () {
            Http::fake([
                'crawl4ai:11235/*' => Http::response([
                    'result' => [
                        'markdown' => '# Product Page',
                        'html' => '<h1>Product Page</h1>',
                        'metadata' => ['title' => 'Test'],
                    ],
                ], 200),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);
            $result = $service->scrapeUrl('https://example.com/product');

            expect($result)->toHaveKey('success', true);
            expect($result)->toHaveKey('content', '# Product Page');
            expect($result)->toHaveKey('html', '<h1>Product Page</h1>');
        });

        it('returns empty array on HTTP failure', function () {
            Http::fake([
                'crawl4ai:11235/*' => Http::response('', 500),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            expect($service->scrapeUrl('https://example.com'))->toBeEmpty();
        });
    });

    describe('scrapeWithCssExtraction', function () {
        it('sends CSS selector in payload', function () {
            Http::fake([
                'crawl4ai:11235/*' => Http::response([
                    'result' => [
                        'markdown' => 'Price: $4.99',
                        'html' => '<span class="price">$4.99</span>',
                        'extracted_content' => ['price' => '$4.99'],
                        'metadata' => [],
                    ],
                ], 200),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);

            $selectors = [
                'container_selector' => '.product-list',
                'price_selector' => '.product-price',
                'name_selector' => '.product-title',
            ];

            $result = $service->scrapeWithCssExtraction('https://example.com/search?q=milk', $selectors);

            expect($result)->toHaveKey('success', true);
            expect($result)->toHaveKey('selectors', $selectors);
            expect($result)->toHaveKey('extracted_content');

            Http::assertSent(function ($request) {
                return $request->url() === 'http://crawl4ai:11235/crawl'
                    && $request['css_selector'] === '.product-list';
            });
        });
    });

    describe('scrapeWithLlmExtraction', function () {
        it('sends extraction config in payload', function () {
            Http::fake([
                'crawl4ai:11235/*' => Http::response([
                    'result' => [
                        'markdown' => 'Extracted prices',
                        'extracted_content' => [['name' => 'Milk', 'price' => 4.99]],
                        'metadata' => [],
                    ],
                ], 200),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn(null);

            $service = new CrawlAIService($settingService);
            $result = $service->scrapeWithLlmExtraction(
                'https://example.com/search?q=milk',
                'Extract product prices from this page'
            );

            expect($result)->toHaveKey('success', true);
            expect($result)->toHaveKey('extracted_content');

            Http::assertSent(function ($request) {
                return $request->url() === 'http://crawl4ai:11235/crawl'
                    && ($request['extraction_config']['type'] ?? '') === 'llm'
                    && str_contains($request['extraction_config']['instruction'] ?? '', 'Extract product prices');
            });
        });
    });

    describe('authentication', function () {
        it('includes bearer token when configured', function () {
            Http::fake([
                'crawl4ai:11235/*' => Http::response([
                    'result' => ['markdown' => 'content', 'html' => '', 'metadata' => []],
                ], 200),
            ]);

            $settingService = Mockery::mock(SettingService::class);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_enabled')
                ->andReturn(true);
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_base_url')
                ->andReturn('http://crawl4ai:11235');
            $settingService->shouldReceive('get')
                ->with('price_search', 'crawl4ai_api_token')
                ->andReturn('test-secret-token');

            $service = new CrawlAIService($settingService);
            $service->scrapeUrl('https://example.com');

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization', 'Bearer test-secret-token');
            });
        });
    });
});
