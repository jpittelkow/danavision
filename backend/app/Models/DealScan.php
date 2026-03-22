<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_job_id',
        'image_path',
        'scan_type',
        'deals_extracted',
        'deals_accepted',
        'deals_dismissed',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiJob(): BelongsTo
    {
        return $this->belongsTo(AIJob::class, 'ai_job_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(ScannedDeal::class, 'source_scan_id');
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function incrementAccepted(): void
    {
        $this->increment('deals_accepted');
    }

    public function incrementDismissed(): void
    {
        $this->increment('deals_dismissed');
    }
}
