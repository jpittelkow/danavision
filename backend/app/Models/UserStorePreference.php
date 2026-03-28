<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStorePreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'priority',
        'enabled',
        'is_favorite',
        'location_id',
        'chain_name',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * The user who owns this preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The store this preference is for.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
