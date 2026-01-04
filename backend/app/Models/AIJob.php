<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * AIJob Model
 * 
 * Represents a background AI job (product identification, price search, etc.)
 * that runs asynchronously and can be tracked/cancelled by the user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property array|null $input_data
 * @property array|null $output_data
 * @property string|null $error_message
 * @property int $progress
 * @property int|null $related_item_id
 * @property int|null $related_list_id
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AIJob extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_jobs';

    // Job types
    public const TYPE_PRODUCT_IDENTIFICATION = 'product_identification';
    public const TYPE_IMAGE_ANALYSIS = 'image_analysis';
    public const TYPE_PRICE_SEARCH = 'price_search';
    public const TYPE_SMART_FILL = 'smart_fill';
    public const TYPE_PRICE_REFRESH = 'price_refresh';
    public const TYPE_FIRECRAWL_DISCOVERY = 'firecrawl_discovery';
    public const TYPE_FIRECRAWL_REFRESH = 'firecrawl_refresh';

    // Job statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Human-readable labels for job types.
     */
    public const TYPE_LABELS = [
        self::TYPE_PRODUCT_IDENTIFICATION => 'Product Identification',
        self::TYPE_IMAGE_ANALYSIS => 'Image Analysis',
        self::TYPE_PRICE_SEARCH => 'Price Search',
        self::TYPE_SMART_FILL => 'Smart Fill',
        self::TYPE_PRICE_REFRESH => 'Price Refresh',
        self::TYPE_FIRECRAWL_DISCOVERY => 'Firecrawl Price Discovery',
        self::TYPE_FIRECRAWL_REFRESH => 'Firecrawl Price Refresh',
    ];

    /**
     * Human-readable labels for job statuses.
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'input_data',
        'output_data',
        'error_message',
        'progress',
        'related_item_id',
        'related_list_id',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related list item (if any).
     */
    public function relatedItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'related_item_id');
    }

    /**
     * Get the related shopping list (if any).
     */
    public function relatedList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'related_list_id');
    }

    /**
     * Get all request logs for this job.
     */
    public function requestLogs(): HasMany
    {
        return $this->hasMany(AIRequestLog::class, 'ai_job_id');
    }

    /**
     * Scope a query to only include jobs for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include active (pending/processing) jobs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Scope a query to only include completed jobs.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include failed jobs.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include recent jobs.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to filter by job type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the job is active (pending or processing).
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if the job can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the job has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the job was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Mark the job as processing.
     */
    public function markAsProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the job as completed with output data.
     *
     * @param array|null $outputData The job output data
     */
    public function markAsCompleted(?array $outputData = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'output_data' => $outputData,
            'progress' => 100,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the job as failed with error message.
     *
     * @param string $errorMessage The error message
     * @param array|null $partialOutput Any partial output data
     */
    public function markAsFailed(string $errorMessage, ?array $partialOutput = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'output_data' => $partialOutput,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the job as cancelled.
     */
    public function markAsCancelled(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Update the job progress with optional status message and logs.
     *
     * @param int $progress Progress percentage (0-100)
     * @param string|null $statusMessage Optional status message to display
     * @param array|null $logs Optional array of log entries to store
     */
    public function updateProgress(int $progress, ?string $statusMessage = null, ?array $logs = null): self
    {
        $updates = [
            'progress' => min(100, max(0, $progress)),
        ];

        // Store logs and status message in output_data without overwriting other data
        if ($statusMessage !== null || $logs !== null) {
            $currentOutput = $this->output_data ?? [];
            
            if ($statusMessage !== null) {
                $currentOutput['status_message'] = $statusMessage;
            }
            
            if ($logs !== null) {
                $currentOutput['progress_logs'] = $logs;
            }
            
            $updates['output_data'] = $currentOutput;
        }

        $this->update($updates);

        return $this;
    }

    /**
     * Get the human-readable type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Get the human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Get a summary of the input data for display.
     */
    public function getInputSummaryAttribute(): string
    {
        if (empty($this->input_data)) {
            return '';
        }

        // Return a brief summary based on job type
        return match ($this->type) {
            self::TYPE_PRODUCT_IDENTIFICATION => $this->input_data['query'] ?? $this->input_data['product_name'] ?? 'Image analysis',
            self::TYPE_IMAGE_ANALYSIS => 'Image analysis',
            self::TYPE_PRICE_SEARCH => $this->input_data['query'] ?? $this->input_data['product_name'] ?? 'Price search',
            self::TYPE_SMART_FILL => $this->input_data['product_name'] ?? 'Smart fill',
            self::TYPE_PRICE_REFRESH => $this->input_data['product_name'] ?? 'Price refresh',
            self::TYPE_FIRECRAWL_DISCOVERY => $this->input_data['product_name'] ?? 'Firecrawl discovery',
            self::TYPE_FIRECRAWL_REFRESH => $this->input_data['product_name'] ?? 'Firecrawl refresh',
            default => json_encode(array_slice($this->input_data, 0, 2)),
        };
    }

    /**
     * Get the duration of the job in milliseconds.
     */
    public function getDurationMsAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return (int) $this->started_at->diffInMilliseconds($endTime);
    }

    /**
     * Get formatted duration string.
     */
    public function getFormattedDurationAttribute(): string
    {
        $durationMs = $this->duration_ms;
        
        if ($durationMs === null) {
            return '-';
        }

        if ($durationMs < 1000) {
            return $durationMs . 'ms';
        }

        $seconds = $durationMs / 1000;
        
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return $minutes . 'm ' . round($remainingSeconds) . 's';
    }

    /**
     * Create a new AI job for a user.
     *
     * @param int $userId The user ID
     * @param string $type The job type
     * @param array $inputData The input data
     * @param int|null $relatedItemId Optional related item ID
     * @param int|null $relatedListId Optional related list ID
     */
    public static function createJob(
        int $userId,
        string $type,
        array $inputData,
        ?int $relatedItemId = null,
        ?int $relatedListId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'status' => self::STATUS_PENDING,
            'input_data' => $inputData,
            'progress' => 0,
            'related_item_id' => $relatedItemId,
            'related_list_id' => $relatedListId,
        ]);
    }
}
