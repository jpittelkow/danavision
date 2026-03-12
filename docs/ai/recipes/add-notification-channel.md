# Recipe: Add Notification Channel

Step-by-step guide to add a new notification delivery channel.

## Architecture Overview

Sourdough uses a **two-layer notification configuration**:

1. **Admin layer** (Configuration > Notifications): Enable which channels are available to users
2. **User layer** (User Preferences): Users configure their own settings (webhooks, phone numbers) and enable channels

```
┌─────────────────────────────────────────────────────────────┐
│ Admin: Configuration > Notifications                        │
│   - Toggle "channel available to users"                     │
│   - Configure provider credentials (env vars)               │
│   - Select preferred SMS provider (Twilio/Vonage/SNS)       │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ User: Preferences > Notifications                           │
│   - See only admin-enabled channels                         │
│   - Enter user-specific settings (webhook URL, phone, etc.) │
│   - Test notification                                       │
│   - Accept usage, then enable                               │
└─────────────────────────────────────────────────────────────┘
```

## Files to Create/Modify

### Backend

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Services/Notifications/Channels/{Name}Channel.php` | Create | Channel implementation |
| `backend/config/notifications.php` | Modify | Register channel config |
| `backend/app/Services/Notifications/NotificationOrchestrator.php` | Modify | Add import + instance mapping |
| `backend/app/Services/Notifications/NotificationChannelMetadata.php` | Modify | Add name, description, and user-configurable flag |
| `backend/app/Http/Controllers/Api/UserNotificationSettingsController.php` | Modify | Add user-specific settings |
| `backend/.env.example` | Modify | Add environment variables |

### Frontend

| File | Action | Purpose |
|------|--------|---------|
| `frontend/app/(dashboard)/configuration/notifications/page.tsx` | Modify | Add channel icon to `channelIcons` map |

## Step 1: Create the Channel Class

Create a class implementing `ChannelInterface`. If your channel belongs to **push** (WebPush, FCM, ntfy), **inapp** (DatabaseChannel), or **chat** (Telegram, Discord, Slack, Twilio, etc.), add **template resolution** so per-type notification templates are used when present: at the start of `send()`, call a private `resolveContent($user, $type, $title, $message, $data)` that uses `NotificationTemplateService::getByTypeAndChannel($type, $channelGroup)` and, if found, `renderTemplate()` with merged variables (user, app_name, $data); then use the returned title/body. Otherwise use the passed title/message. See existing channels (e.g. `DatabaseChannel.php`, `WebPushChannel.php`, `TelegramChannel.php`) for the `resolveContent()` pattern. Map your channel to one of: `push`, `inapp`, `chat` (and add it to `NotificationOrchestrator::channelToGroup()` if you add a new channel identifier).

```php
<?php
// backend/app/Services/Notifications/Channels/ExampleChannel.php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class ExampleChannel implements ChannelInterface
{
    /**
     * Send a notification via this channel.
     */
    public function send(User $user, string $type, string $title, string $message, array $data = []): array
    {
        // Get user-specific config (from User Preferences)
        $webhookUrl = $user->getSetting('notifications', 'example_webhook_url');

        if (!$webhookUrl) {
            throw new \RuntimeException('Example webhook URL not configured');
        }

        $payload = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'timestamp' => now()->toISOString(),
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        $response = Http::timeout(30)->post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Example webhook error: ' . $response->body());
        }

        return ['sent' => true];
    }

    /**
     * Get the channel name.
     */
    public function getName(): string
    {
        return 'Example';
    }

    /**
     * Check if the channel is available for the user.
     * This checks if the channel is configured globally.
     */
    public function isAvailableFor(User $user): bool
    {
        // Check if channel is enabled in config (provider configured)
        return config('notifications.channels.example.enabled', false);
    }
}
```

### Channel Types

Different channels have different user configuration needs:

| Type | User Config | Example Channels | User-Configurable |
|------|-------------|------------------|-------------------|
| Webhook | URL | Slack, Discord | Yes |
| Topic/Pub-Sub | Topic name | ntfy | Yes |
| Bot/API | Chat ID | Telegram | No (needs bot token) |
| Homeserver | Room ID | Matrix | No (needs access token) |
| SMS | Phone number | Twilio, Vonage, SNS | No (needs API keys) |
| None | - | Database, Email | N/A (always available) |

## Step 2: Register in Configuration

```php
// backend/config/notifications.php

