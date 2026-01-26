# Job Logging Feature

## Overview

The Job Logging feature provides console-style logging for AI and crawl/discovery jobs, allowing users to verify that background operations are working correctly directly from the UI. Logs update in real-time during job processing via the existing job polling mechanism.

## Features

1. **Structured Log Entries**: Logs include level (info, success, warning, error, debug), message, and timestamp
2. **Console-Style Display**: Logs are displayed in a terminal-like interface with color coding
3. **Real-Time Updates**: Logs update while jobs are processing (polling every 2–3 seconds)
4. **Crawl Statistics**: For discovery jobs, shows URL success/failure counts, stores found, and tier results
5. **Export Options**: Copy logs to clipboard or download as text file

## Components

### Frontend

#### JobLogViewer (`backend/resources/js/Components/JobLogViewer.tsx`)

A React component that displays job logs in a console-style format.

**Props:**

- `outputData`: The job's output_data containing progress_logs and optionally crawl_stats
- `defaultExpanded`: Whether to show expanded by default (default: false)
- `maxHeight`: Maximum height of the log viewer (default: 300px)
- `autoScroll`: Auto-scroll to bottom on new logs (default: true)
- `className`: Additional CSS classes

**Usage:**

```tsx
<JobLogViewer
  outputData={job.output_data}
  defaultExpanded={job.status === 'processing'}
  maxHeight={250}
  autoScroll={true}
/>
```

The viewer supports both structured `progress_logs` (array of `JobLogEntry`) and legacy `logs` (string array), which it converts for display.

#### JobsTab Integration

The JobCard in `JobsTab` includes the JobLogViewer for all jobs. The viewer returns null when there are no logs or crawl_stats, so it only appears for jobs that produce them.

### Backend

#### JobLogger (`backend/app/Support/JobLogger.php`)

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
- `incrementStat(key, amount)` - Increment a statistic
- `setStat(key, value)` - Set a statistic value
- `getLogs()` - Get structured log entries
- `getSimpleLogs()` - Get simple string logs (backward compatible)
- `getStats()` - Get crawl statistics (includes total_duration_ms)

**Usage:**

```php
$logger = new JobLogger();
$logger->info("Starting price discovery for: {$productName}");
$logger->success("Found price at Walmart: \$29.99");
$logger->setStat('stores_found', 5);
// In job return: 'progress_logs' => $logger->getLogs(), 'crawl_stats' => $logger->getStats()
```

#### AIJobController

The controller includes `output_data` in list and active responses for job types that have progress logs: firecrawl_discovery, firecrawl_refresh, price_search, price_refresh, nearby_store_discovery, store_auto_config. This allows the JobLogViewer to display logs without requiring a separate detail request.

#### BaseAIJob::updateProgress

Supports both structured log entries (array with `level`, `message`, `timestamp`) and legacy string arrays. When the last log is a structured entry, the `message` field is used as the status message.

## TypeScript Types

### JobLogLevel

```typescript
type JobLogLevel = 'info' | 'success' | 'warning' | 'error' | 'debug';
```

### JobLogEntry

```typescript
interface JobLogEntry {
  level: JobLogLevel;
  message: string;
  timestamp: string;
  data?: Record<string, unknown>;
}
```

### JobCrawlStats

```typescript
interface JobCrawlStats {
  urls_attempted: number;
  urls_successful: number;
  urls_failed: number;
  stores_found: number;
  tier1_results: number;
  tier2_results: number;
  total_duration_ms: number;
}
```

### JobOutputData

```typescript
interface JobOutputData {
  product_name?: string;
  results?: Array<Record<string, unknown>>;
  results_count?: number;
  lowest_price?: number;
  highest_price?: number;
  source?: string;
  analysis?: Record<string, unknown>;
  progress_logs?: JobLogEntry[];
  logs?: string[];
  crawl_stats?: JobCrawlStats;
}
```

## Job Types with Logging Support

The following job types receive `output_data` in list/active API responses and can display the log viewer when they produce logs:

- `firecrawl_discovery` - Price Discovery (uses JobLogger)
- `firecrawl_refresh` - Price Refresh
- `price_search` - Price Search
- `price_refresh` - Price Refresh
- `nearby_store_discovery` - Nearby Store Discovery
- `store_auto_config` - Store URL Discovery

## Viewing Logs

1. Navigate to **Settings** > **Jobs** tab
2. Find a job that produces logs (e.g. Firecrawl Discovery, active or completed)
3. Click **Job Logs** to expand the log viewer
4. Logs show:
   - Timestamp for each entry
   - Color-coded log level (info=blue, success=green, warning=yellow, error=red, debug=gray)
   - Message text
   - Optional additional data

## Statistics Bar

When `crawl_stats` is present, the log viewer shows a statistics bar with:

- URLs attempted vs successful
- Failed URL count (if any)
- Stores found
- Tier 1 results count
- Tier 2 results count
- Total duration

## Export Options

- **Copy**: Copy all logs to clipboard as plain text
- **Download**: Download logs as a text file with timestamps

## Related Files

- `backend/resources/js/Components/JobLogViewer.tsx` - Log viewer component
- `backend/resources/js/Components/JobsTab.tsx` - Jobs tab with log integration
- `backend/resources/js/types/index.ts` - TypeScript type definitions
- `backend/app/Support/JobLogger.php` - Backend logging helper
- `backend/app/Jobs/AI/FirecrawlDiscoveryJob.php` - Job using JobLogger
- `backend/app/Jobs/AI/BaseAIJob.php` - updateProgress with structured log support
- `backend/app/Http/Controllers/AIJobController.php` - output_data for log-viewer job types
