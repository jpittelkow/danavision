<?php

namespace App\Http\Controllers;

use App\Models\AIRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * AIRequestLogController
 * 
 * Handles API endpoints for viewing and managing AI request logs.
 * Provides transparency into AI API usage for debugging and auditing.
 */
class AIRequestLogController extends Controller
{
    /**
     * List AI request logs for the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIRequestLog::class);

        $query = AIRequestLog::forUser($user->id)
            ->orderByDesc('created_at');

        // Filter by provider
        if ($request->filled('provider')) {
            $query->byProvider($request->input('provider'));
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->input('status') === 'success') {
                $query->successful();
            } elseif (in_array($request->input('status'), ['failed', 'timeout'])) {
                $query->failed();
            }
        }

        // Filter by request type
        if ($request->filled('request_type')) {
            $query->byType($request->input('request_type'));
        }

        // Filter by job ID
        if ($request->filled('ai_job_id')) {
            $query->forJob($request->integer('ai_job_id'));
        }

        // Date range filtering
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        // Pagination
        $perPage = min($request->integer('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'logs' => collect($logs->items())->map(fn($log) => $this->formatLog($log)),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get a single log with full details.
     *
     * @param AIRequestLog $log
     * @return JsonResponse
     */
    public function show(AIRequestLog $log): JsonResponse
    {
        Gate::authorize('view', $log);

        return response()->json([
            'log' => $this->formatLog($log, true),
        ]);
    }

    /**
     * Delete a single log entry.
     *
     * @param AIRequestLog $log
     * @return JsonResponse
     */
    public function destroy(AIRequestLog $log): JsonResponse
    {
        Gate::authorize('delete', $log);

        $log->delete();

        return response()->json([
            'message' => 'Log deleted successfully',
        ]);
    }

    /**
     * Clear all logs for the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('deleteAll', AIRequestLog::class);

        $deleted = AIRequestLog::forUser($user->id)->delete();

        return response()->json([
            'message' => "Deleted {$deleted} log entries",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Get usage statistics for the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIRequestLog::class);

        $days = $request->integer('days', 30);
        $stats = AIRequestLog::getStatsForUser($user->id, $days);

        return response()->json($stats);
    }

    /**
     * Format a log for API response.
     *
     * @param AIRequestLog $log
     * @param bool $includeFullData Whether to include full request/response data
     * @return array
     */
    protected function formatLog(AIRequestLog $log, bool $includeFullData = false): array
    {
        $data = [
            'id' => $log->id,
            'ai_job_id' => $log->ai_job_id,
            'provider' => $log->provider,
            'provider_display_name' => $log->provider_display_name,
            'model' => $log->model,
            'request_type' => $log->request_type,
            'type_label' => $log->type_label,
            'status' => $log->status,
            'duration_ms' => $log->duration_ms,
            'formatted_duration' => $log->formatted_duration,
            'tokens_input' => $log->tokens_input,
            'tokens_output' => $log->tokens_output,
            'total_tokens' => $log->total_tokens,
            'error_message' => $log->error_message,
            'created_at' => $log->created_at->toISOString(),
            'truncated_prompt' => $log->getTruncatedPrompt(150),
        ];

        if ($includeFullData) {
            $data['request_data'] = $log->request_data;
            $data['response_data'] = $log->response_data;
            $data['serp_data'] = $log->serp_data;
            $data['serp_data_summary'] = $log->getSerpDataSummary();
        }

        return $data;
    }
}
