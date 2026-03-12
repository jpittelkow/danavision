<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_code',
        'response_body',
        'success',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'success' => 'boolean',
        ];
    }

    /**
     * Get the webhook that owns this delivery.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
