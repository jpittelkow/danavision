<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class AppLogExportService
{
    /**
     * List log file paths that may contain entries in the given date range.
     * Handles both single (laravel.log) and daily (laravel-YYYY-MM-DD.log) channels.
     *
     * @return array<string>
     */
    public function getLogFilesInRange(?string $dateFrom, ?string $dateTo): array
    {
        $logPath = storage_path('logs');
        if (! is_dir($logPath)) {
            return [];
        }

        $files = [];
        $glob = glob($logPath . '/laravel*.log');
        if ($glob === false) {
            return [];
        }

        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        foreach ($glob as $path) {
            $basename = basename($path);
            if ($basename === 'laravel.log') {
                if ($start === null && $end === null) {
                    $files[] = $path;
                } else {
                    $mtime = filemtime($path);
                    if ($mtime === false) {
                        continue;
                    }
                    if ($start && $mtime < $start->timestamp) {
                        continue;
                    }
                    if ($end && $mtime > $end->timestamp) {
                        continue;
                    }
                    $files[] = $path;
                }
                continue;
            }
            if (! preg_match('/^laravel-(\d{4}-\d{2}-\d{2})\.log$/', $basename, $m)) {
                continue;
            }
            $fileDate = Carbon::parse($m[1]);
            if ($start && $fileDate->lt($start)) {
                continue;
            }
            if ($end && $fileDate->gt($end)) {
                continue;
            }
            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Parse a log line. Returns array with keys: datetime, level, message, correlation_id (optional) or null if unparseable.
     *
     * @return array{datetime: string, level: string, message: string, correlation_id: string|null}|null
     */
    public function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (Str::startsWith($line, '{')) {
            $data = json_decode($line, true);
            if (! is_array($data)) {
                return null;
            }
            $level = $data['level_name'] ?? $data['level'] ?? 'INFO';
            if (is_int($level)) {
                $names = [100 => 'debug', 200 => 'info', 250 => 'notice', 300 => 'warning', 400 => 'error', 500 => 'critical', 550 => 'alert', 600 => 'emergency'];
                $level = $names[$level] ?? 'info';
            } else {
                $level = strtolower((string) $level);
            }
            $message = $data['message'] ?? '';
            $datetime = $data['datetime'] ?? $data['timestamp'] ?? null;
            if (is_array($datetime)) {
                $datetime = $datetime['date'] ?? null;
            }
            $context = $data['context'] ?? $data['extra'] ?? [];
            $correlationId = $context['correlation_id'] ?? null;

            return [
                'datetime' => $datetime ?? '',
                'level' => $level,
                'message' => $message,
                'correlation_id' => $correlationId,
            ];
        }

        if (preg_match('/^\[([^\]]+)\]\s+\S+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL):\s*(.*)$/s', $line, $m)) {
            return [
                'datetime' => $m[1],
                'level' => strtolower($m[2]),
                'message' => $m[3],
                'correlation_id' => null,
            ];
        }

        return null;
    }

    /**
     * Check if a parsed entry passes the level and correlation_id filters.
     */
    public function passesFilters(array $entry, ?string $levelFilter, ?string $correlationIdFilter): bool
    {
        if ($levelFilter !== null && $levelFilter !== '' && $entry['level'] !== $levelFilter) {
            return false;
        }
        if ($correlationIdFilter !== null && $correlationIdFilter !== '') {
            $cid = $entry['correlation_id'] ?? null;
            if ($cid === null || $cid !== $correlationIdFilter) {
                return false;
            }
        }
        return true;
    }

    /**
     * Read the most recent N log entries from all log files, newest first.
     *
     * @return array<array{datetime: string, level: string, message: string, correlation_id: string|null}>
     */
    public function getRecentEntries(int $limit = 200, ?string $level = null, ?string $search = null): array
    {
        $files = $this->getLogFilesInRange(null, null);
        // Process files in reverse order (newest files last in sorted list)
        $files = array_reverse($files);

        $entries = [];
        foreach ($files as $path) {
            if (! is_readable($path)) {
                continue;
            }
            // Read all lines and reverse for newest-first ordering.
            // file() is O(n) vs SplFileObject::seek() in a loop which is O(n^2).
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }
                $entry = $this->parseLine($line);
                if ($entry === null) {
                    continue;
                }
                if ($level && $entry['level'] !== $level) {
                    continue;
                }
                if ($search && stripos($entry['message'], $search) === false) {
                    continue;
                }
                $entries[] = $entry;
                if (count($entries) >= $limit) {
                    break 2;
                }
            }
        }

        return $entries;
    }

    /**
     * Check if entry datetime falls within date range (when parsed datetime is available).
     */
    public function inDateRange(array $entry, ?string $dateFrom, ?string $dateTo): bool
    {
        $dt = $entry['datetime'] ?? '';
        if ($dt === '') {
            return true;
        }
        try {
            $entryDate = Carbon::parse($dt);
            if ($dateFrom && $entryDate->lt(Carbon::parse($dateFrom)->startOfDay())) {
                return false;
            }
            if ($dateTo && $entryDate->gt(Carbon::parse($dateTo)->endOfDay())) {
                return false;
            }
        } catch (\Throwable) {
            return true;
        }
        return true;
    }
}
