<?php

namespace App\Http\Controllers;

use App\Jobs\AI\FirecrawlRefreshJob;
use App\Jobs\AI\ProductIdentificationJob;
use App\Jobs\AI\SmartFillJob;
use App\Models\AIJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * AIJobController
 * 
 * Handles API endpoints for managing AI background jobs.
 * Provides listing, creation, and cancellation of jobs.
 */
class AIJobController extends Controller
{
    /**
     * List AI jobs for the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIJob::class);

        $query = AIJob::forUser($user->id)
            ->withCount('requestLogs')
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter active only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Limit results
        $limit = min($request->integer('limit', 50), 100);
        $jobs = $query->take($limit)->get();

        return response()->json([
            'jobs' => $jobs->map(fn(AIJob $job) => $this->formatJob($job)),
        ]);
    }

    /**
     * Get only active jobs (for polling).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIJob::class);

        $jobs = AIJob::forUser($user->id)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'jobs' => $jobs->map(fn(AIJob $job) => $this->formatJob($job)),
            'count' => $jobs->count(),
        ]);
    }

    /**
     * Get a single job with details.
     *
     * @param AIJob $aiJob
     * @return JsonResponse
     */
    public function show(AIJob $aiJob): JsonResponse
    {
        Gate::authorize('view', $aiJob);

        $aiJob->load('requestLogs');

        return response()->json([
            'job' => $this->formatJob($aiJob, true),
        ]);
    }

    /**
     * Create and dispatch a new AI job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', AIJob::class);

        $request->validate([
            'type' => ['required', 'string', 'in:product_identification,image_analysis,smart_fill,price_refresh'],
            'input_data' => ['required', 'array'],
            'related_item_id' => ['nullable', 'integer', 'exists:list_items,id'],
            'related_list_id' => ['nullable', 'integer', 'exists:shopping_lists,id'],
        ]);

        $user = $request->user();

        // Create the job record
        $aiJob = AIJob::createJob(
            userId: $user->id,
            type: $request->input('type'),
            inputData: $request->input('input_data'),
            relatedItemId: $request->input('related_item_id'),
            relatedListId: $request->input('related_list_id'),
        );

        // Dispatch the appropriate job
        $jobClass = match ($aiJob->type) {
            AIJob::TYPE_PRODUCT_IDENTIFICATION, AIJob::TYPE_IMAGE_ANALYSIS => ProductIdentificationJob::class,
            AIJob::TYPE_SMART_FILL => SmartFillJob::class,
            AIJob::TYPE_PRICE_REFRESH => FirecrawlRefreshJob::class,
            default => throw new \InvalidArgumentException("Unknown job type: {$aiJob->type}"),
        };

        dispatch(new $jobClass($aiJob->id, $user->id));

        return response()->json([
            'job' => $this->formatJob($aiJob),
            'message' => 'Job created successfully',
        ], 201);
    }

    /**
     * Cancel an active job.
     *
     * @param AIJob $aiJob
     * @return JsonResponse
     */
    public function cancel(AIJob $aiJob): JsonResponse
    {
        Gate::authorize('cancel', $aiJob);

        if (!$aiJob->canBeCancelled()) {
            return response()->json([
                'message' => 'This job cannot be cancelled.',
            ], 400);
        }

        $aiJob->markAsCancelled();

        return response()->json([
            'job' => $this->formatJob($aiJob),
            'message' => 'Job cancelled successfully',
        ]);
    }

    /**
     * Delete a job from history.
     *
     * @param AIJob $aiJob
     * @return JsonResponse
     */
    public function destroy(AIJob $aiJob): JsonResponse
    {
        Gate::authorize('delete', $aiJob);

        // Don't allow deleting active jobs
        if ($aiJob->isActive()) {
            return response()->json([
                'message' => 'Cannot delete an active job. Cancel it first.',
            ], 400);
        }

        $aiJob->delete();

        return response()->json([
            'message' => 'Job deleted successfully',
        ]);
    }

    /**
     * Clear all completed/failed/cancelled jobs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIJob::class);

        $deleted = AIJob::forUser($user->id)
            ->whereNotIn('status', [AIJob::STATUS_PENDING, AIJob::STATUS_PROCESSING])
            ->delete();

        return response()->json([
            'message' => "Deleted {$deleted} jobs from history",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Get job statistics for the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', AIJob::class);

        $days = $request->integer('days', 30);
        $query = AIJob::forUser($user->id)->recent($days);

        $total = (clone $query)->count();
        $completed = (clone $query)->completed()->count();
        $failed = (clone $query)->failed()->count();
        $cancelled = (clone $query)->where('status', AIJob::STATUS_CANCELLED)->count();
        $active = AIJob::forUser($user->id)->active()->count();

        $byType = (clone $query)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return response()->json([
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'active' => $active,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'by_type' => $byType,
        ]);
    }

    /**
     * Format a job for API response.
     *
     * @param AIJob $job
     * @param bool $includeDetails Whether to include full details
     * @return array
     */
    protected function formatJob(AIJob $job, bool $includeDetails = false): array
    {
        $data = [
            'id' => $job->id,
            'type' => $job->type,
            'type_label' => $job->type_label,
            'status' => $job->status,
            'status_label' => $job->status_label,
            'progress' => $job->progress,
            'input_summary' => $job->input_summary,
            'error_message' => $job->error_message,
            'related_item_id' => $job->related_item_id,
            'related_list_id' => $job->related_list_id,
            'started_at' => $job->started_at?->toISOString(),
            'completed_at' => $job->completed_at?->toISOString(),
            'created_at' => $job->created_at->toISOString(),
            'duration_ms' => $job->duration_ms,
            'formatted_duration' => $job->formatted_duration,
            'can_cancel' => $job->canBeCancelled(),
            'logs_count' => $job->request_logs_count ?? $job->requestLogs()->count(),
        ];

        // Always include output_data for crawl/discovery jobs so frontend can display logs
        // This is needed for the CrawlLogViewer component to work properly
        if ($this->isCrawlJob($job->type) || $includeDetails) {
            $data['output_data'] = $job->output_data;
        }

        if ($includeDetails) {
            $data['input_data'] = $job->input_data;
            $data['logs'] = $job->requestLogs->map(fn($log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'model' => $log->model,
                'request_type' => $log->request_type,
                'status' => $log->status,
                'duration_ms' => $log->duration_ms,
                'formatted_duration' => $log->formatted_duration,
                'tokens_input' => $log->tokens_input,
                'tokens_output' => $log->tokens_output,
                'created_at' => $log->created_at->toISOString(),
            ]);
        }

        return $data;
    }

    /**
     * Check if a job type is a crawl/discovery job that benefits from log display.
     *
     * @param string $jobType
     * @return bool
     */
    protected function isCrawlJob(string $jobType): bool
    {
        return in_array($jobType, [
            AIJob::TYPE_FIRECRAWL_DISCOVERY,
            AIJob::TYPE_FIRECRAWL_REFRESH,
            AIJob::TYPE_PRICE_SEARCH,
            AIJob::TYPE_PRICE_REFRESH,
            AIJob::TYPE_NEARBY_STORE_DISCOVERY,
            AIJob::TYPE_STORE_AUTO_CONFIG,
        ], true);
    }
}
