<?php

namespace App\Events;

use App\Models\AuditLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AuditLog $log
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('audit-logs'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AuditLogCreated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $log = $this->log;

        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'action' => $log->action,
            'severity' => $log->severity ?? 'info',
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'correlation_id' => $log->correlation_id,
            'created_at' => $log->created_at->toIso8601String(),
            'user' => $log->user?->only(['id', 'name', 'email']),
        ];
    }
}
