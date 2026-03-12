<?php

namespace App\Services;

use App\Models\AccessLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessLogService
{
    /**
     * Build a filtered access log query.
     *
     * @param array<string, mixed> $filters  Keys: user_id, action, resource_type, correlation_id, date_from, date_to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function buildFilteredQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = AccessLog::with('user');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }
        if (isset($filters['correlation_id']) && $filters['correlation_id'] !== '') {
            $query->where('correlation_id', $filters['correlation_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get filtered access logs for export.
     *
     * @param array<string, mixed> $filters  Keys: user_id, action, resource_type, correlation_id, date_from, date_to
     */
    public function queryForExport(array $filters): LazyCollection
    {
        return $this->buildFilteredQuery($filters)->cursor();
    }

    /**
     * Get access log statistics for a date range.
     */
    public function getStats(string $dateFrom, string $dateTo): array
    {
        $baseQuery = AccessLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);

        return [
            'total' => (clone $baseQuery)->count(),
            'by_action' => (clone $baseQuery)
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->get()
                ->pluck('count', 'action'),
            'by_resource_type' => (clone $baseQuery)
                ->select('resource_type', DB::raw('count(*) as count'))
                ->groupBy('resource_type')
                ->orderByDesc('count')
                ->get()
                ->pluck('count', 'resource_type'),
            'by_user' => (clone $baseQuery)
                ->select('user_id', DB::raw('count(*) as count'))
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->with('user:id,name,email')
                ->get()
                ->map(fn ($item) => [
                    'user' => $item->user,
                    'count' => $item->count,
                ]),
            'daily_trends' => (clone $baseQuery)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date'),
        ];
    }

    /**
     * Log access to a protected resource (PHI) for HIPAA compliance.
     *
     * @param  string  $action  view, create, update, delete, export
     * @param  string  $resourceType  User, Setting, etc.
     * @param  array<string>|null  $fieldsAccessed  Which fields were returned or modified (optional)
     */
    public function log(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?array $fieldsAccessed = null,
        ?Request $request = null
    ): void {
        if (! config('logging.hipaa_access_logging_enabled', true)) {
            return;
        }

        $request ??= request();
        $user = $request->user();

        if (! $user) {
            return;
        }

        try {
            AccessLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'fields_accessed' => $fieldsAccessed,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Access log write failed', [
                'action' => $action,
                'resource_type' => $resourceType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
