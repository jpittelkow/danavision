<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Services\AI\AILoggingService;
use App\Services\AI\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * BaseAIJob
 * 
 * Abstract base class for all AI background jobs.
 * Handles common functionality like:
 * - AIJob model status updates
 * - Progress tracking
 * - Cancellation checking
 * - Error handling and logging
 * - AILoggingService integration
 */
abstract class BaseAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * The AIJob model ID.
     */
    protected int $aiJobId;

    /**
     * The user ID.
     */
    protected int $userId;

    /**
     * Create a new job instance.
     *
     * @param int $aiJobId The AIJob model ID
     * @param int $userId The user ID
     */
    public function __construct(int $aiJobId, int $userId)
    {
        $this->aiJobId = $aiJobId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $aiJob = AIJob::find($this->aiJobId);

        if (!$aiJob) {
            Log::warning('BaseAIJob: AIJob not found', ['ai_job_id' => $this->aiJobId]);
            return;
        }

        // Check if job was cancelled before starting
        if ($aiJob->isCancelled()) {
            Log::info('BaseAIJob: Job was cancelled before execution', ['ai_job_id' => $this->aiJobId]);
            return;
        }

        // Mark as processing
        $aiJob->markAsProcessing();

        try {
            // Execute the specific job logic
            $result = $this->process($aiJob);

            // Mark as completed with results
            $aiJob->markAsCompleted($result);

            Log::info('BaseAIJob: Job completed successfully', [
                'ai_job_id' => $this->aiJobId,
                'type' => $aiJob->type,
                'duration_ms' => $aiJob->duration_ms,
            ]);

        } catch (\Exception $e) {
            Log::error('BaseAIJob: Job failed', [
                'ai_job_id' => $this->aiJobId,
                'type' => $aiJob->type ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Mark as failed
            $aiJob->markAsFailed($e->getMessage());

            // Re-throw to trigger retry if attempts remain
            throw $e;
        }
    }

    /**
     * Process the job. Must be implemented by subclasses.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    abstract protected function process(AIJob $aiJob): ?array;

    /**
     * Check if the job has been cancelled.
     * Call this periodically during long-running operations.
     *
     * @param AIJob $aiJob The AIJob model
     * @return bool True if cancelled
     */
    protected function isCancelled(AIJob $aiJob): bool
    {
        // Refresh from database to get latest status
        $aiJob->refresh();
        return $aiJob->isCancelled();
    }

    /**
     * Update the job progress.
     *
     * @param AIJob $aiJob The AIJob model
     * @param int $progress Progress percentage (0-100)
     */
    protected function updateProgress(AIJob $aiJob, int $progress): void
    {
        $aiJob->updateProgress($progress);
    }

    /**
     * Get an AILoggingService instance for this job.
     *
     * @return AILoggingService|null
     */
    protected function getLoggingService(): ?AILoggingService
    {
        return AILoggingService::forUser($this->userId, $this->aiJobId);
    }

    /**
     * Get an AIService instance for this user.
     *
     * @return AIService|null
     */
    protected function getAIService(): ?AIService
    {
        return AIService::forUser($this->userId);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BaseAIJob: Job failed permanently', [
            'ai_job_id' => $this->aiJobId,
            'error' => $exception->getMessage(),
        ]);

        // Update the AIJob status if it exists
        $aiJob = AIJob::find($this->aiJobId);
        if ($aiJob && !$aiJob->isFailed()) {
            $aiJob->markAsFailed('Job failed after all retries: ' . $exception->getMessage());
        }
    }

    /**
     * Get the tags for the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'ai-job',
            'user:' . $this->userId,
            'ai_job_id:' . $this->aiJobId,
        ];
    }
}
