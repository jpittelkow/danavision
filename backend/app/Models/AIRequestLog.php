<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * AIRequestLog Model
 * 
 * Logs all AI API requests and responses for debugging and transparency.
 * Stores the full request/response data including SERP API data for price aggregation.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $ai_job_id
 * @property string $provider
 * @property string|null $model
 * @property string $request_type
 * @property array|null $request_data
 * @property array|null $response_data
 * @property string|null $error_message
 * @property int|null $tokens_input
 * @property int|null $tokens_output
 * @property int $duration_ms
 * @property string $status
 * @property array|null $serp_data
 * @property \Carbon\Carbon $created_at
 */
class AIRequestLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_request_logs';

    /**
     * Disable updated_at since we only use created_at.
     */
    public const UPDATED_AT = null;

    // Request statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TIMEOUT = 'timeout';

    // Request types
    public const TYPE_COMPLETION = 'completion';
    public const TYPE_IMAGE_ANALYSIS = 'image_analysis';
    public const TYPE_TEST_CONNECTION = 'test_connection';
    public const TYPE_PRICE_AGGREGATION = 'price_aggregation';

    /**
     * Human-readable labels for request types.
     */
    public const TYPE_LABELS = [
        self::TYPE_COMPLETION => 'Text Completion',
        self::TYPE_IMAGE_ANALYSIS => 'Image Analysis',
        self::TYPE_TEST_CONNECTION => 'Connection Test',
        self::TYPE_PRICE_AGGREGATION => 'Price Aggregation',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ai_job_id',
        'provider',
        'model',
        'request_type',
        'request_data',
        'response_data',
        'error_message',
        'tokens_input',
        'tokens_output',
        'duration_ms',
        'status',
        'serp_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'serp_data' => 'array',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the AI job this log belongs to (if any).
     */
    public function aiJob(): BelongsTo
    {
        return $this->belongsTo(AIJob::class, 'ai_job_id');
    }

    /**
     * Scope a query to only include logs for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include logs for a specific job.
     */
    public function scopeForJob(Builder $query, int $jobId): Builder
    {
        return $query->where('ai_job_id', $jobId);
    }

    /**
     * Scope a query to only include successful requests.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope a query to only include failed requests.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_TIMEOUT]);
    }

    /**
     * Scope a query to only include recent logs.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to filter by provider.
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope a query to filter by request type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('request_type', $type);
    }

    /**
     * Check if the request was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the request failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_TIMEOUT]);
    }

    /**
     * Get formatted duration string.
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        $seconds = $this->duration_ms / 1000;
        
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return $minutes . 'm ' . round($remainingSeconds) . 's';
    }

    /**
     * Get the total token count.
     */
    public function getTotalTokensAttribute(): int
    {
        return ($this->tokens_input ?? 0) + ($this->tokens_output ?? 0);
    }

    /**
     * Get a truncated version of the prompt for display.
     *
     * @param int $maxLength Maximum length of the truncated prompt
     */
    public function getTruncatedPrompt(int $maxLength = 200): string
    {
        $prompt = $this->request_data['prompt'] ?? '';
        
        if (strlen($prompt) <= $maxLength) {
            return $prompt;
        }

        return substr($prompt, 0, $maxLength) . '...';
    }

    /**
     * Get a summary of the SERP data (for price_aggregation requests).
     */
    public function getSerpDataSummary(): ?array
    {
        if (empty($this->serp_data)) {
            return null;
        }

        $results = $this->serp_data['shopping_results'] ?? $this->serp_data['results'] ?? [];
        
        return [
            'results_count' => count($results),
            'search_query' => $this->serp_data['search_parameters']['q'] ?? null,
            'engine' => $this->serp_data['search_metadata']['engine'] ?? 'unknown',
        ];
    }

    /**
     * Get the human-readable type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->request_type] ?? $this->request_type;
    }

    /**
     * Get the display name for the provider.
     */
    public function getProviderDisplayNameAttribute(): string
    {
        return match ($this->provider) {
            'claude' => 'Claude',
            'openai' => 'OpenAI',
            'gemini' => 'Gemini',
            'local' => 'Ollama (Local)',
            default => ucfirst($this->provider),
        };
    }

    /**
     * Create a new log entry for an AI request.
     *
     * @param int $userId The user ID
     * @param string $provider The AI provider
     * @param string $requestType The request type
     * @param array $requestData The request data
     * @param int|null $aiJobId Optional associated job ID
     * @param string|null $model The model name
     */
    public static function createLog(
        int $userId,
        string $provider,
        string $requestType,
        array $requestData,
        ?int $aiJobId = null,
        ?string $model = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'ai_job_id' => $aiJobId,
            'provider' => $provider,
            'model' => $model,
            'request_type' => $requestType,
            'request_data' => $requestData,
            'status' => self::STATUS_PENDING,
            'duration_ms' => 0,
        ]);
    }

    /**
     * Mark the log as successful with response data.
     *
     * @param array|null $responseData The response data
     * @param int $durationMs The request duration in milliseconds
     * @param int|null $tokensInput Input token count
     * @param int|null $tokensOutput Output token count
     * @param array|null $serpData Original SERP API data
     */
    public function markAsSuccess(
        ?array $responseData,
        int $durationMs,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
        ?array $serpData = null
    ): self {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'response_data' => $responseData,
            'duration_ms' => $durationMs,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'serp_data' => $serpData,
        ]);

        return $this;
    }

    /**
     * Mark the log as failed with error message.
     *
     * @param string $errorMessage The error message
     * @param int $durationMs The request duration in milliseconds
     */
    public function markAsFailed(string $errorMessage, int $durationMs): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
        ]);

        return $this;
    }

    /**
     * Mark the log as timed out.
     *
     * @param int $durationMs The request duration in milliseconds
     */
    public function markAsTimeout(int $durationMs): self
    {
        $this->update([
            'status' => self::STATUS_TIMEOUT,
            'error_message' => 'Request timed out',
            'duration_ms' => $durationMs,
        ]);

        return $this;
    }

    /**
     * Get usage statistics for a user.
     *
     * @param int $userId The user ID
     * @param int $days Number of days to look back
     */
    public static function getStatsForUser(int $userId, int $days = 30): array
    {
        $query = self::forUser($userId)->recent($days);

        $totalRequests = (clone $query)->count();
        $successfulRequests = (clone $query)->successful()->count();
        $failedRequests = (clone $query)->failed()->count();
        
        $totalTokens = (clone $query)->successful()
            ->selectRaw('COALESCE(SUM(tokens_input), 0) + COALESCE(SUM(tokens_output), 0) as total')
            ->value('total') ?? 0;

        $byProvider = (clone $query)
            ->selectRaw('provider, COUNT(*) as count')
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        $byType = (clone $query)
            ->selectRaw('request_type, COUNT(*) as count')
            ->groupBy('request_type')
            ->pluck('count', 'request_type')
            ->toArray();

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 1) : 0,
            'total_tokens' => (int) $totalTokens,
            'by_provider' => $byProvider,
            'by_type' => $byType,
        ];
    }
}
