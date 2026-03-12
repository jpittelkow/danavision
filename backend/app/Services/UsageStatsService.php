<?php

namespace App\Services;

use App\Models\IntegrationUsage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UsageStatsService
{
    /**
     * Get aggregated usage stats for the dashboard.
     *
     * @return array{summary: array, daily: array, by_provider: array}
     */
    public function getStats(
        string $dateFrom,
        string $dateTo,
        ?string $integration = null,
        ?string $provider = null,
        string $groupBy = 'day'
    ): array {
        $baseQuery = IntegrationUsage::byDateRange($dateFrom, $dateTo);

        if ($integration) {
            $baseQuery->byIntegration($integration);
        }
        if ($provider) {
            $baseQuery->byProvider($provider);
        }

        // Summary: total cost and cost by integration
        $totalCost = (clone $baseQuery)->sum('estimated_cost');

        $byIntegration = [];
        foreach (IntegrationUsage::INTEGRATIONS as $type) {
            $byIntegration[$type] = (float) (clone $baseQuery)
                ->where('integration', $type)
                ->sum('estimated_cost');
        }

        // Daily/weekly/monthly cost breakdown per integration
        $dateFormat = $this->getDateFormat($groupBy);
        $dailyRaw = (clone $baseQuery)
            ->selectRaw("{$dateFormat} as date, integration, SUM(estimated_cost) as cost")
            ->groupByRaw("{$dateFormat}, integration")
            ->orderBy('date')
            ->get();

        $daily = $this->pivotDailyData($dailyRaw);

        // Provider breakdown
        $byProvider = (clone $baseQuery)
            ->select('provider', 'integration', DB::raw('SUM(estimated_cost) as total_cost'), DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('provider', 'integration')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'integration' => $row->integration,
                'total_cost' => (float) $row->total_cost,
                'total_quantity' => (float) $row->total_quantity,
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'total_estimated_cost' => (float) $totalCost,
                'by_integration' => $byIntegration,
            ],
            'daily' => $daily,
            'by_provider' => $byProvider,
        ];
    }

    /**
     * Get API-specific usage statistics for the GraphQL admin dashboard.
     *
     * @return array{total_7d: int, total_30d: int, daily: array, top_users: array, top_queries: array}
     */
    public function getApiUsageStats(int $days = 30): array
    {
        $days = min(max($days, 1), 365);

        $total7d = IntegrationUsage::byIntegration('api')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('quantity');

        $total30d = IntegrationUsage::byIntegration('api')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity');

        $daily = IntegrationUsage::byIntegration('api')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("DATE(created_at) as date, SUM(quantity) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Top 10 users by request count
        $topUserRows = IntegrationUsage::byIntegration('api')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('user_id')
            ->selectRaw('user_id, SUM(quantity) as total_requests')
            ->groupBy('user_id')
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get();

        $userIds = $topUserRows->pluck('user_id')->all();
        $users = User::select('id', 'name', 'email')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $topUsers = $topUserRows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);
            return [
                'user_id' => $row->user_id,
                'name' => $user?->name ?? "User #{$row->user_id}",
                'email' => $user?->email,
                'total_requests' => (int) $row->total_requests,
            ];
        });

        // Top 10 query names — use DB-appropriate JSON extraction
        $driver = IntegrationUsage::query()->getConnection()->getDriverName();
        $jsonExpr = match ($driver) {
            'pgsql' => "metadata->>'query_name'",
            default => "JSON_EXTRACT(metadata, '$.query_name')",
        };
        $topQueries = IntegrationUsage::byIntegration('api')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('metadata')
            ->selectRaw("{$jsonExpr} as query_name, SUM(quantity) as total_requests")
            ->groupBy('query_name')
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get()
            ->filter(fn ($row) => $row->query_name !== null)
            ->map(fn ($row) => [
                // SQLite's JSON_EXTRACT returns double-quoted strings; trim them
                'query_name' => trim($row->query_name, '"'),
                'total_requests' => (int) $row->total_requests,
            ])
            ->values();

        return [
            'total_7d' => (int) $total7d,
            'total_30d' => (int) $total30d,
            'daily' => $daily,
            'top_users' => $topUsers,
            'top_queries' => $topQueries,
        ];
    }

    /**
     * Get detailed breakdown for a single integration type.
     * For LLM: by model. For SMS: by country. For others: by provider.
     */
    public function getBreakdown(
        string $dateFrom,
        string $dateTo,
        string $integration,
        ?int $userId = null
    ): array {
        $baseQuery = IntegrationUsage::byDateRange($dateFrom, $dateTo)
            ->byIntegration($integration);

        if ($userId) {
            $baseQuery->byUser($userId);
        }

        $driver = DB::getDriverName();
        $groupByField = match ($integration) {
            IntegrationUsage::INTEGRATION_LLM => match ($driver) {
                    'mysql' => "JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.model'))",
                    'pgsql' => "metadata->>'model'",
                    default => "REPLACE(json_extract(metadata, '$.model'), '\"', '')", // SQLite — strip JSON quotes
                },
            default => 'provider',
        };

        $breakdown = (clone $baseQuery)
            ->select(
                DB::raw("{$groupByField} as group_key"),
                'provider',
                DB::raw('SUM(estimated_cost) as total_cost'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(*) as total_records')
            )
            ->groupBy(DB::raw($groupByField), 'provider')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row) => [
                'group_key' => $row->group_key ?? 'unknown',
                'provider' => $row->provider,
                'total_cost' => (float) $row->total_cost,
                'total_quantity' => (float) $row->total_quantity,
                'total_records' => (int) $row->total_records,
            ])
            ->values()
            ->all();

        // Also get daily trend for this integration
        $dailyRaw = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, SUM(estimated_cost) as cost, SUM(quantity) as quantity')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'cost' => (float) $row->cost,
                'quantity' => (float) $row->quantity,
            ])
            ->values()
            ->all();

        // Per-user breakdown (for integrations that track user_id)
        // Note: can't use ->with('user') on aggregate GROUP BY query (no id column),
        // so we load users manually via a second query.
        $byUser = [];
        if (in_array($integration, [IntegrationUsage::INTEGRATION_LLM, IntegrationUsage::INTEGRATION_SMS])) {
            $rows = (clone $baseQuery)
                ->whereNotNull('user_id')
                ->select('user_id', DB::raw('SUM(estimated_cost) as total_cost'), DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('user_id')
                ->orderByDesc('total_cost')
                ->limit(20)
                ->get();

            $userIds = $rows->pluck('user_id')->unique()->filter();
            $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

            $byUser = $rows->map(fn ($row) => [
                    'user' => $users->get($row->user_id),
                    'total_cost' => (float) $row->total_cost,
                    'total_quantity' => (float) $row->total_quantity,
                ])
                ->values()
                ->all();
        }

        return [
            'integration' => $integration,
            'breakdown' => $breakdown,
            'daily' => $dailyRaw,
            'by_user' => $byUser,
        ];
    }

    /**
     * Build a query for CSV export and write to a stream handle.
     * Uses chunking to avoid memory exhaustion with large datasets.
     */
    public function exportToStream(
        $handle,
        string $dateFrom,
        string $dateTo,
        ?string $integration = null,
        ?string $provider = null
    ): void {
        $query = IntegrationUsage::byDateRange($dateFrom, $dateTo)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($integration) {
            $query->byIntegration($integration);
        }
        if ($provider) {
            $query->byProvider($provider);
        }

        $query->chunk(500, function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at->format('Y-m-d H:i:s'),
                    $row->integration,
                    $row->provider,
                    $row->metric,
                    $row->quantity,
                    $row->estimated_cost,
                    $row->user?->name ?? '',
                    $row->user?->email ?? '',
                    $row->metadata ? json_encode($row->metadata) : '',
                ]);
            }
        });
    }

    /**
     * Get the SQL date format expression for the groupBy parameter.
     * Supports SQLite, MySQL, and PostgreSQL.
     */
    private function getDateFormat(string $groupBy): string
    {
        $driver = DB::getDriverName();

        return match ($groupBy) {
            'week' => match ($driver) {
                'mysql' => "DATE_FORMAT(created_at, '%Y-W%u')",
                'pgsql' => "TO_CHAR(created_at, 'IYYY-\"W\"IW')",
                default => "strftime('%Y-W%W', created_at)", // SQLite
            },
            'month' => match ($driver) {
                'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
                'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
                default => "strftime('%Y-%m', created_at)", // SQLite
            },
            default => 'DATE(created_at)', // Works on all three
        };
    }

    /**
     * Pivot daily raw data into a structured array with one entry per date,
     * containing costs for each integration type.
     */
    private function pivotDailyData(Collection $dailyRaw): array
    {
        $grouped = [];
        foreach ($dailyRaw as $row) {
            $date = $row->date;
            if (!isset($grouped[$date])) {
                $grouped[$date] = ['date' => $date];
                foreach (IntegrationUsage::INTEGRATIONS as $type) {
                    $grouped[$date][$type] = 0.0;
                }
            }
            $grouped[$date][$row->integration] = (float) $row->cost;
        }

        return array_values($grouped);
    }
}
