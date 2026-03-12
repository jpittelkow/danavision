# Email Configuration Dependencies Roadmap

Refactor email notifications to properly handle unconfigured state and disable dependent features.

**Priority**: HIGH  
**Status**: Complete  
**Last Updated**: 2026-01-29

**Dependencies**: None

---

## Problem Statement

Currently, email-dependent features (password reset, email verification) may be available in the UI even when email is not properly configured. This creates a poor user experience where users attempt actions that will fail.

**Expected Behavior**:
1. If email SMTP/provider is not configured → email channel should report as not available
2. If email channel is not available → password reset and email verification features should be disabled/hidden
3. Clear indication to admins that email needs configuration for these features

---

## Task Checklist

### Phase 1: Email Configuration Detection

- [x] Create `EmailConfigService` with `isConfigured()` that checks mail settings (host, port, credentials per provider)
- [x] Return `configured: false` in channel metadata when email isn't set up (NotificationSettingsController)
- [x] Add configuration status to public API (`/system-settings/public` features object)

### Phase 2: Feature Gating

- [x] Create `EmailConfigService` to check if email-dependent features are available
- [x] Gate password reset functionality on email availability
  - [x] Disable "Forgot Password" link when email not configured
  - [x] Return 503 error if reset attempted without email
- [x] Gate email verification functionality on email availability
  - [x] Auto-verify user when email not configured (registration)
  - [x] Return 503 on resend verification when email not configured
- [x] Add admin notification/warning when email-dependent features are disabled

### Phase 3: Admin UI Improvements

- [x] Show email configuration status in System Settings (warning banner when not configured)
- [x] Add warning banner when email is not configured with link to Mail settings
- [x] Disable "Email Verification Required" toggle when email not configured
- [ ] Add "Test Email" functionality to verify configuration (already exists in Mail settings)

### Phase 6: Email Template Infrastructure (Chunk B – 2026-01-29)

- [x] EmailTemplate model, migration, seeder with four default templates
- [x] EmailTemplateService, RenderedEmail DTO, variable replacement (`{{variable}}`, `{{user.name}}`)
- [x] EmailTemplateSeeder run from migration; getDefaultForKey for reset

### Phase 7: Email Template API (Chunk B – 2026-01-29)

- [x] EmailTemplateController (index, show, update, preview, test, reset)
- [x] Routes under `auth:sanctum` + `can:manage-settings` at `/api/email-templates`

### Phase 8: Email Templates Admin UI (Chunk C – 2026-01-29)

- [x] Email Templates list page at `/configuration/email-templates`
- [x] Template editor page with TipTap WYSIWYG editor
- [x] Variable picker for inserting `{{variable}}` placeholders
- [x] Live preview panel (debounced, supports unsaved content via preview API)
- [x] Send test email dialog
- [x] Reset to default for system templates
- [x] Preview API accepts optional subject, body_html, body_text for live preview

### Phase 4: Documentation

- [x] Document email configuration requirements
- [x] Document which features depend on email
- [x] Add troubleshooting guide for email issues

### Phase 9: Integrate Templates into Email Flows (Chunk D – 2026-01-29)

- [x] Create TemplatedMail Mailable class
- [x] Override sendPasswordResetNotification in User model (template-based reset link email)
- [x] Override sendEmailVerificationNotification in User model (template-based verification email)
- [x] Update EmailChannel to use EmailTemplateService and notification template

### Phase 10: Documentation (Chunk D – 2026-01-29)

- [x] Add Email Templates admin section to user-docs.md
- [x] Update roadmap status to Complete

---

## Implementation Notes

### Email Configuration Check

The `EmailChannel` should check for these settings:
- `MAIL_MAILER` - Must be set to a valid driver (smtp, mailgun, ses, postmark, etc.)
- For SMTP: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- For API providers: Respective API keys

### Feature Dependencies

Features that require email:
1. **Password Reset** - Sends reset link via email
2. **Email Verification** - Sends verification link via email
3. **User Invitations** (if implemented) - Sends invite link via email
4. **Notification emails** - General notification delivery

### Graceful Degradation

When email is not configured:
- Password reset: Show message "Password reset is not available. Contact your administrator."
- Email verification: Either skip requirement or prevent registration
- Notifications: Email channel simply not available; other channels still work

---

## Files Reference

**Backend**:
- `backend/app/Services/Notifications/Channels/EmailChannel.php` - Configuration detection; uses EmailTemplateService (Chunk D)
- `backend/app/Http/Controllers/Api/AuthController.php` - Gate password reset
- `backend/config/mail.php` - Mail configuration
- `backend/config/notifications.php` - Notification channel config
- **Email templates (Chunk B):** `backend/app/Models/EmailTemplate.php`, `backend/app/Services/EmailTemplateService.php`, `backend/app/Services/RenderedEmail.php`, `backend/app/Http/Controllers/Api/EmailTemplateController.php`, `backend/database/seeders/EmailTemplateSeeder.php`, `backend/routes/api.php` (email-templates routes)
- **Chunk D:** `backend/app/Mail/TemplatedMail.php`, `backend/app/Models/User.php` (sendPasswordResetNotification, sendEmailVerificationNotification)

**Frontend**:
- `frontend/app/(auth)/forgot-password/page.tsx` - Conditionally show/hide
- `frontend/app/(dashboard)/configuration/system/page.tsx` - Show email status
- Login/registration pages - Handle email verification requirement
- **Email templates (Chunk C):** `frontend/app/(dashboard)/configuration/email-templates/page.tsx`, `frontend/app/(dashboard)/configuration/email-templates/[key]/page.tsx`, `frontend/components/email-template-editor.tsx`, `frontend/components/variable-picker.tsx`

---

## Success Criteria

- [x] Email channel correctly reports configured/unconfigured status (via EmailConfigService)
- [x] Password reset is unavailable when email not configured (backend 503, frontend link hidden / page shows unavailable)
- [x] Email verification requirement is handled appropriately (auto-verify when email not configured)
- [x] Admins have clear visibility into email configuration status (System Settings banner)
- [x] No silent failures for email-dependent features
- [x] Admins can view and edit email templates via Configuration > Email Templates (Chunk C)
- [x] Password reset, email verification, and notification emails use customizable templates (Chunk D)
- [x] Admin and email configuration documentation in user-docs.md (Chunk D)
## Implementation Journal

- [Email Template Infrastructure (Chunk B)](../journal/2026-01-29-email-template-infrastructure.md)
- [Email Template Integration (Chunk D)](../journal/2026-01-29-email-template-integration-chunk-d.md)
