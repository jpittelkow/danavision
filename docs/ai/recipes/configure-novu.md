# Configure Novu Notification Infrastructure

Optional integration with [Novu](https://novu.co/) for multi-channel notifications, workflow orchestration, and the pre-built notification center UI.

## When to Use

- You want a single dashboard (Novu Cloud or self-hosted) to manage notification workflows.
- You want digest/batching, visual workflow editor, or the Novu React Inbox component.
- You are fine with an external dependency (Novu Cloud) or running Novu self-hosted.

When Novu is **not** configured, Sourdough uses its built-in notification system (see [ADR-005](../../adr/005-notification-system-architecture.md)).

## Configuration Steps

1. **Create a Novu account** (Cloud) or deploy [Novu self-hosted](https://docs.novu.co/community/self-hosting-novu/overview).
2. **Create an Application** in the Novu dashboard and note the **Application Identifier**.
3. **Create an API Key** (Settings → API Keys) and copy it.
4. In Sourdough: **Configuration → Novu**:
   - Enable Novu.
   - Paste **API Key** and **Application Identifier**.
   - For self-hosted: set **API URL** and **WebSocket URL** to your Novu instance.
5. **Test connection** (button on the same page).
6. **Create workflows** in the Novu dashboard for each notification type (see workflow map below).
7. **Sync subscribers**: run `php artisan novu:sync-subscribers` to push existing users to Novu (optional; users are also synced on login/register/update).

## Workflow Map

Sourdough triggers events by **workflow identifier**. Create workflows in Novu with these identifiers and wire channel steps (in-app, email, push, etc.) as needed.

| Notification type       | Novu workflow identifier | Key variables |
|-------------------------|--------------------------|---------------|
| `backup.completed`      | `backup-completed`       | `backup_name` |
| `backup.failed`         | `backup-failed`          | `backup_name`, `error_message` |
| `auth.login`            | `auth-login`             | `ip`, `timestamp` |
| `auth.password_reset`   | `auth-password-reset`    | — |
| `system.update`         | `system-update`          | `version` |
| `llm.quota_warning`     | `llm-quota-warning`      | `usage` |
| `storage.warning`       | `storage-warning`        | `usage`, `threshold`, `free_formatted`, `total_formatted` |
| `storage.critical`      | `storage-critical`       | `usage`, `threshold`, `free_formatted`, `total_formatted` |
| `suspicious_activity`   | `suspicious-activity`    | `alert_summary`, `alert_count` |
| `usage.budget_warning`  | `usage-budget-warning`   | `integration`, `percent`, `current_cost`, `budget` |
| `usage.budget_exceeded` | `usage-budget-exceeded`  | `integration`, `percent`, `current_cost`, `budget` |

All payloads include `user` (with `name` and `email`), and `app_name`. Type-specific variables are listed in the table above.

### Creating Workflows in Novu

1. Go to your Novu dashboard > Workflows > Create Workflow
2. Set the workflow identifier to match the table above (e.g., `backup-completed`)
3. Add channel steps: In-App, Email, Push, Chat, SMS, etc.
4. Use the payload variables in your templates: `{{user.name}}`, `{{app_name}}`, `{{backup_name}}`, etc.
5. Test the workflow from the Novu dashboard or via `php artisan novu:test`

## Artisan Commands

- `php artisan novu:sync-subscribers` — Sync all users to Novu as subscribers.
- `php artisan novu:test` — Test Novu connection.

## Related

- [ADR-025: Novu Notification Integration](../../adr/025-novu-notification-integration.md)
- [ADR-005: Notification System Architecture](../../adr/005-notification-system-architecture.md)

### Implementation Journal

- [Novu Notification Refactor (2026-02-07)](../../journal/2026-02-07-novu-notification-refactor.md)
- [Novu Turbopack Compat (2026-02-07)](../../journal/2026-02-07-novu-turbopack-compat.md)
