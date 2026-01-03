<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'query',
        'query_type',
        'results_count',
        'image_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
        ];
    }

    /**
     * Get the user who made this search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for text searches.
     */
    public function scopeText($query)
    {
        return $query->where('query_type', 'text');
    }

    /**
     * Scope for image searches.
     */
    public function scopeImage($query)
    {
        return $query->where('query_type', 'image');
    }
}
