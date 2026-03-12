<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class NotificationTemplate extends Model
{
    use Searchable;
    protected $fillable = [
        'type',
        'channel_group',
        'title',
        'body',
        'variables',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Invalidate notification type cache when templates change
        static::created(fn () => Cache::forget('notification_known_types'));
        static::created(fn () => Cache::forget('notification_category_type_map'));
        static::updated(fn () => Cache::forget('notification_known_types'));
        static::updated(fn () => Cache::forget('notification_category_type_map'));
    }

    /**
     * Scope to only active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only system templates.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Find a template by type and channel group (active only).
     */
    public static function findByTypeAndChannel(string $type, string $channelGroup): ?self
    {
        return static::where('type', $type)
            ->where('channel_group', $channelGroup)
            ->active()
            ->first();
    }

    /**
     * Get the indexable data array for the model (Scout/Meilisearch).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'channel_group' => $this->channel_group,
            'title' => $this->title,
            'body' => $this->body,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'notification_templates';
    }
}
