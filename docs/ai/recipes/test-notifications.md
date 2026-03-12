# Recipe: Test Notifications

How to test notification channels and verify they are correctly configured.

## When to Use

- After configuring a new notification channel
- After changing channel credentials
- During deployment verification
- When a user reports not receiving notifications

## Test Approaches

### 1. Admin UI: Test Individual Channels

**Configuration > Notifications** page has a "Test" button for each channel credential section.

- Navigate to Configuration > Notifications
- Expand the channel section (e.g., Telegram, Discord)
- Click **Test** to send a test notification using that channel

This uses `POST /notification-settings/test/{channel}` which applies the latest saved credentials before sending.

### 2. Admin UI: Test All Enabled Channels

The **Verify Configuration** card at the top of Configuration > Notifications tests all channels at once.

- Click **Test all enabled channels**
- Review the per-channel results (success/error/skipped)
- Channels that are not configured or not available are skipped automatically

This uses `POST /admin/notification-channels/test-all`.

### 3. User Preferences: Test User-Configured Channels

Users can test their own channel configuration from **User Preferences > Notification Preferences**.

- Navigate to User Preferences
- Find the channel (e.g., Slack, Discord, ntfy)
- Enter personal settings (webhook URL, topic, etc.)
- Click **Test**

This uses `POST /notifications/test/{channel}`.

### 4. Template Editor: Test Notification Delivery

Admins can send a test notification from the **Notification Template Editor**.

- Navigate to Configuration > Notification Templates
- Click on a template
- Click **Send test** to send a real notification through the channel group

### 5. Backend: Artisan Commands

```bash
# Test Novu connection
php artisan novu:test

# Sync subscribers to Novu
php artisan novu:sync-subscribers
```

### 6. Backend: Programmatic Test

```php
$orchestrator = app(NotificationOrchestrator::class);

// Test a specific channel
$orchestrator->sendTestNotification($user, 'database');
$orchestrator->sendTestNotification($user, 'email');
$orchestrator->sendTestNotification($user, 'slack');

// Test template-based sending
$orchestrator->sendByType($user, 'backup.completed', [
    'backup_name' => 'test-backup',
]);
```

## API Endpoints

| Endpoint | Who | Purpose |
|----------|-----|---------|
| `POST /notifications/test/{channel}` | Any authenticated user | Test a channel with user's own settings |
| `POST /notification-settings/test/{channel}` | Admin (`can:settings.edit`) | Test with latest admin credentials |
| `POST /admin/notification-channels/test-all` | Admin (`can:settings.edit`) | Test all enabled channels |
| `GET /admin/notification-channels/verify` | Admin (`can:settings.view`) | Verify config status without sending |
| `POST /novu-settings/test` | Admin (`can:settings.edit`) | Test Novu API connection |

## Troubleshooting

### Channel shows "Not configured"

- Check that the required environment variables or database settings are set
- For channels configured via admin UI, verify credentials in Configuration > Notifications
- For user-configurable channels (Slack, Discord, ntfy, WebPush), the user must provide their own settings

### Test sends but notification not received

- **Telegram**: Verify the `chat_id` is correct. The bot must be in the chat.
- **Discord/Slack**: Verify the webhook URL is valid and the webhook hasn't been deleted.
- **SMS (Twilio/Vonage/SNS)**: Check the phone number format (must include country code, e.g., `+1`).
- **WebPush**: Check browser notification permissions. Subscription may have expired.
- **ntfy**: Verify the user is subscribed to the correct topic in the ntfy app.
- **Email**: Check mail settings in Configuration > Email. Verify the mail driver isn't `log`.

### Novu connection fails

- Verify the API key is correct
- For self-hosted Novu, verify the API URL and WebSocket URL
- Run `php artisan novu:test` for detailed error output
- Check that Novu workflows exist for the configured types

## Key Files

```
backend/app/Services/Notifications/NotificationOrchestrator.php  # sendTestNotification()
backend/app/Http/Controllers/Api/NotificationController.php      # POST /notifications/test/{channel}
backend/app/Http/Controllers/Api/NotificationSettingController.php  # POST /notification-settings/test/{channel}
backend/app/Http/Controllers/Api/NotificationChannelConfigController.php  # test-all, verify
backend/app/Console/Commands/NovuTestCommand.php                 # novu:test
frontend/app/(dashboard)/configuration/notifications/page.tsx    # Admin test UI
frontend/app/(dashboard)/user/preferences/page.tsx               # User test UI
```

## Related

- [Add Notification Channel](add-notification-channel.md) - Step 8 covers testing
- [Configure Novu](configure-novu.md) - Novu connection testing
- [Trigger Notifications](trigger-notifications.md) - How to send notifications from code

### Implementation Journal

- [Notifications Implementation (2026-01-27)](../../journal/2026-01-27-notifications-implementation.md)
- [Notification Config Split (2026-01-28)](../../journal/2026-01-28-notification-config-split.md)
