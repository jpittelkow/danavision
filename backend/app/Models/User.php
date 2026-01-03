<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's search history.
     */
    public function searchHistory(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    /**
     * Get the shopping lists owned by the user.
     */
    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    /**
     * Get the shopping lists shared with the user.
     */
    public function sharedLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class, 'list_shares')
            ->withPivot(['permission', 'accepted_at', 'shared_by_user_id'])
            ->withTimestamps()
            ->wherePivotNotNull('accepted_at');
    }

    /**
     * Get the user's settings.
     */
    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class);
    }

    /**
     * Get the user's AI providers.
     */
    public function aiProviders(): HasMany
    {
        return $this->hasMany(AIProvider::class);
    }

    /**
     * Get the user's custom AI prompts.
     */
    public function aiPrompts(): HasMany
    {
        return $this->hasMany(AIPrompt::class);
    }

    /**
     * Get the user's primary AI provider.
     */
    public function primaryAIProvider(): HasOne
    {
        return $this->hasOne(AIProvider::class)->where('is_primary', true);
    }

    /**
     * Get all lists accessible to the user (owned + shared).
     */
    public function accessibleLists()
    {
        return ShoppingList::where('user_id', $this->id)
            ->orWhereHas('shares', function ($query) {
                $query->where('user_id', $this->id)
                    ->whereNotNull('accepted_at');
            });
    }
}
