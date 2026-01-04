<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * SmartAddQueueItem Model
 * 
 * Represents a product identification result awaiting user review.
 * Users can review the AI-identified products and either add them to a list
 * or dismiss them. Items auto-expire after 7 days.
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property array $product_data
 * @property string $source_type
 * @property string|null $source_query
 * @property string|null $source_image_path
 * @property int|null $ai_job_id
 * @property int|null $added_item_id
 * @property int|null $selected_index
 * @property array|null $providers_used
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SmartAddQueueItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'smart_add_queue';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_ADDED = 'added';
    public const STATUS_DISMISSED = 'dismissed';

    // Source type constants
    public const SOURCE_IMAGE = 'image';
    public const SOURCE_TEXT = 'text';

    /**
     * Human-readable labels for statuses.
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending Review',
        self::STATUS_REVIEWED => 'Reviewed',
        self::STATUS_ADDED => 'Added to List',
        self::STATUS_DISMISSED => 'Dismissed',
    ];

    /**
     * Default expiration time in days.
     */
    public const DEFAULT_EXPIRATION_DAYS = 7;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'product_data',
        'source_type',
        'source_query',
        'source_image_path',
        'ai_job_id',
        'added_item_id',
        'selected_index',
        'providers_used',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'product_data' => 'array',
        'providers_used' => 'array',
        'selected_index' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this queue item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the AI job that produced this result.
     */
    public function aiJob(): BelongsTo
    {
        return $this->belongsTo(AIJob::class, 'ai_job_id');
    }

    /**
     * Get the list item that was created when this was added.
     */
    public function addedItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'added_item_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by pending status.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter by not expired items.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get items ready for review (pending and not expired).
     */
    public function scopeReadyForReview(Builder $query): Builder
    {
        return $query->pending()->notExpired();
    }

    /**
     * Check if the item is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the item has been added to a list.
     */
    public function isAdded(): bool
    {
        return $this->status === self::STATUS_ADDED;
    }

    /**
     * Check if the item has been dismissed.
     */
    public function isDismissed(): bool
    {
        return $this->status === self::STATUS_DISMISSED;
    }

    /**
     * Check if the item has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the top product suggestion.
     */
    public function getTopSuggestionAttribute(): ?array
    {
        $data = $this->product_data;
        if (is_array($data) && count($data) > 0) {
            return $data[0];
        }
        return null;
    }

    /**
     * Get the selected product (if user has selected one).
     */
    public function getSelectedProductAttribute(): ?array
    {
        if ($this->selected_index === null) {
            return null;
        }
        $data = $this->product_data;
        if (is_array($data) && isset($data[$this->selected_index])) {
            return $data[$this->selected_index];
        }
        return null;
    }

    /**
     * Get the number of product suggestions.
     */
    public function getSuggestionsCountAttribute(): int
    {
        $data = $this->product_data;
        return is_array($data) ? count($data) : 0;
    }

    /**
     * Get the human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Get display title for the queue item.
     */
    public function getDisplayTitleAttribute(): string
    {
        $top = $this->top_suggestion;
        if ($top && isset($top['product_name'])) {
            return $top['product_name'];
        }
        if ($this->source_type === self::SOURCE_TEXT && $this->source_query) {
            return $this->source_query;
        }
        return 'Product';
    }

    /**
     * Get display image URL for the queue item.
     */
    public function getDisplayImageAttribute(): ?string
    {
        // First, check if we have an uploaded source image
        if ($this->source_type === self::SOURCE_IMAGE && $this->source_image_path) {
            return asset('storage/' . $this->source_image_path);
        }
        
        // Otherwise, try to get image from top suggestion
        $top = $this->top_suggestion;
        if ($top && !empty($top['image_url'])) {
            return $top['image_url'];
        }
        
        return null;
    }

    /**
     * Mark the item as reviewed.
     */
    public function markAsReviewed(?int $selectedIndex = null): self
    {
        $this->update([
            'status' => self::STATUS_REVIEWED,
            'selected_index' => $selectedIndex,
        ]);
        return $this;
    }

    /**
     * Mark the item as added to a list.
     */
    public function markAsAdded(int $itemId, int $selectedIndex): self
    {
        $this->update([
            'status' => self::STATUS_ADDED,
            'added_item_id' => $itemId,
            'selected_index' => $selectedIndex,
        ]);
        return $this;
    }

    /**
     * Mark the item as dismissed.
     */
    public function markAsDismissed(): self
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
        ]);
        return $this;
    }

    /**
     * Create a new queue item from AI job results.
     *
     * @param int $userId The user ID
     * @param array $productData Array of product suggestions
     * @param string $sourceType 'image' or 'text'
     * @param string|null $sourceQuery Text query (for text source)
     * @param string|null $sourceImagePath Stored image path (for image source)
     * @param int|null $aiJobId The AI job that produced this result
     * @param array $providersUsed Array of provider names
     */
    public static function createFromJobResults(
        int $userId,
        array $productData,
        string $sourceType,
        ?string $sourceQuery = null,
        ?string $sourceImagePath = null,
        ?int $aiJobId = null,
        array $providersUsed = []
    ): self {
        return self::create([
            'user_id' => $userId,
            'status' => self::STATUS_PENDING,
            'product_data' => $productData,
            'source_type' => $sourceType,
            'source_query' => $sourceQuery,
            'source_image_path' => $sourceImagePath,
            'ai_job_id' => $aiJobId,
            'providers_used' => $providersUsed,
            'expires_at' => now()->addDays(self::DEFAULT_EXPIRATION_DAYS),
        ]);
    }

    /**
     * Clean up expired queue items.
     * Should be called periodically by a scheduled job.
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<=', now())->delete();
    }
}
