<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'command',
        'user_id',
        'status',
        'started_at',
        'completed_at',
        'output',
        'error',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who triggered the run (null if scheduled).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
