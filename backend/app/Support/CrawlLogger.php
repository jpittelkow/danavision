<?php

namespace App\Support;

/**
 * CrawlLogger
 *
 * Helper class for generating structured log entries for crawl jobs.
 * Creates console-style logs that can be displayed in the UI.
 *
 * Log levels:
 * - info: General information (blue in UI)
 * - success: Successful operations (green in UI)
 * - warning: Potential issues (yellow in UI)
 * - error: Failed operations (red in UI)
 * - debug: Detailed debugging info (gray in UI)
 */
class CrawlLogger
{
    /**
     * The collected log entries.
     *
     * @var array<int, array{level: string, message: string, timestamp: string, data?: array}>
     */
    protected array $logs = [];

    /**
     * Crawl statistics.
     *
     * @var array<string, int>
     */
    protected array $stats = [
        'urls_attempted' => 0,
        'urls_successful' => 0,
        'urls_failed' => 0,
        'stores_found' => 0,
        'tier1_results' => 0,
        'tier2_results' => 0,
        'total_duration_ms' => 0,
    ];

    /**
     * Start time for duration tracking.
     */
    protected ?float $startTime = null;

    /**
     * Create a new CrawlLogger instance.
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Add an info-level log entry.
     *
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    public function info(string $message, ?array $data = null): self
    {
        return $this->log('info', $message, $data);
    }

    /**
     * Add a success-level log entry.
     *
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    public function success(string $message, ?array $data = null): self
    {
        return $this->log('success', $message, $data);
    }

    /**
     * Add a warning-level log entry.
     *
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    public function warning(string $message, ?array $data = null): self
    {
        return $this->log('warning', $message, $data);
    }

    /**
     * Add an error-level log entry.
     *
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    public function error(string $message, ?array $data = null): self
    {
        return $this->log('error', $message, $data);
    }

    /**
     * Add a debug-level log entry.
     *
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    public function debug(string $message, ?array $data = null): self
    {
        return $this->log('debug', $message, $data);
    }

    /**
     * Add a log entry.
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array|null $data Optional additional data
     * @return self
     */
    protected function log(string $level, string $message, ?array $data = null): self
    {
        $entry = [
            'level' => $level,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $entry['data'] = $data;
        }

        $this->logs[] = $entry;

        return $this;
    }

    /**
     * Increment a statistic counter.
     *
     * @param string $key The stat key
     * @param int $amount Amount to increment by
     * @return self
     */
    public function incrementStat(string $key, int $amount = 1): self
    {
        if (isset($this->stats[$key])) {
            $this->stats[$key] += $amount;
        }

        return $this;
    }

    /**
     * Set a statistic value.
     *
     * @param string $key The stat key
     * @param int $value The value
     * @return self
     */
    public function setStat(string $key, int $value): self
    {
        $this->stats[$key] = $value;

        return $this;
    }

    /**
     * Log a URL scrape attempt with result.
     *
     * @param string $url The URL that was scraped
     * @param bool $success Whether the scrape was successful
     * @param string|null $error Error message if failed
     * @return self
     */
    public function logUrlScrape(string $url, bool $success, ?string $error = null): self
    {
        $this->incrementStat('urls_attempted');

        // Extract domain for cleaner logging
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain ?? 'unknown');

        if ($success) {
            $this->incrementStat('urls_successful');
            $this->success("Scraped {$domain} successfully");
        } else {
            $this->incrementStat('urls_failed');
            $this->error("Failed to scrape {$domain}" . ($error ? ": {$error}" : ''));
        }

        return $this;
    }

    /**
     * Log a price extraction result.
     *
     * @param string $storeName The store name
     * @param float|null $price The price found (null if not found)
     * @param string|null $itemName The item name if found
     * @return self
     */
    public function logPriceExtraction(string $storeName, ?float $price, ?string $itemName = null): self
    {
        if ($price !== null && $price > 0) {
            $this->success(
                "Found price at {$storeName}: \${$price}" .
                ($itemName ? " ({$itemName})" : '')
            );
        } else {
            $this->warning("No price found at {$storeName}");
        }

        return $this;
    }

    /**
     * Log tier completion.
     *
     * @param int $tier The tier number (1 or 2)
     * @param int $resultsCount Number of results found
     * @return self
     */
    public function logTierComplete(int $tier, int $resultsCount): self
    {
        $statKey = "tier{$tier}_results";
        $this->setStat($statKey, $resultsCount);

        if ($resultsCount > 0) {
            $this->success("Tier {$tier} complete: {$resultsCount} price(s) found");
        } else {
            $this->info("Tier {$tier} complete: No prices found");
        }

        return $this;
    }

    /**
     * Get all log entries.
     *
     * @return array The log entries array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Get simple string messages (for backward compatibility).
     *
     * @return array<int, string>
     */
    public function getSimpleLogs(): array
    {
        return array_map(fn($log) => $log['message'], $this->logs);
    }

    /**
     * Get crawl statistics.
     *
     * @return array The statistics array
     */
    public function getStats(): array
    {
        // Calculate total duration
        if ($this->startTime !== null) {
            $this->stats['total_duration_ms'] = (int) ((microtime(true) - $this->startTime) * 1000);
        }

        return $this->stats;
    }

    /**
     * Get the count of logs.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Check if there are any errors in the logs.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return count(array_filter($this->logs, fn($log) => $log['level'] === 'error')) > 0;
    }

    /**
     * Get the count of errors.
     *
     * @return int
     */
    public function errorCount(): int
    {
        return count(array_filter($this->logs, fn($log) => $log['level'] === 'error'));
    }
}
