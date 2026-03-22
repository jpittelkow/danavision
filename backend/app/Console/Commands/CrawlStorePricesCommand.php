<?php

namespace App\Console\Commands;

use App\Jobs\CrawlStorePriceJob;
use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\SettingService;
use Illuminate\Console\Command;

class CrawlStorePricesCommand extends Command
{
    protected $signature = 'prices:crawl-stores
        {--store= : Crawl a specific store ID}
        {--category= : Crawl only stores in this category}
        {--force : Ignore last_crawled_at and crawl all due stores}';

    protected $description = 'Dispatch background crawl jobs for stores to keep cached prices fresh.';

    /**
     * Category-to-interval map (hours).
     */
    private const CATEGORY_INTERVALS = [
        'grocery' => 6,
        'general' => 6,
        'warehouse' => 8,
        'electronics' => 12,
        'pharmacy' => 12,
        'home-improvement' => 12,
        'delivery' => 12,
    ];

    public function handle(CrawlAIService $crawlAIService, SettingService $settingService): int
    {
        if (! $settingService->get('price_search', 'store_crawl_enabled', false)) {
            $this->info('Store crawling is disabled. Enable via price_search.store_crawl_enabled setting.');

            return self::SUCCESS;
        }

        if (! $crawlAIService->isAvailable()) {
            $this->error('CrawlAI service is unavailable. Aborting.');

            return self::FAILURE;
        }

        $query = Store::where('is_active', true)
            ->whereNotNull('search_url_template');

        if ($storeId = $this->option('store')) {
            $query->where('id', $storeId);
        }

        if ($category = $this->option('category')) {
            $query->where('category', $category);
        }

        $stores = $query->get();

        if ($stores->isEmpty()) {
            $this->info('No stores match the criteria.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($stores as $store) {
            if (! $this->option('force') && ! $this->isDue($store)) {
                $skipped++;

                continue;
            }

            CrawlStorePriceJob::dispatch($store->id);
            $dispatched++;
            $this->line("  Dispatched: {$store->name} ({$store->category})");
        }

        $this->info("Dispatched {$dispatched} crawl jobs, skipped {$skipped} (not due).");

        return self::SUCCESS;
    }

    /**
     * Check if a store is due for crawling based on its category interval.
     */
    private function isDue(Store $store): bool
    {
        if ($store->last_crawled_at === null) {
            return true;
        }

        $intervalHours = self::CATEGORY_INTERVALS[$store->category] ?? 12;

        return $store->last_crawled_at->lt(now()->subHours($intervalHours));
    }
}
