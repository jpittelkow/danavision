# Integration Settings Roadmap

Integration and configuration settings for external services and customization.

**Priority**: MEDIUM  
**Status**: Complete  
**Last Updated**: 2026-01-29

**Dependencies**:
- ~~[Settings Restructure](settings-restructure-roadmap.md)~~ - Complete
- ~~[Env to Database Migration](env-to-database-roadmap.md)~~ - Complete

---

## Task Checklist

- [x] Add SSO/Authentication provider configuration UI - See [SSO Settings Enhancement](sso-settings-enhancement-roadmap.md)
- [x] Add Email/SMTP configuration settings UI
- [x] Add Storage Settings for upload policies and stats
- [x] Create API tokens and webhook management settings
- [x] Add Theme/Branding customization settings

---

## 1. SSO/Authentication Configuration

**See [SSO Settings Enhancement Roadmap](sso-settings-enhancement-roadmap.md)** for the complete SSO configuration implementation plan.

SSO settings page already exists at `/configuration/sso`. The SSO Settings Enhancement roadmap covers additional improvements like setup instructions, test connection buttons, and per-provider save.

---

## 2. Email/SMTP Configuration (MEDIUM VALUE)

**Purpose**: Configure email delivery without requiring environment variable changes and redeploy.

**Features**:
- SMTP server settings
- Email provider selection with provider-specific configuration
- Test email functionality
- Email templates preview
- From address and name configuration

### Supported Email Providers

| Provider | Type | Notes |
|----------|------|-------|
| **SMTP (Generic)** | SMTP | Any SMTP server (self-hosted, ISP, etc.) |
| **SendGrid** | API | Popular transactional email service |
| **Mailgun** | API | Developer-friendly with good deliverability |
| **Mailtrap** | SMTP/API | Testing/staging environment emails |
| **AWS SES** | API | Cost-effective at scale, requires AWS setup |
| **Postmark** | API | Focused on transactional email delivery |
| **Resend** | API | Modern developer-first email API |
| **Brevo (Sendinblue)** | API | Marketing + transactional combined |
| **SparkPost** | API | Enterprise-grade deliverability |
| **Mandrill (Mailchimp)** | API | Mailchimp's transactional service |

### Provider Configuration Fields

**Generic SMTP**:
- Host, Port, Encryption (TLS/SSL/None)
- Username, Password
- From address, From name

**API-based Providers** (SendGrid, Mailgun, AWS SES, etc.):
- API Key (encrypted storage)
- Region/Domain (provider-specific)
- From address, From name

**Mailtrap** (Development/Testing):
- Inbox credentials (SMTP)
- API token (for Email Testing API)
- Project/Inbox selection

**Current state**: Email is env-only via `config/mail.php`.

**Implementation scope**:
- New route: `/configuration/email`
- Backend: Store email config in system settings (encrypted)
- Frontend: Form-based configuration with test email button
- Dynamic form fields based on selected provider
- Support multiple email providers with dynamic configuration
- Provider health check / connection test

**Security considerations**:
- Encrypt sensitive credentials (API keys, passwords)
- Store in system settings table with encryption
- Allow admin-only access
- Never expose credentials in API responses

---

## 3. Storage Settings (LOW-MEDIUM VALUE)

**Purpose**: Configure file storage and upload policies.

**Features**:
- Storage driver selection (local, S3, etc.)
- Upload size limits
- Allowed file types
- Storage usage statistics
- Cleanup policies

**Implementation scope**:
- New route: `/configuration/storage`
- Backend: Storage configuration and statistics endpoint
- Frontend: Storage settings form and usage dashboard

**Database schema**:

```
storage_settings:
- driver (local, s3, etc.)
- max_file_size (bytes)
- allowed_extensions (JSON array)
- s3_bucket (nullable)
- s3_region (nullable)
- cleanup_after_days (nullable)
```

---

## 4. API/Webhook Settings (LOW-MEDIUM VALUE)

**Purpose**: Manage API tokens and outgoing webhooks.

**Features**:
- Personal API token management
- Webhook endpoint configuration
- Webhook event selection
- Webhook delivery logs

**Implementation scope**:
- New route: `/configuration/api`
- Backend: API token generation/revocation, webhook management
- Frontend: Token list, webhook configuration UI

**Database schema**:

```
personal_access_tokens (Laravel Sanctum - may already exist):
- id
- tokenable_type
- tokenable_id
- name
- token (hashed)
- abilities (JSON)
- last_used_at
- expires_at
- created_at

webhooks:
- id
- user_id
- url
- events (JSON array)
- secret
- active
- last_triggered_at
- created_at
```

---

## 5. Theme/Branding Settings (LOW VALUE)

**Purpose**: Visual customization without code changes.

**Features**:
- Logo upload
- Primary color customization
- Dark mode defaults
- Custom CSS injection

**Implementation scope**:
- New route: `/configuration/branding`
- Backend: File upload for logo, store theme settings
- Frontend: Theme customization UI with live preview

**Storage considerations**:
- Store logo in public storage
- Store theme colors in system settings
- Custom CSS stored as text in settings (with validation)

---

## Implementation Priority

| Feature | Priority | Effort | Value | Status |
|---------|----------|--------|-------|--------|
| SSO Configuration | HIGH | Medium | High | ✅ Done - See [SSO Settings Enhancement](sso-settings-enhancement-roadmap.md) |
| Email Configuration | MEDIUM | Medium | Medium | ✅ Done |
| Storage Settings | LOW-MEDIUM | Medium | Medium | ✅ Done |
| API/Webhooks | LOW-MEDIUM | High | Medium | ✅ Done |
| Theme/Branding | LOW | Low | Low | ✅ Done |

---

## Files Reference

**Backend** (existing):
- `backend/config/mail.php` - Email configuration
- `backend/config/filesystems.php` - Storage configuration
- `backend/app/Http/Controllers/Api/SettingController.php` - Generic settings
- `backend/app/Http/Controllers/Api/SSOSettingController.php` - SSO settings (exists)

**Frontend** (existing):
- `frontend/app/(dashboard)/configuration/sso/page.tsx` - SSO settings page (exists)
- `frontend/app/(dashboard)/configuration/email/page.tsx` - Email settings page (exists)

**Documentation**:
- `docs/adr/012-admin-only-settings.md` - Admin-only settings decision
- [SSO Settings Enhancement Roadmap](sso-settings-enhancement-roadmap.md) - SSO enhancements
