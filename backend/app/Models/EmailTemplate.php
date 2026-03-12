<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Searchable;

class EmailTemplate extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'key',
        'name',
        'description',
        'subject',
        'body_html',
        'body_text',
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
     * Find a template by key (active only).
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->active()->first();
    }

    /**
     * Get the indexable data array for the model (Scout/Meilisearch).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'subject' => $this->subject,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'email_templates';
    }
}
