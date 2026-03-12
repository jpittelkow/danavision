# Recipe: Verify Notification Configuration

Admin checklist for validating that all notification channels are correctly configured.

## When to Use

- During initial deployment setup
- After changing notification provider credentials
- During periodic health checks
- When migrating between environments

## Pre-Flight Checklist

### 1. Core Channels (Always Available)

- [ ] **Database (In-App)**: No config required. Verify the notification bell in the header shows notifications.
- [ ] **Email**: Go to Configuration > Email. Verify mail driver, SMTP settings, and send a test email.

### 2. Chat Channels

- [ ] **Telegram**: Set `TELEGRAM_BOT_TOKEN` or configure in admin UI. Bot must be added to the target chat.
- [ ] **Discord**: Set `DISCORD_WEBHOOK_URL` or configure in admin UI. Users can also provide their own webhook URLs.
- [ ] **Slack**: Set `SLACK_WEBHOOK_URL` or configure in admin UI. Users can also provide their own webhook URLs.
- [ ] **Signal**: Install `signal-cli` on the server. Set `SIGNAL_CLI_PATH` and `SIGNAL_PHONE_NUMBER`.
- [ ] **Matrix**: Set `MATRIX_HOMESERVER` and `MATRIX_ACCESS_TOKEN`. Optionally set `MATRIX_DEFAULT_ROOM`.

### 3. SMS Channels (Choose One)

- [ ] **Twilio**: Set `TWILIO_SID`, `TWILIO_TOKEN`, and `TWILIO_FROM` (sender phone number).
- [ ] **Vonage**: Set `VONAGE_API_KEY`, `VONAGE_API_SECRET`, and `VONAGE_FROM`.
- [ ] **AWS SNS**: Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and enable `SNS_ENABLED=true`.
- [ ] Set the preferred SMS provider in Configuration > Notifications > SMS Provider.

### 4. Push Channels

- [ ] **Web Push (VAPID)**: Generate VAPID keys and set `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`.
- [ ] **FCM**: Create a Firebase project. Set `FCM_PROJECT_ID` and `FCM_SERVICE_ACCOUNT` (JSON key).
- [ ] **ntfy**: Enabled by default. Set `NTFY_SERVER` for self-hosted instances.

### 5. Novu (Optional)

- [ ] Set `NOVU_ENABLED=true`, `NOVU_API_KEY`, and `NOVU_APP_IDENTIFIER` in Configuration > Novu.
- [ ] Create workflows in Novu dashboard matching the workflow map (see configure-novu recipe).
- [ ] Click **Test connection** to verify API connectivity.
- [ ] Run `php artisan novu:sync-subscribers` to sync existing users.

## Verification Steps

### Step 1: Check Channel Status

Go to **Configuration > Notifications**. For each channel:
- Verify the status badge shows "Configured"
- Toggle "Available to users" for channels you want to offer

### Step 2: Test All Channels

Click **Test all enabled channels** on the Verify Configuration card. Review:
- **Green checkmark**: Channel is working
- **Red X**: Channel failed (hover for error details)
- **Grey dash**: Channel skipped (not configured or not available)

### Step 3: Verify Templates

Go to **Configuration > Notification Templates**. For each type:
- Verify templates exist for push, inapp, and chat
- Check the preview renders correctly with sample variables
- Use the **Send test** button to verify real delivery

### Step 4: Test User Experience

1. As a non-admin user, go to **User Preferences > Notification Preferences**
2. Enable a channel and provide settings (if needed)
3. Click **Test** to verify delivery

## API Verification Endpoint

```
GET /admin/notification-channels/verify
```

Returns the configuration status of all channels without sending any notifications:

```json
{
  "data": {
    "channels": {
      "database": { "name": "In-App", "provider_configured": true, "available_to_users": true, "always_available": true },
      "email": { "name": "Email", "provider_configured": true, "available_to_users": true, "always_available": true },
      "telegram": { "name": "Telegram", "provider_configured": true, "available_to_users": false, "always_available": false }
    }
  }
}
```

## Key Files

```
backend/config/notifications.php                                 # Channel config (env-based)
backend/config/settings-schema.php                               # Notification settings schema
backend/app/Providers/ConfigServiceProvider.php                  # DB settings injection
backend/app/Http/Controllers/Api/NotificationChannelConfigController.php  # verify endpoint
frontend/app/(dashboard)/configuration/notifications/page.tsx    # Admin UI
```

## Related

- [Test Notifications](test-notifications.md) - How to test individual channels
- [Add Notification Channel](add-notification-channel.md) - Adding new channels
- [Configure Novu](configure-novu.md) - Novu setup
- [Trigger Notifications](trigger-notifications.md) - Sending notifications from code

### Implementation Journal

- [Notification Config Split (2026-01-28)](../../journal/2026-01-28-notification-config-split.md)
