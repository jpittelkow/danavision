<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class ShoppingList extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'notify_on_any_drop',
        'notify_on_threshold',
        'threshold_percent',
        'shop_local',
        'last_refreshed_at',
        'analysis_data',
        'last_analyzed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notify_on_any_drop' => 'boolean',
            'notify_on_threshold' => 'boolean',
            'shop_local' => 'boolean',
            'last_refreshed_at' => 'datetime',
            'analysis_data' => 'array',
            'last_analyzed_at' => 'datetime',
        ];
    }

    /**
     * The user who owns this shopping list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Items on this shopping list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }

    /**
     * Share records for this list.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ListShare::class);
    }

    /**
     * Users this list is shared with.
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_shares')
            ->withPivot('permission', 'accepted_at', 'declined_at', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get the indexable data array for the model (Scout/Meilisearch).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->user_id,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'shopping_lists';
    }
}
