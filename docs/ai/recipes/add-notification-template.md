# Recipe: Add Notification Template

Step-by-step guide to add a new notification type with per-channel templates (push, inapp, chat).

## Architecture Overview

Notification templates are stored in the `notification_templates` table and seeded from `NotificationTemplateSeeder`. Each row is keyed by (type, channel_group). Application code uses `NotificationTemplateService::render($type, $channelGroup, $variables)` or `NotificationOrchestrator::sendByType($user, $type, $variables, $channels)`.

See [ADR-017: Notification Template System](../../adr/017-notification-template-system.md).

## Files to Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/database/seeders/NotificationTemplateSeeder.php` | Modify | Add default templates for the new type (push, inapp, chat) and `getDefaultFor` support |

No new files are required; new types are added by extending the seeder.

## Step 1: Add Templates to Seeder Defaults

Edit `NotificationTemplateSeeder::defaults()` and add three entries per type (one per channel_group: push, inapp, chat):

```php
// backend/database/seeders/NotificationTemplateSeeder.php

// Example: adding storage.quota_warning
['type' => 'storage.quota_warning', 'channel_group' => 'push', 'title' => '{{app_name}}: Storage quota', 'body' => 'You have used {{usage}}% of your storage.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
['type' => 'storage.quota_warning', 'channel_group' => 'inapp', 'title' => 'Storage quota', 'body' => 'You have used {{usage}}% of your storage.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
['type' => 'storage.quota_warning', 'channel_group' => 'chat', 'title' => '{{app_name}}: Storage quota', 'body' => 'You have used {{usage}}% of your storage.', 'variables' => ['app_name', 'usage', 'user.name'], 'is_system' => true, 'is_active' => true],
```

### Type naming

- Use dot notation: e.g. `backup.completed`, `auth.login`, `llm.quota_warning`.
- Type is used in code: `$orchestrator->sendByType($user, 'storage.quota_warning', $vars)`.

### Channel groups

- **push**: WebPush, FCM, ntfy — short title and body.
- **inapp**: DatabaseChannel — title and message shown in-app.
- **chat**: Telegram, Discord, Slack, Twilio, Signal, Matrix, Vonage, SNS — markdown-friendly body.

### Variable naming

- Use dot notation for related data: `user.name`, `user.email`.
- List all placeholders in the `variables` array (for admin UI).
- Pass nested arrays when sending: `['user' => ['name' => '...'], 'app_name' => config('app.name'), 'usage' => 80]`.

## Step 2: Add getDefaultFor (for reset)

If the seeder exposes `getDefaultFor($type, $channelGroup)` for reset, ensure the new type is covered. The current implementation iterates `defaults()`; as long as the new type is in `defaults()`, reset will work.

## Step 3: Run the Seeder

Either run the seeder once so the new templates exist:

```bash
php artisan db:seed --class=NotificationTemplateSeeder
```

Or run migrations (if you added a new migration that calls the seeder). Existing installs: run the seeder; it uses `updateOrCreate` by (type, channel_group) so the new rows are inserted without affecting existing ones.

## Step 4: Use in Code

Where you send the notification, use `NotificationOrchestrator::sendByType()` so templates are used per channel:

```php
use App\Services\Notifications\NotificationOrchestrator;

$this->orchestrator->sendByType(
    $user,
    'storage.quota_warning',
    [
        'usage' => 85,
        // user and app_name are merged by the orchestrator
    ],
    ['database', 'webpush']  // optional; defaults from config
);
```

Or keep using `send()` with explicit title/message; channels will still resolve a template for (type, channel_group) when one exists, otherwise use the passed title/message.

## Step 5: Add Variable Descriptions for UI Reference

The template editor shows an "Available Variables" panel with descriptions. When you introduce a **new variable name** (not already in the list), add it to `NotificationTemplateController::variableDescriptions()`:

```php
// backend/app/Http/Controllers/Api/NotificationTemplateController.php

private function variableDescriptions(): array
{
    return [
        'app_name' => 'Application name from settings',
        'user.name' => "Recipient user's display name",
        'user.email' => "Recipient user's email address",
        'backup_name' => 'Name of the backup',
        'error_message' => 'Error details when operation fails',
        'ip' => 'IP address of the request',
        'timestamp' => 'Date and time of the event',
        'version' => 'Application version number',
        'usage' => 'Current quota usage percentage',
        // Add new variables here, e.g.:
        // 'storage_used' => 'Amount of storage used',
    ];
}
```

The `show` response already includes `variable_descriptions`; the frontend uses it in the collapsible "Available Variables" card. If a variable is missing from this map, the UI shows "Available when sending." as fallback.

## Step 6: Add Sample Variables for Preview (Optional)

If admins will preview this type in the UI, add sample variables in `NotificationTemplateController::sampleVariables()`:

```php
'storage.quota_warning' => [
    'user' => $user,
    'app_name' => $appName,
    'usage' => '85',
],
```

## Checklist

- [ ] Added three rows to `NotificationTemplateSeeder::defaults()` (push, inapp, chat).
- [ ] Ran seeder or migration.
- [ ] Trigger code uses `sendByType($user, $type, $variables)` or `send()` with type and data.
- [ ] **New variable names** added to `NotificationTemplateController::variableDescriptions()` so the editor "Available Variables" panel shows descriptions.
- [ ] Optionally added sample variables in the controller for preview.

## Reference

- **Service**: `backend/app/Services/NotificationTemplateService.php`
- **Model**: `backend/app/Models/NotificationTemplate.php`
- **Orchestrator**: `backend/app/Services/Notifications/NotificationOrchestrator.php` — `sendByType()`, `channelToGroup()`
- **Channels**: `backend/app/Services/Notifications/Channels/*` — resolveContent() for template lookup
- **Recipe**: [Trigger Notifications](trigger-notifications.md) — sendByType() usage
- **Recipe**: [Add Notification Channel](add-notification-channel.md) — When adding a new channel, implement resolveContent() and map to push/inapp/chat so it uses per-type templates
- **Recipe**: [Keep Notification Template Variables Reference Up to Date](keep-notification-template-variables-up-to-date.md) — When adding or changing variables, update the UI reference

## Implementation Journal

- [Notification Templates (2026-01-30)](../../journal/2026-01-30-notification-templates.md)
