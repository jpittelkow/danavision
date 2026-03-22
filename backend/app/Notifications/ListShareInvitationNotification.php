<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationOrchestrator;

class ListShareInvitationNotification
{
    public const TYPE = 'list.share_invitation';

    public const CHANNELS = ['database', 'email'];

    public function __construct(
        private string $listName,
        private string $sharedByName,
        private string $permission,
        private int $listId,
        private int $shareId,
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
            'list_name' => $this->listName,
            'shared_by_name' => $this->sharedByName,
            'permission' => $this->permission,
            'list_id' => $this->listId,
            'share_id' => $this->shareId,
        ];
    }
}
