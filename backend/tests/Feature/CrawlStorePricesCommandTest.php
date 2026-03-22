<?php

use App\Jobs\CrawlStorePriceJob;
use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\SettingService;
use Illuminate\Support\Facades\Bus;

describe('CrawlStorePricesCommand', function () {
    beforeEach(function () {
        Bus::fake([CrawlStorePriceJob::class]);
        // Remove seeded stores so only test-created stores are present
        Store::query()->delete();
    });

    it('exits early when store_crawl_enabled is false', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', false);

        $this->artisan('prices:crawl-stores')
            ->expectsOutputToContain('disabled')
            ->assertExitCode(0);

        Bus::assertNotDispatched(CrawlStorePriceJob::class);
    });

    it('exits with failure when CrawlAI is unavailable', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', true);
        $settingService->set('price_search', 'crawl4ai_enabled', true);

        $mock = Mockery::mock(CrawlAIService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(false);
        $this->app->instance(CrawlAIService::class, $mock);

        $this->artisan('prices:crawl-stores')
            ->expectsOutputToContain('unavailable')
            ->assertExitCode(1);

        Bus::assertNotDispatched(CrawlStorePriceJob::class);
    });

    it('dispatches jobs for active stores with search URLs', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', true);

        $mock = Mockery::mock(CrawlAIService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(CrawlAIService::class, $mock);

        $store = Store::create([
            'name' => 'Test Grocery',
            'slug' => 'test-grocery',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        // Inactive store should be ignored
        Store::create([
            'name' => 'Inactive Store',
            'slug' => 'inactive-store',
            'category' => 'grocery',
            'is_active' => false,
            'search_url_template' => 'https://inactive.com/search?q={query}',
        ]);

        $this->artisan('prices:crawl-stores --force')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlStorePriceJob::class, 1);
        Bus::assertDispatched(CrawlStorePriceJob::class, fn ($job) => $job->storeId === $store->id);
    });

    it('filters stores by category', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', true);

        $mock = Mockery::mock(CrawlAIService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(CrawlAIService::class, $mock);

        Store::create([
            'name' => 'Grocery Store',
            'slug' => 'grocery-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://grocery.com/search?q={query}',
        ]);

        $electronics = Store::create([
            'name' => 'Electronics Store',
            'slug' => 'electronics-store',
            'category' => 'electronics',
            'is_active' => true,
            'search_url_template' => 'https://electronics.com/search?q={query}',
        ]);

        $this->artisan('prices:crawl-stores --category=electronics --force')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlStorePriceJob::class, 1);
        Bus::assertDispatched(CrawlStorePriceJob::class, fn ($job) => $job->storeId === $electronics->id);
    });

    it('skips stores not due for crawling based on last_crawled_at', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', true);

        $mock = Mockery::mock(CrawlAIService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(CrawlAIService::class, $mock);

        // Recently crawled (within 6h grocery interval)
        Store::create([
            'name' => 'Recent Grocery',
            'slug' => 'recent-grocery',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://recent.com/search?q={query}',
            'last_crawled_at' => now()->subHours(2),
        ]);

        // Due for crawling (past 6h grocery interval)
        $dueStore = Store::create([
            'name' => 'Due Grocery',
            'slug' => 'due-grocery',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://due.com/search?q={query}',
            'last_crawled_at' => now()->subHours(8),
        ]);

        // Never crawled
        $neverStore = Store::create([
            'name' => 'Never Crawled',
            'slug' => 'never-crawled',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://never.com/search?q={query}',
            'last_crawled_at' => null,
        ]);

        $this->artisan('prices:crawl-stores')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlStorePriceJob::class, 2);
        Bus::assertDispatched(CrawlStorePriceJob::class, fn ($job) => $job->storeId === $dueStore->id);
        Bus::assertDispatched(CrawlStorePriceJob::class, fn ($job) => $job->storeId === $neverStore->id);
    });

    it('forces crawl of all stores with --force', function () {
        $settingService = app(SettingService::class);
        $settingService->set('price_search', 'store_crawl_enabled', true);

        $mock = Mockery::mock(CrawlAIService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(CrawlAIService::class, $mock);

        Store::create([
            'name' => 'Recent Store',
            'slug' => 'recent-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://recent.com/search?q={query}',
            'last_crawled_at' => now()->subMinutes(5),
        ]);

        $this->artisan('prices:crawl-stores --force')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlStorePriceJob::class, 1);
    });
});