return [
    'channels' => [
        // ... existing channels ...

        'example' => [
            'enabled' => env('NOTIFICATIONS_EXAMPLE_ENABLED', false),
            // Add any global config (API keys, etc.)
            'api_key' => env('EXAMPLE_API_KEY'),
        ],
    ],
    // ...
];
```

## Step 3: Add to Orchestrator

```php
// backend/app/Services/Notifications/NotificationOrchestrator.php

// 1. Add the import at the top of the file:
use App\Services\Notifications\Channels\ExampleChannel;

// 2. In getChannelInstance() method, add to the match expression:
private function getChannelInstance(string $channel): ?ChannelInterface
{
    // ...
    $instance = match ($channel) {
        'database' => new DatabaseChannel(),
        'email' => new EmailChannel(),
        // ... existing channels ...
        'example' => new ExampleChannel(),  // Add this line
        default => null,
    };
    // ...
}
```

## Step 4: Add to Metadata Trait

Update the shared trait with the channel name, description, and user-configurable flag:

```php
// backend/app/Services/Notifications/NotificationChannelMetadata.php

// 1. Add name:
protected function getChannelName(string $id): string
{
    return match ($id) {
        // ... existing channels ...
        'example' => 'Example Service',
        default => ucfirst($id),
    };
}

// 2. Add description:
protected function getChannelDescription(string $id): string
{
    return match ($id) {
        // ... existing channels ...
        'example' => 'Receive notifications via Example service',
        default => "Receive notifications via {$id}",
    };
}

// 3. If channel doesn't need global credentials (users provide their own
//    webhooks/topics), add to isUserConfigurableChannel():
protected function isUserConfigurableChannel(string $id): bool
{
    return in_array($id, ['slack', 'discord', 'example'], true);
}
```

**User-configurable channels** (Slack, Discord, ntfy) don't require admin-configured API keys - users provide their own webhooks or topics. These channels:
- Show as "Configured" in admin panel without env vars
- Can be enabled by admin without setting up global credentials
- Users enter their own webhook URL / topic in preferences

## Step 5: Add User Settings

Update the user notification settings controller to define required user settings:

```php
// backend/app/Http/Controllers/Api/UserNotificationSettingsController.php

// In getRequiredSettings() method:
private function getRequiredSettings(string $id): array
{
    return match ($id) {
        // ... existing channels ...
        'example' => [
            [
                'key' => 'webhook_url',
                'label' => 'Webhook URL',
                'type' => 'text',
                'placeholder' => 'https://api.example.com/webhook/...',
            ],
        ],
        default => [],
    };
}
```

**Setting types:** `text`, `password`, `url`

## Step 6: Add Environment Variables

```env
# backend/.env.example

# Example Notification Channel
NOTIFICATIONS_EXAMPLE_ENABLED=false
EXAMPLE_API_KEY=
```

## Step 7: Add Frontend Icon

Add an icon for the channel in the admin notifications page:

```tsx
// frontend/app/(dashboard)/configuration/notifications/page.tsx

