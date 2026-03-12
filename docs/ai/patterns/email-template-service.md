# EmailTemplateService Pattern

Use `EmailTemplateService` for rendering email templates with variable replacement. Send rendered content via the **TemplatedMail** Mailable:

```php
use App\Mail\TemplatedMail;
use App\Services\EmailTemplateService;
use App\Services\RenderedEmail;
use Illuminate\Support\Facades\Mail;

public function __construct(private EmailTemplateService $templateService) {}

// Render a template (active templates only)
$rendered = $this->templateService->render('password_reset', [
    'user' => ['name' => $user->name, 'email' => $user->email],
    'reset_url' => $resetUrl,
    'expires_in' => '60 minutes',
    'app_name' => config('app.name'),
]);

// Send via TemplatedMail (standard pattern)
Mail::to($user->email)->send(new TemplatedMail($rendered));

// Or use rendered content directly
$rendered->subject; // "Reset your password"
$rendered->html;    // HTML body
$rendered->text;    // Plain text body
```

- **TemplatedMail**: Accepts a `RenderedEmail` DTO; sets subject and HTML body. Use for all template-based sends so admins' customizations apply.
- Variable placeholders: `{{variable}}` or `{{user.name}}` (dot notation for nested arrays). Missing variables are replaced with empty string.
- For admin preview of any template (including inactive), use `renderTemplate(EmailTemplate $template, array $variables): RenderedEmail`.
- For **live preview of unsaved content** (e.g. admin UI), use `renderContent(string $subject, string $bodyHtml, ?string $bodyText, array $variables): RenderedEmail`. The preview API accepts optional `subject`, `body_html`, `body_text` in the request body and uses them when present.
- Default templates are defined in `EmailTemplateSeeder` and seeded on migration; reset-to-default uses `getDefaultContent($key)`.

**Integration points:** Built-in templates are wired as follows. Use the same pattern (render + TemplatedMail) for new flows.

- **password_reset** – `User::sendPasswordResetNotification($token)`; reset URL uses `config('app.frontend_url')` + `/reset-password?token=...&email=...`.
- **email_verification** – `User::sendEmailVerificationNotification()`; verification URL uses frontend `/verify-email?id=...&hash=...`.
- **notification** – `EmailChannel::send()`; passes user, title, message, action_url, action_text, app_name.
- **welcome** – Template exists; not yet wired (e.g. to `Registered` event).

## Email Template Admin UI

The admin UI for email templates lives under **Configuration > Email Templates** (`/configuration/email-templates`):

- **List page**: Fetches `GET /api/email-templates`; displays name, description, Active/Inactive badge, System badge, last updated; row click navigates to `/configuration/email-templates/[key]`.
- **Editor page**: Fetches `GET /api/email-templates/{key}`; form with subject (Input), body (TipTap WYSIWYG via `EmailTemplateEditor`), plain text (Textarea, optional), Active (Switch). Save calls `PUT /api/email-templates/{key}`. Reset to default (system templates only) calls `POST /api/email-templates/{key}/reset`.
- **EmailTemplateEditor**: TipTap with StarterKit, Link, Image, Placeholder; toolbar (Bold, Italic, headings, lists, link, image, variable picker). Controlled: `content` and `onChange(html)`. Variable picker inserts `{{variable}}` at cursor.
- **VariablePicker**: Dropdown listing template `variables`; on select, parent inserts `{{variable}}` into the editor.
- **Live preview**: Debounce (e.g. 500ms) then `POST /api/email-templates/{key}/preview` with current `subject`, `body_html`, `body_text`; render returned HTML in an iframe (sandbox). Preview API uses `EmailTemplateService::renderContent()` when body is provided.
- **Test email**: Dialog with email input (default current user); `POST /api/email-templates/{key}/test` with `{ to }`. Handle 503 when email is not configured.

**Key files:**
- `backend/app/Services/EmailTemplateService.php`
- `backend/app/Services/RenderedEmail.php`
- `backend/app/Mail/TemplatedMail.php`
- `backend/app/Models/EmailTemplate.php`
- `backend/app/Models/User.php` (sendPasswordResetNotification, sendEmailVerificationNotification overrides)
- `backend/app/Services/Notifications/Channels/EmailChannel.php`
- `backend/database/seeders/EmailTemplateSeeder.php`
- `backend/app/Http/Controllers/Api/EmailTemplateController.php`
- `frontend/app/(dashboard)/configuration/email-templates/page.tsx`
- `frontend/app/(dashboard)/configuration/email-templates/[key]/page.tsx`
- `frontend/components/email-template-editor.tsx`
- `frontend/components/variable-picker.tsx`
- `frontend/app/(dashboard)/configuration/layout.tsx` (navigation)

**Related:**
- [ADR-016: Email Template System](../../adr/016-email-template-system.md)
- [Recipe: Add Email Template](../recipes/add-email-template.md)

## Implementation Journal

- [Email Template Infrastructure (2026-01-29)](../../journal/2026-01-29-email-template-infrastructure.md)
- [Email Template Integration Chunk D (2026-01-29)](../../journal/2026-01-29-email-template-integration-chunk-d.md)
