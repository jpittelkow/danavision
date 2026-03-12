# Admin Features Roadmap

Admin-focused settings expansions for comprehensive application management.

**Priority**: HIGH  
**Status**: HIGH priority complete (User Management, System Settings)  
**Last Updated**: 2026-01-29

**Dependencies**:
- ~~[Settings Restructure](settings-restructure-roadmap.md)~~ - Complete
- ~~[Env to Database Migration](env-to-database-roadmap.md)~~ - Complete

---

## Task Checklist

### User Management (HIGH Priority)
- [x] Build User Management settings page for admin user CRUD
- [x] Add user list with pagination/search
- [x] Add create/edit/disable user functionality
- [x] Add role management
- [x] Send verification email to new users on creation
- [x] Add "Resend Verification Email" button in user management
- [x] Show email verification status in user list (verified/pending)

### System Settings (HIGH Priority)
- [x] Create System/Application Settings page for global configuration
- [x] Add timezone dropdown with common timezones
- [x] Add default locale dropdown (language/region)
- [x] Add default LLM mode dropdown (single, council, etc.)
- [x] Add registration settings (enabled/disabled, email verification)
- [x] Add session timeout configuration
- [x] Add password policy settings

### Scheduled Tasks (MEDIUM Priority)
- [x] Build Scheduled Tasks/Jobs monitoring and configuration
- [x] Add manual trigger (Run Now) for whitelisted commands (backup:run, log:cleanup, log:check-suspicious)
- [x] Add last run / run history (task_runs table)
- [ ] Add backup schedule configuration (existing in Backup & Restore settings)
- [ ] Add notification digest settings

### Audit Log (MEDIUM Priority)
- [ ] Implement Audit/Activity Log feature - See [Audit Logs & Logging Roadmap](audit-logs-roadmap.md)
- [ ] Track user logins and admin actions
- [ ] Add log viewer with filtering

### Message Templates (MEDIUM Priority) — Email Complete
- [x] Create Message Templates configuration page (email at `/configuration/email-templates`)
- [x] Add rich text editor for email templates (TipTap)
- [x] Create email template management (welcome, verification, password reset, notification)
- [x] Support template variables/placeholders ({{user.name}}, {{app.name}}, etc.)
- [x] Add preview functionality for templates
- [x] Add "Reset to Default" option for each template

### Per-Type Notification Templates (MEDIUM Priority)
- [ ] Create Notification Templates configuration page at `/configuration/notification-templates`
- [ ] Per-notification-type templates for push, in-app, and chat channel groups
- [ ] NotificationTemplateService with render, preview, reset
- [ ] Admin API and UI for editing notification templates
- [ ] Orchestrator `sendByType()` and channel integration

---

## 1. User Management Settings (HIGH VALUE)

**Purpose**: Admin UI for managing application users - essential for any multi-user application.

**Features**:
- View all users with pagination/search
- Create new users (invite or direct)
- Edit user details and roles
- Enable/disable user accounts
- Reset user passwords
- View user activity/sessions

### Email Verification

**New User Verification**:
- When admin creates a new user, automatically send verification email
- Option to skip verification (create as pre-verified) for internal users
- Show verification status in user list (verified badge or "Pending" indicator)

**Resend Verification Email**:
- Add "Resend Verification Email" action button in user row/details
- Only show for users with pending verification
- Rate limit resends (e.g., max 1 per 5 minutes per user)
- Log verification email sends in activity log

**User List Columns**:
- Name, Email, Role, Status (Active/Disabled), **Verified** (✓ or Pending), Created, Last Login

**Implementation scope**:
- New route: `/configuration/users`
- Backend: `UserController.php` with admin policies
- Backend: `ResendVerificationEmail` action endpoint
- Frontend: User list, user edit dialog, invite flow, verification status badge

---

## 2. System/Application Settings (HIGH VALUE)

**Purpose**: Centralized application configuration that admins can modify without redeploying.

**Features**:
- Application name and branding
- Default timezone and locale
- Registration settings (enabled/disabled, email verification required)
- Session timeout configuration
- Password policy settings (min length, complexity)
- Default LLM mode for new users

### Timezone Dropdown

