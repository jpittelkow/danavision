<?php

use App\Services\Crawler\Crawl4AIService;
use Illuminate\Support\Facades\Http;

test('crawl4ai service can check availability', function () {
    // Mock the health endpoint
    Http::fake([
        '127.0.0.1:5000/health' => Http::response([
            'status' => 'ok',
            'service' => 'crawl4ai',
        ], 200),
    ]);

    $service = new Crawl4AIService();
    
    expect($service->isAvailable())->toBeTrue();
});

test('crawl4ai service returns unavailable when service is down', function () {
    // Mock a failed health check
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(null, 500),
    ]);

    $service = new Crawl4AIService();
    
    expect($service->isAvailable())->toBeFalse();
});

test('crawl4ai service can scrape single url', function () {
    Http::fake([
        '127.0.0.1:5000/scrape' => Http::response([
            'success' => true,
            'markdown' => '# Product Page\n\nPrice: $29.99\n\nIn Stock',
            'html' => '<h1>Product Page</h1>',
            'title' => 'Product Page',
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $result = $service->scrapeUrl('https://example.com/product');

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
    expect($result['markdown'])->toContain('$29.99');
});

test('crawl4ai service handles scrape failure', function () {
    Http::fake([
        '127.0.0.1:5000/scrape' => Http::response([
            'success' => false,
            'error' => 'Page timeout',
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $result = $service->scrapeUrl('https://example.com/slow-page');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Page timeout');
});

test('crawl4ai service can scrape multiple urls', function () {
    Http::fake([
        '127.0.0.1:5000/batch' => Http::response([
            'results' => [
                [
                    'success' => true,
                    'markdown' => '# Amazon\n\nPrice: $25.99',
                    'title' => 'Amazon Product',
                ],
                [
                    'success' => true,
                    'markdown' => '# Walmart\n\nPrice: $24.99',
                    'title' => 'Walmart Product',
                ],
            ],
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $results = $service->scrapeUrls([
        'https://amazon.com/product',
        'https://walmart.com/product',
    ]);

    expect($results)->toHaveCount(2);
    expect($results[0]['success'])->toBeTrue();
    expect($results[1]['success'])->toBeTrue();
});

test('crawl4ai service throws exception on http failure', function () {
    Http::fake([
        '127.0.0.1:5000/scrape' => Http::response(null, 500),
    ]);

    $service = new Crawl4AIService();
    
    expect(fn() => $service->scrapeUrl('https://example.com'))
        ->toThrow(\RuntimeException::class);
});

test('crawl4ai service can set custom timeout', function () {
    Http::fake([
        '127.0.0.1:5000/scrape' => Http::response([
            'success' => true,
            'markdown' => 'Test content',
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $service->setTimeout(120);

    // Verify the service instance is returned for chaining
    expect($service->setTimeout(120))->toBeInstanceOf(Crawl4AIService::class);
});

test('crawl4ai service validates urls before scraping', function () {
    $service = new Crawl4AIService();
    
    // Invalid URL should throw InvalidArgumentException
    expect(fn() => $service->scrapeUrl('not-a-valid-url'))
        ->toThrow(\InvalidArgumentException::class);
});

test('crawl4ai service rejects non-http urls', function () {
    $service = new Crawl4AIService();
    
    // FTP URL should be rejected
    expect(fn() => $service->scrapeUrl('ftp://example.com/file'))
        ->toThrow(\InvalidArgumentException::class);
    
    // File URL should be rejected
    expect(fn() => $service->scrapeUrl('file:///etc/passwd'))
        ->toThrow(\InvalidArgumentException::class);
});

test('crawl4ai service filters invalid urls in batch', function () {
    Http::fake([
        '127.0.0.1:5000/batch' => Http::response([
            'results' => [
                [
                    'success' => true,
                    'markdown' => 'Valid result',
                ],
            ],
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $results = $service->scrapeUrls([
        'https://valid.example.com',
        'not-valid',
        'ftp://invalid.com',
    ]);

    // Should only have sent the valid URL
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return count($body['urls']) === 1 && 
               $body['urls'][0] === 'https://valid.example.com';
    });
});

test('crawl4ai service returns empty array for all invalid urls in batch', function () {
    $service = new Crawl4AIService();
    
    // All invalid URLs should return empty array without making HTTP call
    $results = $service->scrapeUrls([
        'not-valid',
        'also-not-valid',
    ]);

    expect($results)->toBeEmpty();
    Http::assertNothingSent();
});

test('crawl4ai service returns empty array for empty urls array', function () {
    $service = new Crawl4AIService();
    
    $results = $service->scrapeUrls([]);

    expect($results)->toBeEmpty();
    Http::assertNothingSent();
});

test('crawl4ai service sends correct request parameters', function () {
    Http::fake([
        '127.0.0.1:5000/scrape' => Http::response([
            'success' => true,
            'markdown' => 'Test',
        ], 200),
    ]);

    $service = new Crawl4AIService();
    $service->scrapeUrl('https://example.com', [
        'wait_for' => '.product-price',
        'timeout' => 45,
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return $body['url'] === 'https://example.com' &&
               $body['wait_for'] === '.product-price' &&
               $body['timeout'] === 45000; // seconds to ms
    });
});
