<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Services\AccessLogService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessLogController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private AccessLogService $accessLogService
    ) {}

    /**
     * Get paginated access logs with filters (HIPAA).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.audit_log', 50));
        $filters = $request->only(['user_id', 'action', 'resource_type', 'correlation_id', 'date_from', 'date_to']);

        return response()->json(
            $this->accessLogService->buildFilteredQuery($filters)->paginate($perPage)
        );
    }

    /**
     * Export access logs as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['user_id', 'action', 'resource_type', 'correlation_id', 'date_from', 'date_to']);
        $logs = $this->accessLogService->queryForExport($filters);

        $filename = 'access_logs_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Date',
                'User',
                'Action',
                'Resource Type',
                'Resource ID',
                'Correlation ID',
                'Fields',
                'IP Address',
                'User Agent',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->email : '',
                    $log->action,
                    $log->resource_type,
                    $log->resource_id ?? '',
                    $log->correlation_id ?? '',
                    $log->fields_accessed ? implode(',', $log->fields_accessed) : '',
                    $log->ip_address ?? '',
                    $log->user_agent ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get access log statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        return response()->json($this->accessLogService->getStats($dateFrom, $dateTo));
    }

    /**
     * Delete all access logs. Allowed only when HIPAA access logging is disabled.
     * Violates HIPAA 6-year retention; audit log records the action.
     */
    public function deleteAll(Request $request): JsonResponse
    {
        if (config('logging.hipaa_access_logging_enabled', true)) {
            return response()->json([
                'message' => 'HIPAA access logging is enabled. Disable it in Log retention settings to delete all access logs.',
            ], 422);
        }

        $count = AccessLog::count();
        AccessLog::truncate();

        $this->auditService->log(
            'access_logs.delete_all',
            null,
            [],
            ['deleted_count' => $count],
            $request->user()?->id,
            $request,
            AuditLog::SEVERITY_WARNING
        );

        return response()->json([
            'message' => 'All access logs deleted.',
            'deleted_count' => $count,
        ]);
    }
}
