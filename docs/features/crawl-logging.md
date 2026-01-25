# Crawl Logging Feature

## Overview

The Crawl Logging feature provides console-style logging for crawl/price discovery jobs, allowing users to verify that crawling operations are working correctly directly from the UI.

## Features

1. **Structured Log Entries**: Logs include level (info, success, warning, error, debug), message, and timestamp
2. **Console-Style Display**: Logs are displayed in a terminal-like interface with color coding
3. **Real-Time Updates**: Logs update in real-time while jobs are processing
4. **Crawl Statistics**: Shows URL success/failure counts, stores found, and tier results
5. **Export Options**: Copy logs to clipboard or download as text file

## Components

### Frontend

#### CrawlLogViewer (`backend/resources/js/Components/CrawlLogViewer.tsx`)

A React component that displays crawl job logs in a console-style format.

**Props:**
- `outputData`: The job's output_data containing progress_logs
- `defaultExpanded`: Whether to show expanded by default (default: false)
- `maxHeight`: Maximum height of the log viewer (default: 300px)
- `autoScroll`: Auto-scroll to bottom on new logs (default: true)
- `className`: Additional CSS classes

**Usage:**
```tsx
<CrawlLogViewer
  outputData={job.output_data}
  defaultExpanded={job.status === 'processing'}
  maxHeight={250}
  autoScroll={true}
/>
```

#### JobCard Enhancement (`backend/resources/js/Components/JobsTab.tsx`)

The JobCard component now includes:
- Crawl results summary (prices found, best price, stores)
- Expandable log viewer for crawl-type jobs
- Real-time log updates during processing

### Backend

#### CrawlLogger (`backend/app/Support/CrawlLogger.php`)

A helper class for generating structured log entries.

**Methods:**
- `info(message, data?)` - Add info-level log (blue in UI)
- `success(message, data?)` - Add success-level log (green in UI)
- `warning(message, data?)` - Add warning-level log (yellow in UI)
- `error(message, data?)` - Add error-level log (red in UI)
- `debug(message, data?)` - Add debug-level log (gray in UI)
- `logUrlScrape(url, success, error?)` - Log URL scrape result
- `logPriceExtraction(store, price, itemName?)` - Log price extraction
- `logTierComplete(tier, resultsCount)` - Log tier completion
- `getLogs()` - Get structured log entries
- `getSimpleLogs()` - Get simple string logs (backward compatible)
- `getStats()` - Get crawl statistics

**Usage:**
```php
$logger = new CrawlLogger();
$logger->info("Starting price discovery for: {$productName}");
$logger->logUrlScrape($url, true);
$logger->success("Found price at Walmart: \$29.99");
```

#### FirecrawlDiscoveryJob Updates

The job now uses CrawlLogger for detailed logging:
- Logs each step of the discovery process
- Tracks URL scrape success/failure
- Logs price extractions from each store
- Captures timing statistics
- Stores both structured and simple logs in output_data

#### AIJobController Updates

The controller now returns `output_data` for crawl jobs to enable frontend log display.

## TypeScript Types

### CrawlLogEntry
```typescript
interface CrawlLogEntry {
  level: 'info' | 'success' | 'warning' | 'error' | 'debug';
  message: string;
  timestamp: string;
  data?: Record<string, unknown>;
}
```

### CrawlJobOutputData
```typescript
interface CrawlJobOutputData {
  product_name?: string;
  results?: Array<{...}>;
  results_count?: number;
  lowest_price?: number;
  highest_price?: number;
  source?: string;
  analysis?: {...};
  progress_logs?: CrawlLogEntry[];
  logs?: string[];  // Legacy format
  crawl_stats?: {
    urls_attempted: number;
    urls_successful: number;
    urls_failed: number;
    stores_found: number;
    tier1_results: number;
    tier2_results: number;
    total_duration_ms: number;
  };
}
```

## Job Types with Logging Support

The following job types display the log viewer:
- `firecrawl_discovery` - Price Discovery
- `firecrawl_refresh` - Price Refresh
- `price_search` - Price Search
- `price_refresh` - Price Refresh
- `nearby_store_discovery` - Nearby Store Discovery
- `store_auto_config` - Store URL Discovery

## Viewing Logs

1. Navigate to **Settings** > **Jobs** tab
2. Find a crawl/discovery job (active or completed)
3. Click "Crawl Logs" to expand the log viewer
4. Logs show:
   - Timestamp for each entry
   - Color-coded log level (info=blue, success=green, warning=yellow, error=red)
   - Message text
   - Optional additional data

## Statistics Bar

When expanded, the log viewer shows a statistics bar with:
- URLs attempted vs successful
- Failed URL count (if any)
- Stores found
- Tier 1 results count
- Tier 2 results count
- Total duration

## Export Options

- **Copy**: Copy all logs to clipboard as plain text
- **Download**: Download logs as a text file with timestamps

## Debug Logging for 0 Price Results

When Crawl4AI finds 0 prices (e.g. when Firecrawl previously did find them), enhanced logging helps identify the cause. Pass `debug: true` in the discovery options to enable verbose logs.

### What Gets Logged (with `debug: true`)

| Stage | Log detail |
|-------|------------|
| **URL generation** | All generated URLs, `query`, and `stores_without_url` (stores that could not produce a URL) |
| **Scrape** | First ~500 chars of markdown per URL, `suspicious_patterns` (e.g. "Robot Check", "CAPTCHA", "Access Denied"), request timing |
| **AI extraction** | Prompt preview, raw AI response, and parse failure reason (invalid JSON, missing `price`, or no JSON in response) |

### Enabling Debug

```php
$result = $discoveryService->discoverPrices($productName, [
    'debug' => true,
    'logger' => $logger,
    // ...other options
]);
```

With `debug: false` (default), verbose entries (content samples, prompt/response, parse details) are written at `debug` level and typically do not appear in production logs. "Suspicious content" and scrape failures remain at `warning`/`error`.

### Python service (Crawl4AI)

The Crawl4AI FastAPI service (`docker/crawl4ai/service.py`) logs:

- Incoming scrape and batch requests (URLs, timeout)
- Per-URL success/failure, markdown length, and duration
- Batch totals (URLs, successful count, duration)

View service logs: `docker compose exec danavision supervisorctl tail -f crawl4ai`

## Related Files

- `backend/resources/js/Components/CrawlLogViewer.tsx` - Log viewer component
- `backend/resources/js/Components/JobsTab.tsx` - Jobs tab with log integration
- `backend/resources/js/types/index.ts` - TypeScript type definitions
- `backend/app/Support/CrawlLogger.php` - Backend logging helper
- `backend/app/Jobs/AI/FirecrawlDiscoveryJob.php` - Job with logging
- `backend/app/Services/Crawler/StoreDiscoveryService.php` - Service with logging
- `backend/app/Services/Crawler/Crawl4AIService.php` - Scrape and content-quality logging
- `docker/crawl4ai/service.py` - Python scrape service logging
- `backend/app/Http/Controllers/AIJobController.php` - API controller
