# Recipe: Keep Notification Template Variables Reference Up to Date

When you add or change variables used in notification templates, keep the admin "Available Variables" panel in sync so editors see accurate descriptions.

## Where the reference lives

- **Backend**: `NotificationTemplateController::variableDescriptions()` in [backend/app/Http/Controllers/Api/NotificationTemplateController.php](../../../backend/app/Http/Controllers/Api/NotificationTemplateController.php). Returns a map of variable name → short description.
- **API**: The `show` endpoint (`GET /api/notification-templates/{id}`) includes `variable_descriptions` in the response.
- **Frontend**: The template editor page uses it in the collapsible "Available Variables" card; variables without an entry show "Available when sending." as fallback.

## When to update

1. **Adding a new notification type** that uses variables not yet in the map (e.g. a new `variables` entry in `NotificationTemplateSeeder::defaults()`).
2. **Introducing a new variable name** in an existing template type (e.g. adding `error_code` to `backup.failed`).
3. **Improving a description** for clarity.

## What to do

1. Open [backend/app/Http/Controllers/Api/NotificationTemplateController.php](../../../backend/app/Http/Controllers/Api/NotificationTemplateController.php).
2. Find the private method `variableDescriptions()`.
3. Add or update entries in the returned array. Use the **exact variable name** as in templates (e.g. `user.name`, `backup_name`). Keep descriptions short (one line).

Example — adding a new variable:

```php
private function variableDescriptions(): array
{
    return [
        'app_name' => 'Application name from settings',
        'user.name' => "Recipient user's display name",
        // ... existing entries ...
        'error_code' => 'Short error code for the failure',  // new
    ];
}
```

## Cross-check

- **Seeder**: Every name in any template’s `variables` array in `NotificationTemplateSeeder::defaults()` should have an entry in `variableDescriptions()` (or accept the fallback text).
- **Preview**: If the type uses the variable in preview, ensure `sampleVariables()` in the same controller includes it so the preview API can render it.

## Related

- [Recipe: Add Notification Template](add-notification-template.md) — Step 5 covers variable descriptions when adding a new type.
- [Patterns: Notification Template Admin UI](../patterns/notification-template-service.md) — Variables Reference panel behavior.
- **Rule**: [.cursor/rules/notification-template-variables.mdc](../../../.cursor/rules/notification-template-variables.mdc) — Reminder to update the reference when touching notification templates (applies when editing NotificationTemplateController, NotificationTemplateSeeder, or notification-templates frontend).
