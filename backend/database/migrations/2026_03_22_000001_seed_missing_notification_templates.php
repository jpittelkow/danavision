<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach (self::allDefaults() as $attrs) {
            $rows[] = array_merge($attrs, [
                'variables' => json_encode($attrs['variables']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('notification_templates')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Only remove templates that were missing before this migration.
        // The 2026_02_22 migration already seeded email templates for the
        // original set (backup, auth, system, llm, storage, suspicious_activity,
        // usage, payment types), so we only remove the newer types + non-email
        // channel groups that this migration adds.
        $newerTypes = [
            'price.drop',
            'price.all_time_low',
            'list.share_invitation',
            'smart_add.complete',
        ];

        // Remove all channel groups for newer types
        DB::table('notification_templates')
            ->whereIn('type', $newerTypes)
            ->delete();

        // Remove non-email channel groups for original types
        DB::table('notification_templates')
            ->whereIn('channel_group', ['push', 'inapp', 'chat'])
            ->delete();
    }

    /**
     * All notification template defaults across all channel groups.
     * Uses insertOrIgnore so already-seeded rows are skipped.
     */
    private static function allDefaults(): array
    {
        return [
            // backup.completed
            ['type' => 'backup.completed', 'channel_group' => 'push', 'title' => '{{app_name}}: Backup complete', 'body' => 'Backup "{{backup_name}}" finished successfully.', 'variables' => ['app_name', 'backup_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.completed', 'channel_group' => 'inapp', 'title' => 'Backup complete', 'body' => 'Backup "{{backup_name}}" finished successfully.', 'variables' => ['app_name', 'backup_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.completed', 'channel_group' => 'chat', 'title' => '{{app_name}}: Backup complete', 'body' => 'Backup "{{backup_name}}" finished successfully.', 'variables' => ['app_name', 'backup_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.completed', 'channel_group' => 'email', 'title' => '{{app_name}}: Backup complete', 'body' => '<p>Hi {{user.name}},</p><p>Backup "{{backup_name}}" finished successfully.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'backup_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            // backup.failed
            ['type' => 'backup.failed', 'channel_group' => 'push', 'title' => '{{app_name}}: Backup failed', 'body' => 'Backup "{{backup_name}}" failed: {{error_message}}', 'variables' => ['app_name', 'backup_name', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.failed', 'channel_group' => 'inapp', 'title' => 'Backup failed', 'body' => 'Backup "{{backup_name}}" failed: {{error_message}}', 'variables' => ['app_name', 'backup_name', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.failed', 'channel_group' => 'chat', 'title' => '{{app_name}}: Backup failed', 'body' => 'Backup "{{backup_name}}" failed: {{error_message}}', 'variables' => ['app_name', 'backup_name', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'backup.failed', 'channel_group' => 'email', 'title' => '{{app_name}}: Backup failed', 'body' => '<p>Hi {{user.name}},</p><p>Backup "{{backup_name}}" failed:</p><p>{{error_message}}</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'backup_name', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            // auth.login
            ['type' => 'auth.login', 'channel_group' => 'push', 'title' => '{{app_name}}: New sign-in', 'body' => 'New sign-in from {{ip}} at {{timestamp}}.', 'variables' => ['app_name', 'ip', 'timestamp', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.login', 'channel_group' => 'inapp', 'title' => 'New sign-in', 'body' => 'New sign-in from {{ip}} at {{timestamp}}.', 'variables' => ['app_name', 'ip', 'timestamp', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.login', 'channel_group' => 'chat', 'title' => '{{app_name}}: New sign-in', 'body' => 'New sign-in from {{ip}} at {{timestamp}}.', 'variables' => ['app_name', 'ip', 'timestamp', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.login', 'channel_group' => 'email', 'title' => '{{app_name}}: New sign-in', 'body' => '<p>Hi {{user.name}},</p><p>A new sign-in to your account was detected from {{ip}} at {{timestamp}}.</p><p>If this wasn\'t you, please change your password immediately.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'ip', 'timestamp', 'user.name'], 'is_system' => true, 'is_active' => true],
            // auth.password_reset
            ['type' => 'auth.password_reset', 'channel_group' => 'push', 'title' => '{{app_name}}: Password changed', 'body' => 'Your password was changed. If this wasn\'t you, contact support.', 'variables' => ['app_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.password_reset', 'channel_group' => 'inapp', 'title' => 'Password changed', 'body' => 'Your password was changed. If this wasn\'t you, contact support.', 'variables' => ['app_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.password_reset', 'channel_group' => 'chat', 'title' => '{{app_name}}: Password changed', 'body' => 'Your password was changed. If this wasn\'t you, contact support.', 'variables' => ['app_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'auth.password_reset', 'channel_group' => 'email', 'title' => '{{app_name}}: Password changed', 'body' => '<p>Hi {{user.name}},</p><p>Your password was changed. If this wasn\'t you, contact support immediately.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'user.name'], 'is_system' => true, 'is_active' => true],
            // system.update
            ['type' => 'system.update', 'channel_group' => 'push', 'title' => '{{app_name}}: Update available', 'body' => 'A new version ({{version}}) is ready to install.', 'variables' => ['app_name', 'version', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'system.update', 'channel_group' => 'inapp', 'title' => 'Update available', 'body' => 'A new version ({{version}}) is ready to install.', 'variables' => ['app_name', 'version', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'system.update', 'channel_group' => 'chat', 'title' => '{{app_name}}: Update available', 'body' => 'A new version ({{version}}) is ready to install.', 'variables' => ['app_name', 'version', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'system.update', 'channel_group' => 'email', 'title' => '{{app_name}}: Update available', 'body' => '<p>Hi {{user.name}},</p><p>A new version ({{version}}) is ready to install.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'version', 'user.name'], 'is_system' => true, 'is_active' => true],
            // llm.quota_warning
            ['type' => 'llm.quota_warning', 'channel_group' => 'push', 'title' => '{{app_name}}: Quota warning', 'body' => 'You have used {{usage}}% of your API quota.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'llm.quota_warning', 'channel_group' => 'inapp', 'title' => 'Quota warning', 'body' => 'You have used {{usage}}% of your API quota.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'llm.quota_warning', 'channel_group' => 'chat', 'title' => '{{app_name}}: Quota warning', 'body' => 'You have used {{usage}}% of your API quota.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'llm.quota_warning', 'channel_group' => 'email', 'title' => '{{app_name}}: Quota warning', 'body' => '<p>Hi {{user.name}},</p><p>You have used {{usage}}% of your API quota.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
            // storage.warning
            ['type' => 'storage.warning', 'channel_group' => 'push', 'title' => '{{app_name}}: Storage warning', 'body' => 'Storage usage is at {{usage}}% (threshold: {{threshold}}%). Free: {{free_formatted}} of {{total_formatted}}.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.warning', 'channel_group' => 'inapp', 'title' => 'Storage warning', 'body' => 'Storage usage is at {{usage}}% (threshold: {{threshold}}%). Free: {{free_formatted}} of {{total_formatted}}.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.warning', 'channel_group' => 'chat', 'title' => '{{app_name}}: Storage warning', 'body' => 'Storage usage is at {{usage}}% (threshold: {{threshold}}%). Free: {{free_formatted}} of {{total_formatted}}.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.warning', 'channel_group' => 'email', 'title' => '{{app_name}}: Storage warning', 'body' => '<p>Hi {{user.name}},</p><p>Storage usage is at {{usage}}% (threshold: {{threshold}}%).</p><p>Free: {{free_formatted}} of {{total_formatted}}.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            // storage.critical
            ['type' => 'storage.critical', 'channel_group' => 'push', 'title' => '{{app_name}}: Storage critical', 'body' => 'Storage usage is at {{usage}}% (critical). Free: {{free_formatted}} of {{total_formatted}}. Take action immediately.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.critical', 'channel_group' => 'inapp', 'title' => 'Storage critical', 'body' => 'Storage usage is at {{usage}}% (critical). Free: {{free_formatted}} of {{total_formatted}}. Take action immediately.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.critical', 'channel_group' => 'chat', 'title' => '{{app_name}}: Storage critical', 'body' => 'Storage usage is at {{usage}}% (critical). Free: {{free_formatted}} of {{total_formatted}}. Take action immediately.', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'storage.critical', 'channel_group' => 'email', 'title' => '{{app_name}}: Storage critical', 'body' => '<p>Hi {{user.name}},</p><p><strong>Storage usage is at {{usage}}% (critical).</strong></p><p>Free: {{free_formatted}} of {{total_formatted}}. Take action immediately.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'usage', 'threshold', 'free_formatted', 'total_formatted', 'user.name'], 'is_system' => true, 'is_active' => true],
            // suspicious_activity
            ['type' => 'suspicious_activity', 'channel_group' => 'push', 'title' => '{{app_name}}: Suspicious activity', 'body' => 'Suspicious activity detected: {{alert_summary}}', 'variables' => ['app_name', 'alert_summary', 'alert_count', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'suspicious_activity', 'channel_group' => 'inapp', 'title' => 'Suspicious activity detected', 'body' => '{{alert_count}} suspicious pattern(s) detected: {{alert_summary}}', 'variables' => ['app_name', 'alert_summary', 'alert_count', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'suspicious_activity', 'channel_group' => 'chat', 'title' => '{{app_name}}: Suspicious activity', 'body' => '{{alert_count}} suspicious pattern(s) detected: {{alert_summary}}', 'variables' => ['app_name', 'alert_summary', 'alert_count', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'suspicious_activity', 'channel_group' => 'email', 'title' => '{{app_name}}: Suspicious activity detected', 'body' => '<p>Hi {{user.name}},</p><p><strong>{{alert_count}} suspicious pattern(s) detected:</strong></p><p>{{alert_summary}}</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'alert_summary', 'alert_count', 'user.name'], 'is_system' => true, 'is_active' => true],
            // usage.budget_warning
            ['type' => 'usage.budget_warning', 'channel_group' => 'push', 'title' => '{{app_name}}: {{integration}} budget warning', 'body' => '{{integration}} usage at {{percent}}% of monthly budget (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_warning', 'channel_group' => 'inapp', 'title' => '{{integration}} budget warning', 'body' => '{{integration}} usage has reached {{percent}}% of the monthly budget (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_warning', 'channel_group' => 'chat', 'title' => '{{app_name}}: {{integration}} budget warning', 'body' => '{{integration}} usage has reached {{percent}}% of the monthly budget (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_warning', 'channel_group' => 'email', 'title' => '{{app_name}}: {{integration}} budget warning', 'body' => '<p>Hi {{user.name}},</p><p>{{integration}} usage has reached {{percent}}% of the monthly budget (${{current_cost}} of ${{budget}}).</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            // usage.budget_exceeded
            ['type' => 'usage.budget_exceeded', 'channel_group' => 'push', 'title' => '{{app_name}}: {{integration}} budget exceeded', 'body' => '{{integration}} budget exceeded: {{percent}}% used (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_exceeded', 'channel_group' => 'inapp', 'title' => '{{integration}} budget exceeded', 'body' => '{{integration}} usage has exceeded the monthly budget at {{percent}}% (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_exceeded', 'channel_group' => 'chat', 'title' => '{{app_name}}: {{integration}} budget exceeded', 'body' => '{{integration}} usage has exceeded the monthly budget at {{percent}}% (${{current_cost}} of ${{budget}}).', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'usage.budget_exceeded', 'channel_group' => 'email', 'title' => '{{app_name}}: {{integration}} budget exceeded', 'body' => '<p>Hi {{user.name}},</p><p><strong>{{integration}} usage has exceeded the monthly budget</strong> at {{percent}}% (${{current_cost}} of ${{budget}}).</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'integration', 'percent', 'current_cost', 'budget', 'user.name'], 'is_system' => true, 'is_active' => true],
            // payment.succeeded
            ['type' => 'payment.succeeded', 'channel_group' => 'push', 'title' => '{{app_name}}: Payment received', 'body' => 'Payment of {{amount}} {{currency}} succeeded.', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.succeeded', 'channel_group' => 'inapp', 'title' => 'Payment received', 'body' => 'Payment of {{amount}} {{currency}} succeeded. {{description}}', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.succeeded', 'channel_group' => 'chat', 'title' => '{{app_name}}: Payment received', 'body' => 'Payment of {{amount}} {{currency}} succeeded. Description: {{description}}. Payment ID: {{payment_id}}.', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.succeeded', 'channel_group' => 'email', 'title' => '{{app_name}}: Payment received', 'body' => '<p>Hi {{user.name}},</p><p>Payment of {{amount}} {{currency}} succeeded.</p><p>{{description}}</p><p>Payment ID: {{payment_id}}</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            // payment.failed
            ['type' => 'payment.failed', 'channel_group' => 'push', 'title' => '{{app_name}}: Payment failed', 'body' => 'Payment of {{amount}} {{currency}} failed.', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.failed', 'channel_group' => 'inapp', 'title' => 'Payment failed', 'body' => 'Payment of {{amount}} {{currency}} failed. {{error_message}}', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.failed', 'channel_group' => 'chat', 'title' => '{{app_name}}: Payment failed', 'body' => 'Payment of {{amount}} {{currency}} failed. Error: {{error_message}}. Payment ID: {{payment_id}}.', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.failed', 'channel_group' => 'email', 'title' => '{{app_name}}: Payment failed', 'body' => '<p>Hi {{user.name}},</p><p>Payment of {{amount}} {{currency}} failed.</p><p>{{error_message}}</p><p>Payment ID: {{payment_id}}</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'amount', 'currency', 'description', 'customer_email', 'payment_id', 'error_message', 'user.name'], 'is_system' => true, 'is_active' => true],
            // payment.refunded
            ['type' => 'payment.refunded', 'channel_group' => 'push', 'title' => '{{app_name}}: Payment refunded', 'body' => 'Refund of {{refund_amount}} {{currency}} processed.', 'variables' => ['app_name', 'amount', 'refund_amount', 'currency', 'description', 'customer_email', 'payment_id', 'refund_type', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.refunded', 'channel_group' => 'inapp', 'title' => 'Payment refunded', 'body' => '{{refund_type}} of {{refund_amount}} {{currency}} processed for payment #{{payment_id}}.', 'variables' => ['app_name', 'amount', 'refund_amount', 'currency', 'description', 'customer_email', 'payment_id', 'refund_type', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.refunded', 'channel_group' => 'chat', 'title' => '{{app_name}}: Payment refunded', 'body' => '{{refund_type}} of {{refund_amount}} {{currency}} processed. Original amount: {{amount}} {{currency}}. Payment ID: {{payment_id}}.', 'variables' => ['app_name', 'amount', 'refund_amount', 'currency', 'description', 'customer_email', 'payment_id', 'refund_type', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'payment.refunded', 'channel_group' => 'email', 'title' => '{{app_name}}: Payment refunded', 'body' => '<p>Hi {{user.name}},</p><p>{{refund_type}} of {{refund_amount}} {{currency}} processed.</p><p>Original amount: {{amount}} {{currency}}. Payment ID: {{payment_id}}.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'amount', 'refund_amount', 'currency', 'description', 'customer_email', 'payment_id', 'refund_type', 'user.name'], 'is_system' => true, 'is_active' => true],
            // price.drop
            ['type' => 'price.drop', 'channel_group' => 'push', 'title' => '{{app_name}}: Price Drop', 'body' => '{{item_name}} dropped from ${{old_price}} to ${{new_price}}!', 'variables' => ['app_name', 'item_name', 'old_price', 'new_price', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.drop', 'channel_group' => 'inapp', 'title' => 'Price Drop', 'body' => '{{item_name}} in "{{list_name}}" dropped from ${{old_price}} to ${{new_price}}.', 'variables' => ['app_name', 'item_name', 'old_price', 'new_price', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.drop', 'channel_group' => 'chat', 'title' => '{{app_name}}: Price Drop', 'body' => '{{item_name}} in "{{list_name}}" dropped from ${{old_price}} to ${{new_price}}.', 'variables' => ['app_name', 'item_name', 'old_price', 'new_price', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.drop', 'channel_group' => 'email', 'title' => '{{app_name}}: Price Drop on {{item_name}}', 'body' => '<p>Hi {{user.name}},</p><p><strong>{{item_name}}</strong> in "{{list_name}}" dropped from ${{old_price}} to <strong>${{new_price}}</strong>.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'item_name', 'old_price', 'new_price', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            // price.all_time_low
            ['type' => 'price.all_time_low', 'channel_group' => 'push', 'title' => '{{app_name}}: All-Time Low', 'body' => '{{item_name}} hit an all-time low of ${{new_low_price}}!', 'variables' => ['app_name', 'item_name', 'new_low_price', 'previous_low', 'retailer', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.all_time_low', 'channel_group' => 'inapp', 'title' => 'All-Time Low Price', 'body' => '{{item_name}} in "{{list_name}}" hit an all-time low of ${{new_low_price}} at {{retailer}}.', 'variables' => ['app_name', 'item_name', 'new_low_price', 'previous_low', 'retailer', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.all_time_low', 'channel_group' => 'chat', 'title' => '{{app_name}}: All-Time Low', 'body' => '{{item_name}} in "{{list_name}}" hit an all-time low of ${{new_low_price}} at {{retailer}} (previous low: ${{previous_low}}).', 'variables' => ['app_name', 'item_name', 'new_low_price', 'previous_low', 'retailer', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'price.all_time_low', 'channel_group' => 'email', 'title' => '{{app_name}}: All-Time Low on {{item_name}}', 'body' => '<p>Hi {{user.name}},</p><p><strong>{{item_name}}</strong> in "{{list_name}}" hit an all-time low of <strong>${{new_low_price}}</strong> at {{retailer}} (previous low: ${{previous_low}}).</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'item_name', 'new_low_price', 'previous_low', 'retailer', 'list_name', 'list_id', 'item_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            // list.share_invitation
            ['type' => 'list.share_invitation', 'channel_group' => 'push', 'title' => '{{app_name}}: List shared', 'body' => '{{shared_by_name}} shared "{{list_name}}" with you.', 'variables' => ['app_name', 'list_name', 'shared_by_name', 'permission', 'list_id', 'share_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'list.share_invitation', 'channel_group' => 'inapp', 'title' => 'List shared with you', 'body' => '{{shared_by_name}} shared "{{list_name}}" with you ({{permission}} access).', 'variables' => ['app_name', 'list_name', 'shared_by_name', 'permission', 'list_id', 'share_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'list.share_invitation', 'channel_group' => 'chat', 'title' => '{{app_name}}: List shared', 'body' => '{{shared_by_name}} shared "{{list_name}}" with you ({{permission}} access).', 'variables' => ['app_name', 'list_name', 'shared_by_name', 'permission', 'list_id', 'share_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'list.share_invitation', 'channel_group' => 'email', 'title' => '{{app_name}}: {{shared_by_name}} shared a list with you', 'body' => '<p>Hi {{user.name}},</p><p>{{shared_by_name}} shared the shopping list "{{list_name}}" with you ({{permission}} access).</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'list_name', 'shared_by_name', 'permission', 'list_id', 'share_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            // smart_add.complete
            ['type' => 'smart_add.complete', 'channel_group' => 'push', 'title' => '{{app_name}}: Smart Add complete', 'body' => '{{product_count}} product(s) identified from {{source_type}}.', 'variables' => ['app_name', 'product_count', 'source_type', 'job_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'smart_add.complete', 'channel_group' => 'inapp', 'title' => 'Smart Add complete', 'body' => '{{product_count}} product(s) identified from {{source_type}} upload. Review and add to your list.', 'variables' => ['app_name', 'product_count', 'source_type', 'job_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'smart_add.complete', 'channel_group' => 'chat', 'title' => '{{app_name}}: Smart Add complete', 'body' => '{{product_count}} product(s) identified from {{source_type}} upload.', 'variables' => ['app_name', 'product_count', 'source_type', 'job_id', 'user.name'], 'is_system' => true, 'is_active' => true],
            ['type' => 'smart_add.complete', 'channel_group' => 'email', 'title' => '{{app_name}}: Smart Add results ready', 'body' => '<p>Hi {{user.name}},</p><p>{{product_count}} product(s) were identified from your {{source_type}} upload.</p><p>Review and add them to your shopping list.</p><p>— {{app_name}}</p>', 'variables' => ['app_name', 'product_count', 'source_type', 'job_id', 'user.name'], 'is_system' => true, 'is_active' => true],
        ];
    }
};
