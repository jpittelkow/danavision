# Settings Restructure Roadmap

Reorganize settings architecture to separate user preferences from application-wide configuration.

**Priority**: HIGH (FOUNDATION)  
**Status**: ✅ COMPLETE  
**Last Updated**: 2026-01-27  
**Completed**: 2026-01-27

> **Note**: This roadmap is foundational. Many other roadmaps (Notifications, Admin Features, Integration Settings, Branding, Versioning) depend on the `/configuration` page and user settings infrastructure created here.

---

## Task Checklist

### User Settings Dropdown (HIGH Priority) ✅
- [x] Create user settings dropdown component in header (from user name, top right)
- [x] Move user-specific settings to user dropdown (theme, preferences, notification channels)
- [x] Add "My Profile" page for user account details
- [x] Add "My Preferences" page for personal settings
- [x] Remove theme selector from header (already available in user preferences)

### Notification Preferences Migration (HIGH Priority) ✅
- [x] Move notification channel preferences from `/settings/notifications` to User Preferences
- [x] Create notification preferences UI in user preferences page (email, push, in-app toggles)
- [x] Separate user notification preferences from admin notification provider configuration
- [x] Update backend to store notification preferences per-user
- [x] Keep admin notification provider settings (API keys, SMTP config) in `/configuration/notifications`

### Configuration Page Consolidation (HIGH Priority) ✅
- [x] Combine current `/settings` and `/admin` into single `/configuration` route
- [x] Create unified configuration navigation sidebar
- [x] Update all existing settings pages to new location
- [x] Update navigation and breadcrumbs
- [x] Add redirects from old `/settings/*` and `/admin/*` routes

### Backend Restructure (MEDIUM Priority) ✅
- [x] Ensure clear separation of user settings vs system settings in API
- [x] Add user settings endpoint (`/api/user/settings`)
- [x] Rename/reorganize system settings endpoints

---

## Current State

**Problem**: Settings are currently split across two areas with unclear boundaries:
- `/settings/*` - Mix of user preferences and app configuration
- `/admin/*` - Admin-only features (backup, etc.)

**Issues**:
- Confusing for users: "Where do I change my notification preferences?"
- Unclear admin vs user permissions
- No quick access to personal settings
- Theme selector duplicated in header (also in user settings) - remove from header

---

## Proposed Architecture

### 1. User Settings Dropdown (Top Right Header)

Location: Dropdown from user's name/avatar in header

**Contains**:
- **My Profile** - User account details (name, email, password)
- **My Preferences** - Personal settings:
  - Theme (dark/light mode)
  - Notification preferences (which channels to receive on)
  - Language/locale (future)
  - Default LLM mode
- **Sign Out**

**Implementation**:
```
frontend/components/header.tsx
├── User dropdown trigger (name/avatar)
└── Dropdown menu
    ├── My Profile → /user/profile
    ├── My Preferences → /user/preferences
    ├── Divider
    └── Sign Out
```

**New Routes**:
- `/user/profile` - Edit profile information
- `/user/preferences` - Personal preferences (theme, notifications, etc.)

### 2. Configuration Page (App-Wide Settings)

Single unified location for all application configuration (admin only).

**Route**: `/configuration`

**Contains** (consolidated from current `/settings` and `/admin`):
- **System Settings** - App name, timezone, registration settings
- **Users** - User management (list, create, edit, disable)
- **Notifications** - System notification channel configuration (API keys, etc.)
- **Email** - SMTP/provider configuration
- **Storage** - File storage settings
- **Backup** - Backup configuration and management
- **API & Webhooks** - API tokens, webhook configuration
- **Branding** - Logo, colors, custom CSS

**Navigation Structure**:
```
/configuration
├── /configuration/system      - System/Application Settings
├── /configuration/users       - User Management
├── /configuration/sso         - SSO/Authentication Providers
├── /configuration/notifications - Notification Providers (admin config)
├── /configuration/email       - Email Configuration
├── /configuration/storage     - Storage Settings
├── /configuration/backup      - Backup Management
├── /configuration/api         - API & Webhooks
└── /configuration/branding    - Theme/Branding
```

---

## Settings Classification

### User Settings (per-user, stored with user_id)

| Setting | Current Location | New Location |
|---------|------------------|--------------|
| Theme preference | `/settings/general` | User dropdown → Preferences |
| Notification channels | `/settings/notifications` | User dropdown → Preferences |
| Default LLM mode | `/settings/general` | User dropdown → Preferences |
| Profile info | `/settings/profile` | User dropdown → Profile |

### System Settings (app-wide, admin only)

