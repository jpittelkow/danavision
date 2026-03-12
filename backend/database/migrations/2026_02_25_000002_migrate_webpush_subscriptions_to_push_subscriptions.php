<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = DB::table('settings')
            ->where('group', 'notifications')
            ->where('key', 'webpush_subscription')
            ->get();

        foreach ($subscriptions as $row) {
            $data = is_string($row->value) ? json_decode($row->value, true) : $row->value;

            if (!$data || !isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
                continue;
            }

            DB::table('push_subscriptions')->insert([
                'user_id' => $row->user_id,
                'endpoint' => $data['endpoint'],
                'endpoint_hash' => hash('sha256', $data['endpoint']),
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'device_name' => null,
                'user_agent' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'last_used_at' => null,
            ]);
        }

        DB::table('settings')
            ->where('group', 'notifications')
            ->where('key', 'webpush_subscription')
            ->delete();
    }

    public function down(): void
    {
        // Data migration — no automatic rollback.
        // Existing push_subscriptions rows would be dropped by the table migration rollback.
    }
};
