<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationOrchestrator;

class PriceDropNotification
{
    public const TYPE = 'price.drop';

    public const CHANNELS = ['database', 'email'];

    public function __construct(
        private string $itemName,
        private float $oldPrice,
        private float $newPrice,
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
            'old_price' => $this->oldPrice,
            'new_price' => $this->newPrice,
            'list_name' => $this->listName,
            'list_id' => $this->listId,
            'item_id' => $this->itemId,
        ];
    }
}