Provide a dropdown with common timezones:
- Use IANA timezone database (e.g., `America/New_York`, `Europe/London`)
- Group by region for easier selection
- Show UTC offset in display (e.g., "America/New_York (UTC-05:00)")
- Store as IANA string in database

**Common timezones to include**:
- UTC
- America/New_York, America/Chicago, America/Denver, America/Los_Angeles
- Europe/London, Europe/Paris, Europe/Berlin
- Asia/Tokyo, Asia/Shanghai, Asia/Singapore
- Australia/Sydney

### Default Locale Dropdown

Provide a dropdown for language/region settings:
- `en-US` - English (United States)
- `en-GB` - English (United Kingdom)
- `es-ES` - Spanish (Spain)
- `fr-FR` - French (France)
- `de-DE` - German (Germany)
- `ja-JP` - Japanese (Japan)
- `zh-CN` - Chinese (Simplified)
- Additional locales as needed

**Affects**:
- Date/time formatting
- Number formatting
- Default language for new users (future i18n)

### Default LLM Mode Dropdown

Dropdown for default LLM interaction mode:
- **Single** - Use a single LLM provider
- **Council** - Use multiple LLMs and synthesize responses
- **Auto** - Automatically select based on query type (future)

This sets the default for new users; users can override in their preferences.

**Implementation scope**:
- New route: `/configuration/system`
- Backend: System settings stored in database, cached
- Frontend: Form-based configuration page with dropdowns

**Architecture considerations**:
- System settings can use existing `settings` table with `user_id = null`
- Or create dedicated `system_settings` table
- Cache system settings on boot, clear cache on update
- Consider Redis for multi-instance deployments

---

## 3. Scheduled Tasks/Jobs Settings (MEDIUM VALUE)

**Purpose**: Configure and monitor background jobs and scheduled tasks.

**Features**:
- View scheduled tasks
- Configure backup schedule (frequency, retention)
- Configure notification digest settings
- View job queue status
- Retry failed jobs

**Implementation scope**:
- New route: `/configuration/jobs`
- Integrate with Laravel Horizon or basic queue monitoring

---

## 4. Audit/Activity Log (MEDIUM VALUE)

**See [Audit Logs & Logging Roadmap](audit-logs-roadmap.md)** for the complete audit logging implementation plan.

This section integrates with the Audit Logs roadmap for:
- Audit log UI improvements
- User login tracking
- Admin action tracking
- Log filtering and export

---

## 5. Message Templates (MEDIUM VALUE)

**Purpose**: Allow admins to customize system emails and notification messages without code changes.

**Features**:
- Edit all system email templates via rich text editor
- Edit notification messages for each channel (email, push, in-app)
- Support template variables/placeholders
- Preview templates before saving
- Reset templates to defaults

### Email Templates

Configurable system emails:

| Template | Description | Variables |
|----------|-------------|-----------|
| **Welcome Email** | Sent to new users after registration | `{{user.name}}`, `{{user.email}}`, `{{app.name}}`, `{{login_url}}` |
| **Email Verification** | Verify email address | `{{user.name}}`, `{{verification_url}}`, `{{app.name}}`, `{{expires_in}}` |
| **Password Reset** | Reset password link | `{{user.name}}`, `{{reset_url}}`, `{{app.name}}`, `{{expires_in}}` |
| **Password Changed** | Confirmation of password change | `{{user.name}}`, `{{app.name}}`, `{{changed_at}}` |
| **Account Disabled** | Notification when account is disabled | `{{user.name}}`, `{{app.name}}`, `{{reason}}` |
| **Invitation** | Invite new user to join | `{{inviter.name}}`, `{{invite_url}}`, `{{app.name}}`, `{{expires_in}}` |

### Notification Messages

Configurable messages for each notification channel:

| Notification Type | Channels | Variables |
|-------------------|----------|-----------|
| **System Alert** | Email, Push, In-app | `{{alert.title}}`, `{{alert.message}}`, `{{app.name}}` |
| **Backup Complete** | Email, In-app | `{{backup.size}}`, `{{backup.date}}`, `{{app.name}}` |
| **Backup Failed** | Email, Push, In-app | `{{error.message}}`, `{{app.name}}` |
| **New User Registered** | Email (admin), In-app | `{{new_user.name}}`, `{{new_user.email}}`, `{{app.name}}` |
| **LLM Council Response** | In-app | `{{council.summary}}`, `{{council.providers}}` |

