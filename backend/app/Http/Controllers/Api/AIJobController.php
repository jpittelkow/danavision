<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\AIJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIJobController extends Controller
{
    use ApiResponseTrait;

    /**
     * List user's AI jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,processing,completed,failed,cancelled'],
        ]);

        $query = AIJob::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $jobs = $query->limit(50)->get();

        return response()->json(['data' => $jobs]);
    }

    /**
     * Show AI job details.
     */
    public function show(Request $request, AIJob $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        return response()->json(['data' => $job]);
    }

    /**
     * Cancel a pending or processing AI job.
     */
    public function cancel(Request $request, AIJob $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        if (!in_array($job->status, ['pending', 'processing'])) {
            return $this->errorResponse('Only pending or processing jobs can be cancelled', 422);
        }

        $job->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return $this->successResponse('Job cancelled', ['data' => $job]);
    }

    /**
     * Authorize that the current user owns the AI job.
     */
    private function authorizeJobAccess(Request $request, AIJob $job): void
    {
        if ($job->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this job.');
        }
    }
}
