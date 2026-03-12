<?php

namespace App\Events;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification,
        public User $user
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('user.'.$this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NotificationSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $n = $this->notification;

        return [
            'id' => $n->id,
            'user_id' => $n->user_id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'updated_at' => $n->updated_at->toIso8601String(),
        ];
    }
}
