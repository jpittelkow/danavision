<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationOrchestrator;

class SmartAddCompleteNotification
{
    public const TYPE = 'smart_add.complete';

    public const CHANNELS = ['database'];

    public function __construct(
        private int $productCount,
        private string $sourceType,
        private int $jobId,
    ) {}

    /**
     * Send the notification to the given user.
     */
    public function send(User $user): array
    {
        return app(NotificationOrchestrator::class)->sendByType(
            $user,
            self::TYPE,
            $this->toArray(),
            self::CHANNELS,
        );
    }

    /**
     * Get the array representation of the notification data.
     */
    public function toArray(): array
    {
        return [
            'product_count' => $this->productCount,
            'source_type' => $this->sourceType,
            'job_id' => $this->jobId,
        ];
    }
}
