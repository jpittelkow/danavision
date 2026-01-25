<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\Store;
use App\Services\Crawler\StoreAutoConfigService;
use Illuminate\Support\Facades\Log;

/**
 * StoreAutoConfigJob
 *
 * Background job for automatically detecting a store's search URL template.
 * Uses Crawl4AI for scraping and AI to analyze the page structure and detect
 * the search URL pattern.
 *
 * This job is dispatched when:
 * - A store is added via Nearby Store Discovery
 * - A store is added via Add Selected Stores
 * - A user manually triggers "Find Search URL" for a store
 */
class StoreAutoConfigJob extends BaseAIJob
{
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120; // 2 minutes

    /**
     * Process the store auto-config job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $storeId = $inputData['store_id'] ?? null;
        $websiteUrl = $inputData['website_url'] ?? null;
        $storeName = $inputData['store_name'] ?? 'Unknown Store';
        $logs = [];

        if (!$storeId) {
            throw new \RuntimeException('No store ID provided.');
        }

        if (!$websiteUrl) {
            throw new \RuntimeException('No website URL provided.');
        }

        $logs[] = "Starting URL discovery for: {$storeName}";
        $this->updateProgress($aiJob, 10, $logs);

        // Find the store
        $store = Store::find($storeId);
        if (!$store) {
            throw new \RuntimeException("Store not found (ID: {$storeId})");
        }

        // Check if store already has a search URL template
        if (!empty($store->search_url_template)) {
            $logs[] = "Store already has a search URL template configured";
            return [
                'store_id' => $storeId,
                'store_name' => $storeName,
                'template' => $store->search_url_template,
                'already_configured' => true,
                'logs' => $logs,
            ];
        }

        $logs[] = "Website: {$websiteUrl}";
        $this->updateProgress($aiJob, 20, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Initialize the auto-config service
        $service = StoreAutoConfigService::forUser($this->userId);

        $logs[] = "Analyzing website using tiered detection...";
        $logs[] = "Tier 1: Checking known store templates...";
        $this->updateProgress($aiJob, 30, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Detect the search URL template using tiered approach
        $result = $service->detectSearchUrlTemplate($websiteUrl, $useAgent);

        $this->updateProgress($aiJob, 80, $logs);

        // Add detection tier to logs
        if (isset($result['tier'])) {
            $tierNames = [
                'known_template' => 'Known template database',
                'common_pattern' => 'Common URL pattern validation',
                'ai_analysis' => 'AI page analysis',
                'firecrawl_agent' => 'Firecrawl Agent interaction',
            ];
            $logs[] = "Detection method: " . ($tierNames[$result['tier']] ?? $result['tier']);
        }

        if ($result['success'] && !empty($result['template'])) {
            $template = $result['template'];
            $validated = $result['validated'] ?? false;

            // Update the store with the discovered template
            $store->update([
                'search_url_template' => $template,
                'auto_configured' => true,
            ]);

            $logs[] = "Search URL template discovered successfully";
            $logs[] = "Template: {$template}";
            $logs[] = $validated ? "Template validated: Yes" : "Template validated: No (may need manual verification)";

            // Include local stock/price info if available
            if (isset($result['local_stock'])) {
                $logs[] = "Supports local stock: " . ($result['local_stock'] ? 'Yes' : 'No');
            }
            if (isset($result['local_price'])) {
                $logs[] = "Supports local pricing: " . ($result['local_price'] ? 'Yes' : 'No');
            }

            Log::info('StoreAutoConfigJob: Successfully configured store', [
                'ai_job_id' => $aiJob->id,
                'store_id' => $storeId,
                'store_name' => $storeName,
                'template' => $template,
                'validated' => $validated,
                'tier' => $result['tier'] ?? 'unknown',
            ]);

            return [
                'store_id' => $storeId,
                'store_name' => $storeName,
                'template' => $template,
                'validated' => $validated,
                'tier' => $result['tier'] ?? null,
                'local_stock' => $result['local_stock'] ?? null,
                'local_price' => $result['local_price'] ?? null,
                'success' => true,
                'logs' => $logs,
            ];
        }

        // Detection failed
        $error = $result['error'] ?? 'Could not detect search URL pattern';
        $logs[] = "Failed to detect search URL: {$error}";

        Log::warning('StoreAutoConfigJob: Failed to configure store', [
            'ai_job_id' => $aiJob->id,
            'store_id' => $storeId,
            'store_name' => $storeName,
            'error' => $error,
        ]);

        // Return partial success (job completed but no template found)
        // We don't throw an exception here because the job itself ran successfully,
        // it just couldn't find a template (which is a valid outcome)
        return [
            'store_id' => $storeId,
            'store_name' => $storeName,
            'template' => null,
            'success' => false,
            'error' => $error,
            'logs' => $logs,
        ];
    }

    /**
     * Get the tags for the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'ai-job',
            'store-auto-config',
            'user:' . $this->userId,
            'ai_job_id:' . $this->aiJobId,
        ];
    }
}
