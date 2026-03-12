<?php

namespace App\Console\Commands;

use App\Models\AccessLog;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogCleanupCommand extends Command
{
    protected $signature = 'log:cleanup
        {--dry-run : Report what would be deleted without making changes}
        {--archive : Export to storage before deleting (audit and access logs only)}';

    protected $description = 'Remove log entries and files older than configured retention. Access logs have a 6-year HIPAA minimum.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $archive = $this->option('archive');

        $appDays = (int) config('logging.retention.app_days', 90);
        $auditDays = (int) config('logging.retention.audit_days', 365);
        $accessDays = (int) config('logging.retention.access_days', 2190);

        if ($dryRun) {
            $this->info('Dry run: no changes will be made.');
        }

        $auditCutoff = Carbon::now()->subDays($auditDays);
        $accessCutoff = Carbon::now()->subDays($accessDays);

        $auditQuery = AuditLog::where('created_at', '<', $auditCutoff);
        $auditCount = $auditQuery->count();
        $this->line("Audit logs older than {$auditDays} days (before {$auditCutoff->toDateString()}): {$auditCount}");

        $accessQuery = AccessLog::where('created_at', '<', $accessCutoff);
        $accessCount = $accessQuery->count();
        $this->line("Access logs older than {$accessDays} days (before {$accessCutoff->toDateString()}): {$accessCount}");

        if ($archive && ! $dryRun && ($auditCount > 0 || $accessCount > 0)) {
            $dir = 'log-archive/' . Carbon::now()->format('Y-m-d_His');
            $path = storage_path('app/' . $dir);
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
            if ($auditCount > 0) {
                $auditPath = $path . '/audit_logs.csv';
                $this->exportAuditToCsv($auditQuery->orderBy('id'), $auditPath);
                $this->info("Archived audit logs to {$auditPath}");
            }
            if ($accessCount > 0) {
                $accessPath = $path . '/access_logs.csv';
                $this->exportAccessToCsv($accessQuery->orderBy('id'), $accessPath);
                $this->info("Archived access logs to {$accessPath}");
            }
        }

        if (! $dryRun) {
            $auditDeleted = AuditLog::where('created_at', '<', $auditCutoff)->delete();
            $this->info("Deleted {$auditDeleted} audit log(s).");
            $accessDeleted = AccessLog::where('created_at', '<', $accessCutoff)->delete();
            $this->info("Deleted {$accessDeleted} access log(s).");
        }

        $logPath = storage_path('logs');
        $appCutoff = Carbon::now()->subDays($appDays);
        $filesDeleted = 0;
        if (is_dir($logPath)) {
            $glob = glob($logPath . '/laravel-*.log');
            if ($glob !== false) {
                foreach ($glob as $file) {
                    if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $m)) {
                        try {
                            $fileDate = Carbon::parse($m[1]);
                            if ($fileDate->lt($appCutoff)) {
                                if (! $dryRun && unlink($file)) {
                                    $filesDeleted++;
                                } else {
                                    $filesDeleted += $dryRun ? 1 : 0;
                                }
                            }
                        } catch (\Throwable) {
                            // skip
                        }
                    }
                }
            }
        }
        $this->line("Application log files older than {$appDays} days: " . ($dryRun ? $filesDeleted . " (would delete)" : $filesDeleted . " deleted"));

        $this->info('Log cleanup finished.');
        return self::SUCCESS;
    }

    private function exportAuditToCsv($query, string $path): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            return;
        }
        fputcsv($handle, ['id', 'user_id', 'action', 'severity', 'correlation_id', 'ip_address', 'user_agent', 'created_at']);
        $query->chunk(1000, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user_id,
                    $log->action,
                    $log->severity ?? 'info',
                    $log->correlation_id ?? '',
                    $log->ip_address ?? '',
                    $log->user_agent ?? '',
                    $log->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
        fclose($handle);
    }

    private function exportAccessToCsv($query, string $path): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            return;
        }
        fputcsv($handle, ['id', 'user_id', 'action', 'resource_type', 'resource_id', 'correlation_id', 'ip_address', 'created_at']);
        $query->chunk(1000, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user_id,
                    $log->action,
                    $log->resource_type,
                    $log->resource_id ?? '',
                    $log->correlation_id ?? '',
                    $log->ip_address ?? '',
                    $log->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
        fclose($handle);
    }
}