### Rich Text Editor

Use a modern rich text editor for template editing:

**Recommended options**:
- **TipTap** - Headless, highly customizable, React-native
- **Lexical** - Facebook's editor, good for complex content
- **Plate** - Built on Slate, shadcn/ui compatible

**Editor Features**:
- Bold, italic, underline formatting
- Headings (H1, H2, H3)
- Lists (bullet, numbered)
- Links
- Variable insertion dropdown ({{variable}})
- HTML source view (advanced users)

### Template Variables

Support dynamic placeholders that get replaced at send time:

```
{{user.name}}        - Recipient's name
{{user.email}}       - Recipient's email
{{app.name}}         - Application name
{{app.url}}          - Application URL
{{action_url}}       - Context-specific action URL (verify, reset, etc.)
{{expires_in}}       - Expiration time for links
{{current_date}}     - Current date (localized)
{{current_time}}     - Current time (localized)
```

**Variable Insertion UI**:
- Dropdown button in editor toolbar
- Click to insert variable at cursor
- Variables highlighted in editor (different color/style)
- Invalid variables flagged with warning

### Preview Functionality

- Live preview panel alongside editor
- Sample data for variable replacement
- Preview for each channel (email renders differently than push)
- Mobile preview for push notifications

### Implementation Scope

**New route**: `/configuration/messages`

**Sub-routes**:
- `/configuration/messages/emails` - Email templates
- `/configuration/messages/notifications` - Notification messages

**Backend**:
- `MessageTemplate` model for storing templates
- Template rendering service with variable replacement
- API endpoints for CRUD operations
- Seed default templates on install

**Frontend**:
- Rich text editor component
- Template list with categories
- Preview modal/panel
- Variable insertion toolbar

**Database Schema**:

```
message_templates table:
- id
- type (email, push, in-app)
- key (welcome_email, password_reset, etc.)
- subject (for emails)
- body (rich text/HTML)
- variables (JSON - available variables for this template)
- is_active (boolean)
- updated_by
- created_at
- updated_at
```

---

## Implementation Priority

| Feature | Priority | Effort | Value | Recommended Order |
|---------|----------|--------|-------|-------------------|
| User Management | HIGH | High | Very High | 1 |
| System Settings | HIGH | Medium | High | 2 |
| Message Templates | MEDIUM | High | High | 3 |
| Scheduled Tasks | MEDIUM | High | Medium | 4 |
| Audit Logging | MEDIUM | High | Medium | 5 |

---

## Architecture Considerations

### Settings Storage Pattern

For system-wide settings, consider:

```
settings table (existing - user settings)
├── user_id (nullable for system settings)
├── group
├── key  
└── value (JSON)

OR

system_settings table (new)
├── group
├── key
├── value (JSON)
├── is_public (visible to non-admins?)
└── updated_by
```

### Settings Caching Strategy

For performance with system settings:
- Cache system settings on boot
- Clear cache on setting update
- Consider Redis for multi-instance deployments

### Settings Validation

Implement validation rules per setting type:
- Type coercion (string to boolean, etc.)
- Range validation for numeric settings
- Enum validation for select fields
- Dependency validation (setting A requires setting B)

---

## Files Reference

**Existing Settings Files**:
- `frontend/app/(dashboard)/settings/layout.tsx` - Settings layout with navigation
- `frontend/app/(dashboard)/admin/backup/page.tsx` - Backup management (example)

**Backend**:
- `backend/app/Http/Controllers/Api/SettingController.php` - Generic settings
- `backend/routes/api.php` - API routes

**Documentation**:
- `docs/adr/012-admin-only-settings.md` - Admin-only settings decision

---

## Related Roadmaps

- [Settings Restructure](settings-restructure-roadmap.md) - Configuration page structure (prerequisite)
- [Env to Database Migration](env-to-database-roadmap.md) - Settings storage infrastructure (prerequisite)
- [Notifications](notifications-roadmap.md) - Notification templates overlap with Message Templates section here
- [Integration Settings](integration-settings-roadmap.md) - Email configuration overlap
