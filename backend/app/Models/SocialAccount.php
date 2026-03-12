<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'provider_token_expires_at',
        'avatar',
        'nickname',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'provider_token',
        'provider_refresh_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'provider_token_expires_at' => 'datetime',
            'provider_token' => 'encrypted',
            'provider_refresh_token' => 'encrypted',
        ];
    }

    /**
     * The user this social account belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
