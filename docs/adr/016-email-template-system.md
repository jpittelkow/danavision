# ADR-016: Email Template System

## Status

Accepted

## Date

2026-01-29

## Context

Sourdough needs customizable email content for transactional emails (password reset, email verification, welcome, notifications). Hard-coded strings in code or views make it difficult for admins to tailor messaging without code changes. We need a single place to define and edit email templates with variable substitution.

## Decision

We will implement a **database-stored email template system** with:

- **EmailTemplate model**: Stores key, name, description, subject, body_html, body_text, variables list, is_system, is_active. Key is unique (e.g. `password_reset`, `email_verification`).
- **Variable replacement**: Placeholders `{{variable}}` and `{{user.name}}` (dot notation) resolved from an associative array. Missing variables replaced with empty string.
- **EmailTemplateService**: `getByKey()`, `render()`, `renderTemplate()`, `renderContent()`, `getAvailableVariables()`, `getDefaultContent()`. Renders only active templates when looking up by key; admin can preview any template via `renderTemplate(EmailTemplate $template, $variables)` or live preview of unsaved content via `renderContent(string $subject, string $bodyHtml, ?string $bodyText, array $variables)`.
- **RenderedEmail DTO**: Read-only object with subject, html, text for use by Mail facade or notification channels.
- **Seeder**: EmailTemplateSeeder defines default templates and is run from the migration so fresh installs get templates. Seeder exposes `getDefaultForKey($key)` for reset-to-default in the admin API.
- **Admin API**: EmailTemplateController under `auth:sanctum` + `can:manage-settings` with index, show, update, preview, test, reset. Test endpoint requires email configured (EmailConfigService::isConfigured()); returns 503 if not.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   Email Template System                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Admin API (manage-settings)                                     │
│    GET    /api/email-templates         → list                    │
│    GET    /api/email-templates/{key}   → show                    │
│    PUT    /api/email-templates/{key}   → update                   │
│    POST   /api/email-templates/{key}/preview  → render (no send)  │
│    POST   /api/email-templates/{key}/test   → send test email    │
│    POST   /api/email-templates/{key}/reset   → reset to default  │
│                                                                  │
│  Application code                                                │
│    EmailTemplateService::render($key, $variables) → RenderedEmail│
│    Only active templates are used for render($key, …).           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Default Templates

| key               | name               | variables |
|-------------------|--------------------|-----------|
| password_reset    | Password Reset     | user.name, user.email, reset_url, expires_in, app_name |
| email_verification| Email Verification | user.name, user.email, verification_url, app_name |
| welcome           | Welcome Email      | user.name, user.email, login_url, app_name |
| notification      | Notification       | user.name, title, message, action_url, action_text, app_name |

## Consequences

### Positive

- Admins can customize email subject and body without code changes.
- Templates are versioned in the database and can be reset to defaults.
- Variable replacement supports nested data (e.g. user.name) for consistent usage across password reset, verification, and notifications.
- Preview and test endpoints allow safe validation before sending.

### Negative

- Template content in DB can drift from seeder defaults if reset is not used; seeder defines canonical defaults.
- Service depends on EmailTemplateSeeder for getDefaultContent (reset); acceptable for this scope.

### Neutral

- Admin UI for templates (Chunk C) is implemented: Configuration > Email Templates with TipTap editor, variable picker, live preview, test email, reset.
- **Chunk D (integration):** Templates are integrated into email flows. `TemplatedMail` Mailable sends any `RenderedEmail`. User model overrides `sendPasswordResetNotification($token)` and `sendEmailVerificationNotification()` to render `password_reset` and `email_verification` templates and send via `TemplatedMail`. `EmailChannel` uses `EmailTemplateService::render('notification', ...)` and `TemplatedMail` for notification emails. Reset and verification links use `config('app.frontend_url')` so links point to the frontend.

## Related Decisions

- [ADR-005: Notification System Architecture](005-notification-system-architecture.md) - Email channel uses EmailTemplateService and the `notification` template (Chunk D).
- [ADR-014: Database Settings with Environment Fallback](014-database-settings-env-fallback.md) - Mail configuration (from address, etc.) remains in SettingService; templates are separate.

## Notes

### Key files

- `backend/app/Models/EmailTemplate.php`
- `backend/app/Services/EmailTemplateService.php`
- `backend/app/Services/RenderedEmail.php`
- `backend/app/Mail/TemplatedMail.php` – Mailable that sends a `RenderedEmail` (subject, html)
- `backend/app/Models/User.php` – overrides `sendPasswordResetNotification`, `sendEmailVerificationNotification` (use templates + TemplatedMail)
- `backend/app/Services/Notifications/Channels/EmailChannel.php` – uses `EmailTemplateService::render('notification', ...)` and `TemplatedMail`
- `backend/app/Http/Controllers/Api/EmailTemplateController.php`
- `backend/database/seeders/EmailTemplateSeeder.php`
- `backend/database/migrations/2026_01_28_000012_create_email_templates_table.php`
- `frontend/app/(dashboard)/configuration/email-templates/page.tsx`
- `frontend/app/(dashboard)/configuration/email-templates/[key]/page.tsx`
- `frontend/components/email-template-editor.tsx`
- `frontend/components/variable-picker.tsx`

### Recipe

- [Add Email Template](../ai/recipes/add-email-template.md)

## Implementation Journal

- [Email Template Infrastructure (2026-01-29)](../journal/2026-01-29-email-template-infrastructure.md)
- [Email Template Integration Chunk D (2026-01-29)](../journal/2026-01-29-email-template-integration-chunk-d.md)
