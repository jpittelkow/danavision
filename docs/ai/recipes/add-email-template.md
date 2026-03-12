# Recipe: Add Email Template

Step-by-step guide to add a new email template to the system.

## Architecture Overview

Email templates are stored in the `email_templates` table and seeded from `EmailTemplateSeeder`. The seeder defines canonical default content; the admin API can update and reset templates. Application code renders templates via `EmailTemplateService::render($key, $variables)`.

See [ADR-016: Email Template System](../../adr/016-email-template-system.md).

## Files to Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/database/seeders/EmailTemplateSeeder.php` | Modify | Add default template definition and `getDefaultForKey` entry |

No new files are required; new templates are added by extending the seeder.

## Step 1: Add Template to Seeder Defaults

Edit `EmailTemplateSeeder::defaults()` and add a new entry keyed by template key:

```php
// backend/database/seeders/EmailTemplateSeeder.php

protected static function defaults(): array
{
    $appName = config('app.name', 'Sourdough');
    return [
        // ... existing templates ...

        'my_new_template' => [
            'key' => 'my_new_template',
            'name' => 'My New Template',
            'description' => 'Sent when ... (admin help text).',
            'subject' => 'Subject line with {{variable}} placeholders',
            'body_html' => "<p>Hi {{user.name}},</p><p>...</p><p>— {{app_name}}</p>",
            'body_text' => "Hi {{user.name}},\n\n...\n\n— {{app_name}}",
            'variables' => ['user.name', 'user.email', 'app_name', 'custom_url'],
            'is_system' => true,
            'is_active' => true,
        ],
    ];
}
```

### Template key naming

- Use `snake_case`: e.g. `password_reset`, `email_verification`, `welcome`, `notification`.
- Key is unique and used in API and in code: `$templateService->render('my_new_template', $vars)`.

### Variable naming

- Use dot notation for related data: `user.name`, `user.email`.
- List all placeholders used in subject/body in the `variables` array (for admin UI and documentation).
- Pass nested arrays when rendering: `['user' => ['name' => '...', 'email' => '...'], 'custom_url' => '...']`.

## Step 2: Run the Seeder

Either run the seeder once so the new template exists:

```bash
php artisan db:seed --class=EmailTemplateSeeder
```

Or run migrations (if you added a new migration that calls the seeder):

```bash
php artisan migrate
```

Existing installs: run the seeder; it uses `updateOrCreate` by key so the new template is inserted without affecting existing ones.

## Step 3: Use the Template in Code (Optional)

Where you need to send the email (e.g. a controller or notification), use `EmailTemplateService::render()` and send via the **TemplatedMail** Mailable:

```php
use App\Mail\TemplatedMail;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Mail;

public function __construct(private EmailTemplateService $templateService) {}

public function sendMyEmail(User $user, string $customUrl): void
{
    $rendered = $this->templateService->render('my_new_template', [
        'user' => ['name' => $user->name, 'email' => $user->email],
        'app_name' => config('app.name'),
        'custom_url' => $customUrl,
    ]);

    Mail::to($user->email)->send(new TemplatedMail($rendered));
}
```

**Integration points (existing templates):** The built-in templates are wired as follows. Use the same pattern (render + TemplatedMail) when adding new flows.

- **password_reset** – `User::sendPasswordResetNotification($token)` (reset link uses `config('app.frontend_url')` + `/reset-password?token=...&email=...`)
- **email_verification** – `User::sendEmailVerificationNotification()` (verification link uses frontend URL `/verify-email?id=...&hash=...`)
- **notification** – `EmailChannel::send()` (title, message, action_url, action_text from notification data)
- **welcome** – Template exists; not yet wired to a listener (e.g. `Registered` event); add a listener if needed.

## Step 4: Verify in Admin UI

New templates appear automatically in **Configuration > Email Templates** (`/configuration/email-templates`):

1. Open the list page and confirm the new template appears (name, description, Active, System badge).
2. Click the row to open the editor. Edit subject and body with the TipTap editor; use **Insert variable** to add `{{variable}}` placeholders.
3. Use the **Preview** panel (updates as you type) to see rendered content with sample variables.
4. Use **Send test email** to send a test (requires email configured in Configuration > Email).
5. For system templates, **Reset to default** restores seeder content.

## Step 5: Test via API

1. **List templates**: `GET /api/email-templates` (auth + manage-settings). Confirm the new key appears.
2. **Get template**: `GET /api/email-templates/my_new_template`. Confirm subject, body_html, body_text, variables.
3. **Preview**: `POST /api/email-templates/my_new_template/preview` with body `{"variables": {"user": {"name": "Test", "email": "test@example.com"}, "app_name": "My App", "custom_url": "https://example.com"}}`. Optional: send `subject`, `body_html`, `body_text` for live preview of unsaved content. Confirm subject, html, text in response.
4. **Test send** (if email is configured): `POST /api/email-templates/my_new_template/test` with optional `{"to": "you@example.com"}`.

## Checklist

### Seeder

- [ ] New entry added to `EmailTemplateSeeder::defaults()` with key, name, description, subject, body_html, body_text, variables, is_system, is_active
- [ ] Seeder run (`php artisan db:seed --class=EmailTemplateSeeder` or migrate)

### Application usage (if applicable)

- [ ] `EmailTemplateService::render('key', $variables)` called with correct variables
- [ ] Email sent via `Mail::to($email)->send(new TemplatedMail($rendered))` (or equivalent)

### Verification

- [ ] Configuration > Email Templates list shows the new template
- [ ] Editor page loads and save/preview/test/reset work as expected
- [ ] `GET /api/email-templates` includes the new template
- [ ] `GET /api/email-templates/{key}` returns full content
- [ ] `POST /api/email-templates/{key}/preview` returns expected subject/html/text
- [ ] Optional: test send via `POST /api/email-templates/{key}/test`

## Existing Templates for Reference

| key | name | variables |
|-----|------|-----------|
| password_reset | Password Reset | user.name, user.email, reset_url, expires_in, app_name |
| email_verification | Email Verification | user.name, user.email, verification_url, app_name |
| welcome | Welcome Email | user.name, user.email, login_url, app_name |
| notification | Notification | user.name, title, message, action_url, action_text, app_name |

## Related Documentation

- [ADR-016: Email Template System](../../adr/016-email-template-system.md)
- [Patterns: EmailTemplateService](../patterns/email-template-service.md)
- [Patterns: Email Template Admin UI](../patterns/email-template-service.md)

### Implementation Journal

- [Email Template Infrastructure (2026-01-29)](../../journal/2026-01-29-email-template-infrastructure.md)
- [Email Template Integration Chunk D (2026-01-29)](../../journal/2026-01-29-email-template-integration-chunk-d.md)