<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\AuditLog;
use Carbon\Carbon;

class SuspiciousActivityService
{
    /** Failed login threshold: alert if this many in the window. */
    public const FAILED_LOGIN_THRESHOLD = 5;

    /** Failed login window (minutes). */
    public const FAILED_LOGIN_WINDOW_MINUTES = 15;

    /** Bulk export threshold: alert if this many exports in the window. */
    public const BULK_EXPORT_THRESHOLD = 10;

    /** Bulk export window (minutes). */
    public const BULK_EXPORT_WINDOW_MINUTES = 60;

    /**
     * Check for suspicious patterns and return alerts (each with type, message, count).
     *
     * @return array<int, array{type: string, message: string, count: int}>
     */
    public function check(): array
    {
        $alerts = [];

        $failedLoginCount = AuditLog::where('action', 'like', 'auth.login_failed%')
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::FAILED_LOGIN_WINDOW_MINUTES))
            ->count();

        if ($failedLoginCount >= self::FAILED_LOGIN_THRESHOLD) {
            $alerts[] = [
                'type' => 'failed_logins',
                'message' => sprintf(
                    '%d failed login attempts in the last %d minutes.',
                    $failedLoginCount,
                    self::FAILED_LOGIN_WINDOW_MINUTES
                ),
                'count' => $failedLoginCount,
            ];
        }

        $exportCount = AccessLog::where('action', 'export')
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::BULK_EXPORT_WINDOW_MINUTES))
            ->count();

        if ($exportCount >= self::BULK_EXPORT_THRESHOLD) {
            $alerts[] = [
                'type' => 'bulk_export',
                'message' => sprintf(
                    '%d data export actions in the last %d minutes (PHI access).',
                    $exportCount,
                    self::BULK_EXPORT_WINDOW_MINUTES
                ),
                'count' => $exportCount,
            ];
        }

        return $alerts;
    }
}
