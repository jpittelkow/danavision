<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\Shopping\StoreCrawlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlStorePriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly int $storeId,
    ) {}

    /**
     * Exponential backoff delays between retries (seconds).
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(CrawlAIService $crawlAIService, StoreCrawlService $storeCrawlService): void
    {
        $store = Store::find($this->storeId);

        if (! $store || ! $store->is_active) {
            Log::info('CrawlStorePriceJob: store not found or inactive', ['store_id' => $this->storeId]);

            return;
        }

        if (! $crawlAIService->isAvailable()) {
            if ($this->attempts() < $this->tries) {
                $this->release(60);

                return;
            }

            Log::warning('CrawlStorePriceJob: CrawlAI unavailable, giving up', ['store' => $store->name]);

            return;
        }

        $stats = $storeCrawlService->crawlStore($store);

        Log::info('CrawlStorePriceJob: completed', [
            'store' => $store->name,
            'products_checked' => $stats['products_checked'],
            'prices_updated' => $stats['prices_updated'],
            'errors' => $stats['errors'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CrawlStorePriceJob: failed', [
            'store_id' => $this->storeId,
            'error' => $e->getMessage(),
        ]);
    }
}
