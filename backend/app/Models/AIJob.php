<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIJob extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_jobs';

    /**
     * The attributes that are mass assignable.
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
     */
    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The user who owns this job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The related list item (if applicable).
     */
    public function relatedItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'related_item_id');
    }

    /**
     * The related shopping list (if applicable).
     */
    public function relatedList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'related_list_id');
    }

    /**
     * AI request logs associated with this job.
     */
    public function requestLogs(): HasMany
    {
        return $this->hasMany(AIRequestLog::class, 'ai_job_id');
    }

    /**
     * Check if the job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark the job as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the job as completed with the given output.
     */
    public function markCompleted(array $output): void
    {
        $this->update([
            'status' => 'completed',
            'output_data' => $output,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the job as failed with the given error message.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
