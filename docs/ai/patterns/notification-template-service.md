# NotificationTemplateService Pattern

Use `NotificationTemplateService` for rendering per-type, per-channel-group notification templates (push, inapp, chat). Email continues to use EmailTemplateService (ADR-016).

```php
use App\Services\NotificationTemplateService;

public function __construct(private NotificationTemplateService $templateService) {}

// Render a template (active only) for a type and channel group
$rendered = $this->templateService->render('backup.completed', 'inapp', [
    'user' => ['name' => $user->name, 'email' => $user->email],
    'app_name' => config('app.name'),
    'backup_name' => $backup->name,
]);

// $rendered is ['title' => string, 'body' => string]
$title = $rendered['title'];
$body = $rendered['body'];
```

- **getByTypeAndChannel($type, $channelGroup)**: Find active template. Returns null if none.
- **render($type, $channelGroup, $variables)**: Render active template; throws if not found.
- **renderTemplate(NotificationTemplate $template, $variables)**: Admin preview of any template.
- **renderContent($title, $body, $variables)**: Live preview of unsaved content.
- **getDefaultContent($type, $channelGroup)**: For reset; delegates to NotificationTemplateSeeder::getDefaultFor().

Channel groups: `push` (WebPush, FCM, ntfy), `inapp` (DatabaseChannel), `chat` (Telegram, Discord, Slack, Twilio, Signal, Matrix, Vonage, SNS). Each channel's `send()` optionally resolves a template for (type, channel_group) when present; otherwise uses passed title/message. Use **NotificationOrchestrator::sendByType($user, $type, $variables, $channels)** to send using templates per channel.

## Notification Template Admin UI

The admin UI for notification templates lives under **Configuration > Notification Templates** (`/configuration/notification-templates`):

- **List page**: Fetches `GET /api/notification-templates`; displays type, channel group (Push/In-App/Chat), title, Active/Inactive badge, System badge, last updated; row click navigates to `/configuration/notification-templates/[id]`.
- **Editor page**: Fetches `GET /api/notification-templates/{id}`; form with title (Input), body (Textarea), Active (Switch). Save calls `PUT /api/notification-templates/{id}`. Reset to default (system templates only) calls `POST /api/notification-templates/{id}/reset`.
- **Live preview**: Debounce (e.g. 500ms) then `POST /api/notification-templates/{id}/preview` with current `title`, `body`; display returned title and body in a preview panel. Preview API uses `NotificationTemplateService::renderContent()` when title/body are provided.
- **Variables**: Template `variables` list is shown in the form description; use placeholders like `{{user.name}}`, `{{app_name}}`, `{{backup_name}}` in title and body. No separate variable picker component (simpler than email templates).
- **Variables Reference panel**: The editor page includes a collapsible "Available Variables" card (below the Preview card). The API returns `variable_descriptions` (from `NotificationTemplateController::variableDescriptions()`) alongside `variables` in `GET /api/notification-templates/{id}`. Each row shows the placeholder (e.g. `{{app_name}}`), a short description, and a Copy button. When adding a new notification type or a new variable name, add an entry to `variableDescriptions()` so the panel stays accurate.

**Key files:**
- `backend/app/Services/NotificationTemplateService.php`
- `backend/app/Models/NotificationTemplate.php`
- `backend/app/Services/Notifications/NotificationOrchestrator.php`
- `backend/app/Services/Notifications/Channels/*`
- `backend/app/Http/Controllers/Api/NotificationTemplateController.php`
- `backend/database/seeders/NotificationTemplateSeeder.php`
- `frontend/app/(dashboard)/configuration/notification-templates/page.tsx`
- `frontend/app/(dashboard)/configuration/notification-templates/[id]/page.tsx`
- `frontend/app/(dashboard)/configuration/layout.tsx` (Communications group navigation)
- `frontend/lib/search-pages.ts` (search entry)
- `backend/app/Services/Search/SearchService.php` (globalSearch, transformNotificationTemplateToResult)

**Related:**
- [ADR-017: Notification Template System](../../adr/017-notification-template-system.md)
- [Recipe: Add Notification Template](../recipes/add-notification-template.md)
- [Recipe: Keep Notification Template Variables Up to Date](../recipes/keep-notification-template-variables-up-to-date.md)

## Implementation Journal

- [Notification Templates (2026-01-30)](../../journal/2026-01-30-notification-templates.md)
- [Notifications Implementation (2026-01-27)](../../journal/2026-01-27-notifications-implementation.md)
