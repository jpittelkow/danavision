<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationOrchestrator;

class AllTimeLowNotification
{
    public const TYPE = 'price.all_time_low';

    public const CHANNELS = ['database', 'email'];

    public function __construct(
        private string $itemName,
        private float $newLowPrice,
        private ?float $previousLow,
        private string $retailer,
        private string $listName,
        private int $listId,
        private int $itemId,
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
            'item_name' => $this->itemName,
            'new_low_price' => $this->newLowPrice,
            'previous_low' => $this->previousLow,
            'retailer' => $this->retailer,
            'list_name' => $this->listName,
            'list_id' => $this->listId,
            'item_id' => $this->itemId,
        ];
    }
}
