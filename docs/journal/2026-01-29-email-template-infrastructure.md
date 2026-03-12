# Email Template Infrastructure (Chunk B) - 2026-01-29

## Overview

Implemented Phases 6 and 7 of the Email Config Dependencies plan: database-stored email templates with variable replacement, EmailTemplateService, RenderedEmail DTO, and admin API (index, show, update, preview, test, reset). Backend-only; no frontend UI or integration into AuthController/EmailChannel yet (Chunk C and D).

## Implementation Approach

- **Model & migration**: `email_templates` table with key (unique), name, description, subject, body_html, body_text, variables (JSON), is_system, is_active. Migration runs EmailTemplateSeeder after creating the table so fresh installs get four default templates.
- **EmailTemplateService**: `getByKey()` (active only), `render($key, $variables)` for app code, `renderTemplate(EmailTemplate $template, $variables)` for admin preview of any template, `getAvailableVariables($key)`, `getDefaultContent($key)` for reset. Variable replacement via `{{variable}}` and `{{user.name}}` (dot notation); missing variables replaced with empty string.
- **RenderedEmail DTO**: Read-only subject, html, text for use by Mail facade or notification channels.
- **EmailTemplateController**: ApiResponseTrait; index (list), show (full template), update (subject, body_html, body_text, is_active), preview (render with provided/sample variables), test (send test email; 503 if email not configured), reset (restore system template to seeder defaults; 403 if not system). Test and preview use SettingService mail config and applyMailConfig for sending.
- **Routes**: `/api/email-templates` under `auth:sanctum` + `can:manage-settings`; list route before `{key}` routes.
- **Documentation**: ADR-016, architecture.md, patterns (EmailTemplateService), context-loading (Email Template Work), recipe add-email-template, features.md, api-reference.md, roadmaps and email-configuration-dependencies roadmap, this journal entry.

## Key Files

- `backend/database/migrations/2026_01_28_000012_create_email_templates_table.php`
- `backend/app/Models/EmailTemplate.php`
- `backend/app/Services/RenderedEmail.php`
- `backend/app/Services/EmailTemplateService.php`
- `backend/database/seeders/EmailTemplateSeeder.php`
- `backend/app/Http/Controllers/Api/EmailTemplateController.php`
- `backend/routes/api.php` (email-templates routes)
- `backend/app/Providers/AppServiceProvider.php` (EmailTemplateService singleton)
- `docs/adr/016-email-template-system.md`
- `docs/ai/recipes/add-email-template.md`

## Challenges Encountered

- **Preview/test for inactive templates**: Admin should preview or test any template. Service’s `render($key)` uses `getByKey()` (active only). Added `renderTemplate(EmailTemplate $template, $variables)` so the controller can pass the already-loaded template (by key, no active filter) and render it.
- **Reset defaults**: Default content lives in the seeder; reset needs one template’s defaults. Seeder exposes `getDefaultForKey($key)`; EmailTemplateService::getDefaultContent($key) delegates to it so the controller does not depend on the seeder directly.

## Observations

- Seeder-in-migration ensures new installs get templates without a separate seed step; existing installs can run `php artisan db:seed --class=EmailTemplateSeeder` to add new templates.
- Dot-notation variable resolution (`user.name` from `$variables['user']['name']`) keeps template placeholders simple and aligns with common usage (user, app_name, reset_url, etc.).

## Trade-offs

- EmailTemplateService depends on EmailTemplateSeeder for getDefaultContent (reset). Acceptable for this scope; alternatives would be a config file or duplicating defaults.
- No frontend UI in Chunk B; admins can use the API directly until Chunk C (Template Admin UI).

## Next Steps (Future Considerations)

- Chunk C: Admin UI for email templates (e.g. TipTap editor).
- Chunk D: Integrate templates into AuthController (password reset, verification), EmailChannel, and Mail; optional Laravel Mailable using EmailTemplateService.

## Testing Notes

- Run migration: table created and four templates seeded.
- GET `/api/email-templates`: list returned (auth + manage-settings).
- GET `/api/email-templates/password_reset`: full template returned.
- PUT `/api/email-templates/password_reset`: subject/body updated.
- POST `/api/email-templates/password_reset/preview`: returns subject, html, text with sample vars.
- POST `/api/email-templates/password_reset/test`: sends test email when email is configured; 503 when not.
- POST `/api/email-templates/password_reset/reset`: content reset to seeder defaults (system template only); 403 for non-system template.
