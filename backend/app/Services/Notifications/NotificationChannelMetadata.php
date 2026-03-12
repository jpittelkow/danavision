<?php

namespace App\Services\Notifications;

/**
 * Shared metadata for notification channels.
 * Used by both admin and user notification settings controllers.
 */
trait NotificationChannelMetadata
{
    /**
     * Check if a channel is always available (no admin toggle required).
     */
    protected function isAlwaysAvailableChannel(string $id): bool
    {
        return in_array($id, ['database', 'email'], true);
    }

    /**
     * Check if a channel is user-configurable (no global provider credentials needed).
     * These channels only require user-provided settings (webhooks, etc.),
     * so admins can enable them without setting up global credentials.
     */
    protected function isUserConfigurableChannel(string $id): bool
    {
        return in_array($id, ['slack', 'discord', 'ntfy', 'webpush'], true);
    }

    /**
     * Get human-readable channel name.
     */
    protected function getChannelName(string $id): string
    {
        return match ($id) {
            'database' => 'In-App',
            'email' => 'Email',
            'telegram' => 'Telegram',
            'discord' => 'Discord',
            'slack' => 'Slack',
            'signal' => 'Signal',
            'matrix' => 'Matrix',
            'twilio' => 'Twilio SMS',
            'vonage' => 'Vonage SMS',
            'sns' => 'AWS SNS',
            'webpush' => 'Web Push',
            'fcm' => 'Firebase Cloud Messaging',
            'ntfy' => 'ntfy Push',
            default => ucfirst($id),
        };
    }

    /**
     * Get channel description.
     */
    protected function getChannelDescription(string $id): string
    {
        return match ($id) {
            'database' => 'Receive notifications in the application',
            'email' => 'Receive notifications via email',
            'telegram' => 'Receive notifications via Telegram bot',
            'discord' => 'Receive notifications via Discord webhook',
            'slack' => 'Receive notifications via Slack webhook',
            'signal' => 'Receive notifications via Signal messaging',
            'matrix' => 'Receive notifications via Matrix',
            'twilio' => 'Receive notifications via Twilio SMS',
            'vonage' => 'Receive notifications via Vonage SMS',
            'sns' => 'Receive notifications via AWS SNS',
            'webpush' => 'Receive browser push notifications',
            'fcm' => 'Receive notifications via Firebase Cloud Messaging',
            'ntfy' => 'Receive push notifications via ntfy',
            default => "Receive notifications via {$id}",
        };
    }

    /**
     * Apply notification settings from DB to runtime config for the current request.
     * Used when testing channels or verifying config with latest DB values.
     */
    protected function applyNotificationsConfigForRequest(array $settings): void
    {
        if (array_key_exists('telegram_bot_token', $settings)) {
            config(['notifications.channels.telegram.bot_token' => $settings['telegram_bot_token']]);
            config(['notifications.channels.telegram.enabled' => !empty($settings['telegram_bot_token'])]);
        }
        if (array_key_exists('discord_webhook_url', $settings)) {
            config(['notifications.channels.discord.webhook_url' => $settings['discord_webhook_url']]);
            config(['notifications.channels.discord.username' => $settings['discord_bot_name'] ?? config('notifications.channels.discord.username')]);
            config(['notifications.channels.discord.avatar_url' => $settings['discord_avatar_url'] ?? null]);
            config(['notifications.channels.discord.enabled' => !empty($settings['discord_webhook_url'])]);
        }
        if (array_key_exists('slack_webhook_url', $settings)) {
            config(['notifications.channels.slack.webhook_url' => $settings['slack_webhook_url']]);
            config(['notifications.channels.slack.username' => $settings['slack_bot_name'] ?? config('notifications.channels.slack.username')]);
            config(['notifications.channels.slack.icon' => $settings['slack_icon'] ?? config('notifications.channels.slack.icon')]);
            config(['notifications.channels.slack.enabled' => !empty($settings['slack_webhook_url'])]);
        }
        if (array_key_exists('signal_cli_path', $settings)) {
            config(['notifications.channels.signal.cli_path' => $settings['signal_cli_path'] ?? config('notifications.channels.signal.cli_path')]);
            config(['notifications.channels.signal.phone_number' => $settings['signal_phone_number'] ?? null]);
            config(['notifications.channels.signal.config_dir' => $settings['signal_config_dir'] ?? config('notifications.channels.signal.config_dir')]);
            config(['notifications.channels.signal.enabled' => !empty($settings['signal_cli_path']) && !empty($settings['signal_phone_number'])]);
        }
        if (array_key_exists('twilio_sid', $settings)) {
            config(['notifications.channels.twilio.sid' => $settings['twilio_sid']]);
            config(['notifications.channels.twilio.token' => $settings['twilio_token'] ?? null]);
            config(['notifications.channels.twilio.from' => $settings['twilio_from'] ?? null]);
            config(['notifications.channels.twilio.enabled' => !empty($settings['twilio_sid']) && !empty($settings['twilio_token'])]);
        }
        if (array_key_exists('vonage_api_key', $settings)) {
            config(['notifications.channels.vonage.api_key' => $settings['vonage_api_key']]);
            config(['notifications.channels.vonage.api_secret' => $settings['vonage_api_secret'] ?? null]);
            config(['notifications.channels.vonage.from' => $settings['vonage_from'] ?? null]);
            config(['notifications.channels.vonage.enabled' => !empty($settings['vonage_api_key']) && !empty($settings['vonage_api_secret'])]);
        }
        if (array_key_exists('sns_enabled', $settings)) {
            config(['notifications.channels.sns.enabled' => !empty(config('mail.mailers.ses.key')) && $settings['sns_enabled']]);
        }
        if (array_key_exists('vapid_public_key', $settings)) {
            config(['notifications.channels.webpush.public_key' => $settings['vapid_public_key']]);
            config(['notifications.channels.webpush.private_key' => $settings['vapid_private_key'] ?? null]);
            config(['notifications.channels.webpush.subject' => $settings['vapid_subject'] ?? config('app.url')]);
            config(['notifications.channels.webpush.enabled' => !empty($settings['vapid_public_key']) && !empty($settings['vapid_private_key'])]);
        }
        if (array_key_exists('fcm_project_id', $settings)) {
            config(['notifications.channels.fcm.project_id' => $settings['fcm_project_id']]);
        }
        if (array_key_exists('fcm_service_account', $settings)) {
            config(['notifications.channels.fcm.service_account' => $settings['fcm_service_account']]);
            // Use runtime config for project_id (may have been set above) instead of $settings
            config(['notifications.channels.fcm.enabled' => !empty(config('notifications.channels.fcm.project_id')) && !empty($settings['fcm_service_account'])]);
        }
        if (array_key_exists('ntfy_enabled', $settings)) {
            config(['notifications.channels.ntfy.enabled' => $settings['ntfy_enabled']]);
        }
        if (array_key_exists('ntfy_server', $settings)) {
            config(['notifications.channels.ntfy.server' => $settings['ntfy_server'] ?? 'https://ntfy.sh']);
        }
        if (array_key_exists('matrix_homeserver', $settings)) {
            config(['notifications.channels.matrix.homeserver' => $settings['matrix_homeserver']]);
            config(['notifications.channels.matrix.access_token' => $settings['matrix_access_token'] ?? null]);
            config(['notifications.channels.matrix.default_room' => $settings['matrix_default_room'] ?? null]);
            config(['notifications.channels.matrix.enabled' => !empty($settings['matrix_homeserver']) && !empty($settings['matrix_access_token'])]);
        }
    }
}
