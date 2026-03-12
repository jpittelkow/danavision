<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'fields_accessed',
        'ip_address',
        'user_agent',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'fields_accessed' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
