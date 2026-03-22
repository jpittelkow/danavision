<?php

use App\Jobs\CrawlStorePriceJob;
use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\Shopping\StoreCrawlService;
use Illuminate\Support\Facades\Log;

describe('CrawlStorePriceJob', function () {
    it('skips when store is not found', function () {
        Log::spy();

        $crawlAI = Mockery::mock(CrawlAIService::class);
        $storeCrawl = Mockery::mock(StoreCrawlService::class);
        $storeCrawl->shouldNotReceive('crawlStore');

        $job = new CrawlStorePriceJob(999999);
        $job->handle($crawlAI, $storeCrawl);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'not found or inactive'))
            ->once();
    });

    it('skips when store is inactive', function () {
        $store = Store::create([
            'name' => 'Inactive Store',
            'slug' => 'inactive-store',
            'category' => 'grocery',
            'is_active' => false,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        Log::spy();

        $crawlAI = Mockery::mock(CrawlAIService::class);
        $storeCrawl = Mockery::mock(StoreCrawlService::class);
        $storeCrawl->shouldNotReceive('crawlStore');

        $job = new CrawlStorePriceJob($store->id);
        $job->handle($crawlAI, $storeCrawl);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'not found or inactive'))
            ->once();
    });

    it('calls StoreCrawlService for active stores', function () {
        $store = Store::create([
            'name' => 'Active Store',
            'slug' => 'active-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        $crawlAI = Mockery::mock(CrawlAIService::class);
        $crawlAI->shouldReceive('isAvailable')->once()->andReturn(true);

        $storeCrawl = Mockery::mock(StoreCrawlService::class);
        $storeCrawl->shouldReceive('crawlStore')
            ->once()
            ->withArgs(fn (Store $s) => $s->id === $store->id)
            ->andReturn(['products_checked' => 5, 'prices_updated' => 3, 'errors' => 0]);

        Log::spy();

        $job = new CrawlStorePriceJob($store->id);
        $job->handle($crawlAI, $storeCrawl);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'completed'))
            ->once();
    });

    it('warns and returns when CrawlAI is unavailable on last attempt', function () {
        $store = Store::create([
            'name' => 'Store For Unavailable Test',
            'slug' => 'store-unavailable-test',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        $crawlAI = Mockery::mock(CrawlAIService::class);
        $crawlAI->shouldReceive('isAvailable')->once()->andReturn(false);

        $storeCrawl = Mockery::mock(StoreCrawlService::class);
        $storeCrawl->shouldNotReceive('crawlStore');

        Log::spy();

        // Simulate the job on its final attempt (attempts >= tries)
        $job = Mockery::mock(CrawlStorePriceJob::class, [$store->id])->makePartial();
        $job->shouldReceive('attempts')->andReturn(2);
        $job->handle($crawlAI, $storeCrawl);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'unavailable'))
            ->once();
    });

    it('logs failed jobs', function () {
        Log::spy();

        $job = new CrawlStorePriceJob(42);
        $job->failed(new \RuntimeException('Connection timeout'));

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'failed') && $ctx['store_id'] === 42)
            ->once();
    });
});
