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
     */
    protected $fillable = [
        'user_id',
        'query',
        'query_type',
        'results_count',
        'image_path',
    ];

    /**
     * The user who performed this search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