// Add to the channelIcons map:
const channelIcons: Record<string, React.ReactNode> = {
  database: <Bell className="h-5 w-5" />,
  email: <Mail className="h-5 w-5" />,
  // ... existing channels ...
  example: <Bell className="h-5 w-5" />,  // Add this line
};
```

**Icon suggestions by channel type:**
- Push notifications: `Bell`
- Email: `Mail`
- Chat/messaging: `MessageSquare`
- Phone/SMS: `Phone`

## Step 8: Test the Channel

1. Set env vars and restart:
   ```
   NOTIFICATIONS_EXAMPLE_ENABLED=true
   EXAMPLE_API_KEY=your-key
   ```

2. In **Configuration > Notifications** (as admin), toggle "Available to users" for Example

3. In **User Preferences > Notifications** (as user), the Example channel should now appear

4. Enter webhook URL, click "Save settings", then enable the toggle

5. Click "Test" to verify

**Note:** For user-configurable channels (no global credentials), you can skip step 1 - just enable in admin panel.

## Checklist

### Backend
- [ ] Channel class created implementing `ChannelInterface`
- [ ] `send()` method implemented with user settings
- [ ] `getName()` returns display name
- [ ] `isAvailableFor()` checks config enabled AND user has required settings
- [ ] Channel registered in `config/notifications.php`
- [ ] Import added to `NotificationOrchestrator.php`
- [ ] Channel added to `NotificationOrchestrator::getChannelInstance()` match
- [ ] Name added to `NotificationChannelMetadata::getChannelName()`
- [ ] Description added to `NotificationChannelMetadata::getChannelDescription()`
- [ ] If user-configurable: added to `NotificationChannelMetadata::isUserConfigurableChannel()`
- [ ] User settings defined in `UserNotificationSettingsController::getRequiredSettings()`
- [ ] Environment variables added to `backend/.env.example`

### Frontend
- [ ] Icon added to `channelIcons` map in `configuration/notifications/page.tsx`

### Testing
- [ ] Admin can enable channel in Configuration > Notifications
- [ ] Channel appears in User Preferences when admin-enabled
- [ ] User can save settings and enable channel
- [ ] Test notification works

## Channel Interface Reference

```php
interface ChannelInterface
{
    /**
     * Send a notification via this channel.
     *
     * @param User $user The recipient user
     * @param string $type Notification type (info, warning, error, etc.)
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Optional additional data
     * @return array Result array (e.g., ['sent' => true])
     */
    public function send(User $user, string $type, string $title, string $message, array $data = []): array;

    /**
     * Get the channel name.
     */
    public function getName(): string;

    /**
     * Check if the channel is available for the user.
     * Usually checks if the provider is configured (env vars set).
     */
    public function isAvailableFor(User $user): bool;
}
```

## Existing Channels for Reference

| Channel | User Config | User-Configurable | File |
|---------|-------------|-------------------|------|
| Slack | `webhook_url` | Yes | `Channels/SlackChannel.php` |
| Discord | `webhook_url` | Yes | `Channels/DiscordChannel.php` |
| ntfy | `topic` | Yes | `Channels/NtfyChannel.php` |
| Telegram | `chat_id` | No | `Channels/TelegramChannel.php` |
| Matrix | `room_id` | No | `Channels/MatrixChannel.php` |
| Twilio | `phone_number` | No | `Channels/TwilioChannel.php` |
| Vonage | `phone_number` | No | `Channels/VonageChannel.php` |
| SNS | `phone_number` | No | `Channels/SNSChannel.php` |
| Database | none | N/A (always available) | `Channels/DatabaseChannel.php` |
| Email | none | N/A (always available) | `Channels/EmailChannel.php` |

**User-Configurable** = Channel doesn't require global API keys; users provide their own webhooks/topics.

## Related Documentation

- [ADR-005: Notification System Architecture](../../adr/005-notification-system-architecture.md)
- [ADR-017: Notification Template System](../../adr/017-notification-template-system.md) — Per-type templates for push, inapp, chat; channels resolve content when a template exists.
- [Recipe: Trigger Notifications](trigger-notifications.md) — `send()` and `sendByType()` usage.
- [Recipe: Add Notification Template](add-notification-template.md) — Add new notification types with push/inapp/chat templates.
- [Notification Config Split Journal](../../journal/2026-01-28-notification-config-split.md)
