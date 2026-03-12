<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboarding extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_onboarding';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'wizard_completed_at',
        'wizard_dismissed_at',
        'steps_completed',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'wizard_completed_at' => 'datetime',
            'wizard_dismissed_at' => 'datetime',
            'steps_completed' => 'array',
        ];
    }

    /**
     * The user this onboarding record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the wizard has been completed.
     */
    public function isWizardCompleted(): bool
    {
        return $this->wizard_completed_at !== null;
    }

    /**
     * Check if the wizard has been dismissed.
     */
    public function isWizardDismissed(): bool
    {
        return $this->wizard_dismissed_at !== null;
    }

    /**
     * Check if a specific step has been completed.
     */
    public function isStepCompleted(string $step): bool
    {
        return in_array($step, $this->steps_completed ?? [], true);
    }

    /**
     * Mark a step as completed.
     */
    public function markStepComplete(string $step): void
    {
        $steps = $this->steps_completed ?? [];
        if (!in_array($step, $steps, true)) {
            $steps[] = $step;
            $this->steps_completed = $steps;
            $this->save();
        }
    }

    /**
     * Mark the wizard as completed.
     */
    public function completeWizard(): void
    {
        $this->wizard_completed_at = now();
        $this->save();
    }

    /**
     * Dismiss the wizard without completing it.
     */
    public function dismissWizard(): void
    {
        $this->wizard_dismissed_at = now();
        $this->save();
    }

    /**
     * Reset the onboarding state to show wizard again.
     */
    public function resetWizard(): void
    {
        $this->wizard_completed_at = null;
        $this->wizard_dismissed_at = null;
        $this->steps_completed = [];
        $this->save();
    }

    /**
     * Get or create an onboarding record for a user.
     */
    public static function forUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            ['steps_completed' => []]
        );
    }
}
