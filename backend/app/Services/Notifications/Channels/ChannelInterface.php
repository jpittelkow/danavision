<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;

interface ChannelInterface
{
    /**
     * Send a notification via this channel.
     */
    public function send(User $user, string $type, string $title, string $message, array $data = []): array;

    /**
     * Get the channel name.
     */
    public function getName(): string;

    /**
     * Check if the channel is available for the user.
     */
    public function isAvailableFor(User $user): bool;
}
