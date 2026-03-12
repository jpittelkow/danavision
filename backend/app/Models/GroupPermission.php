<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPermission extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'group_id',
        'permission',
        'resource_type',
        'resource_id',
    ];

    /**
     * The group this permission belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }
}