| Setting | Current Location | New Location |
|---------|------------------|--------------|
| Notification provider config | `/settings/notifications` | `/configuration/notifications` |
| Backup settings | `/admin/backup` | `/configuration/backup` |
| User management | N/A | `/configuration/users` |
| System settings | N/A | `/configuration/system` |
| Email config | N/A (env only) | `/configuration/email` |

---

## Implementation Plan

### Phase 1: User Settings Dropdown

1. **Create dropdown component** in header
   - Modify `frontend/components/header.tsx`
   - Add dropdown with Profile, Preferences, Sign Out

2. **Create user pages**
   - `frontend/app/(dashboard)/user/profile/page.tsx`
   - `frontend/app/(dashboard)/user/preferences/page.tsx`

3. **Move user settings**
   - Theme toggle → User preferences
   - Profile editing → User profile

4. **Migrate notification preferences**
   - Move notification channel toggles (email, push, in-app) to user preferences
   - Create per-user notification settings UI with toggles for each channel
   - Add notification frequency/digest options (immediate, daily, weekly)
   - Ensure notification preferences are stored per-user in database
   - Keep admin-only notification provider config separate (API keys, SMTP)

### Phase 2: Configuration Consolidation

1. **Create configuration layout**
   - `frontend/app/(dashboard)/configuration/layout.tsx`
   - Sidebar navigation for all config sections

2. **Move existing pages**
   - `/admin/backup` → `/configuration/backup`
   - `/settings/*` admin pages → `/configuration/*`

3. **Update navigation**
   - Main nav: Replace "Settings" with "Configuration"
   - Update all internal links
   - Add breadcrumbs

### Phase 3: Backend Alignment

1. **User settings API**
   - `GET/PUT /api/user/settings` - Personal preferences
   - `GET/PUT /api/user/profile` - Profile info

2. **System settings API**
   - `GET/PUT /api/configuration/*` - Admin-only endpoints
   - Clear admin middleware on all configuration routes

---

## UI Mockup

```
┌─────────────────────────────────────────────────────────────┐
│  [Logo] Sourdough          Dashboard | Configuration    [User ▼]
│                                                         ┌────────────┐
│                                                         │ My Profile │
│                                                         │ Preferences│
│                                                         │ ────────── │
│                                                         │ Sign Out   │
│                                                         └────────────┘
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Configuration                                              │
│  ┌──────────────┐  ┌──────────────────────────────────────┐│
│  │ System       │  │                                      ││
│  │ Users        │  │  [Active Section Content]            ││
│  │ Notifications│  │                                      ││
│  │ Email        │  │                                      ││
│  │ Storage      │  │                                      ││
│  │ Backup       │  │                                      ││
│  │ API          │  │                                      ││
│  │ Branding     │  │                                      ││
│  └──────────────┘  └──────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Priority

| Component | Priority | Effort | Dependencies |
|-----------|----------|--------|--------------|
| User dropdown component | HIGH | Low | None |
| User profile page | HIGH | Low | Dropdown |
| User preferences page | HIGH | Medium | Dropdown |
| Configuration layout | HIGH | Medium | None |
| Migrate backup page | HIGH | Low | Config layout |
| Migrate notification config | MEDIUM | Medium | Config layout |
| System settings page | MEDIUM | Medium | Config layout |
| User management page | MEDIUM | High | Config layout |

---

## Migration Notes

### URL Redirects

For backward compatibility, add redirects:
- `/settings/*` → `/configuration/*` (where applicable)
- `/admin/*` → `/configuration/*`

### Permission Updates

- User dropdown pages: Authenticated users only
- Configuration pages: Admin role required

---

## Files to Create

**Frontend**:
- `frontend/components/user-dropdown.tsx` - User settings dropdown
- `frontend/app/(dashboard)/user/layout.tsx` - User pages layout
- `frontend/app/(dashboard)/user/profile/page.tsx` - Profile page
- `frontend/app/(dashboard)/user/preferences/page.tsx` - Preferences page
- `frontend/app/(dashboard)/configuration/layout.tsx` - Config layout
- `frontend/app/(dashboard)/configuration/page.tsx` - Config index

**Files to Modify**:
- `frontend/components/header.tsx` - Add user dropdown
- `frontend/components/sidebar.tsx` - Update navigation
- `frontend/app/(dashboard)/admin/*` - Move to configuration
- `frontend/app/(dashboard)/settings/*` - Split between user/configuration

---

## Related Roadmaps

- [Admin Features Roadmap](admin-features-roadmap.md) - User management, system settings
- [Integration Settings Roadmap](integration-settings-roadmap.md) - Email, storage, API settings
- [Notifications Roadmap](notifications-roadmap.md) - Notification preferences split
