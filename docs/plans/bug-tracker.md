# Bug Tracker

Suspected bugs and issues discovered during development. Items here need investigation and may affect multiple locations in the codebase.

## Format

Each entry should include:
- **Short title** describing the suspected bug
- **Where found** — file path and line number
- **What's wrong** — observed behavior or suspicion
- **Scope** — could this affect other locations? List suspected files if known
- **Severity** — Critical / High / Medium / Low / Unknown
- **Date added**

## Active Bugs

_(No active bugs)_

## Under Investigation

_(Bugs currently being looked into)_

## Resolved

### New data table component from configuration/users should be adopted globally
- **Where found**: `frontend/app/(dashboard)/configuration/users/`
- **What's wrong**: A new/improved table component is used on the users configuration page but other pages still use older table patterns.
- **Resolution**: Migrated `GroupTable` to use the `DataTable` component (TanStack React Table), matching the `UserTable` pattern. Audit logs, access logs, and notification deliveries pages intentionally kept on raw `Table` primitives — they have complex custom features (live streaming, row highlighting, export, inline detail modals) that don't fit the generic `DataTable` abstraction. The `UsageProviderTable` also stays on raw `Table` due to its custom footer totals row. Date: 2026-03-09.

### Dashboard homepage needs section-based redesign
- **Where found**: `frontend/app/(dashboard)/dashboard/page.tsx`
- **What's wrong**: Example widgets used static placeholder data instead of real APIs.
- **Resolution**: Wired all 6 example widgets to real backend APIs: RecentActivityWidget → `/audit-logs`, NotificationsWidget → `/notifications/unread-count` + `/notifications`, StorageOverviewWidget → `/storage-settings/stats` + `/storage-settings/health`, UpcomingTasksWidget → `/jobs/scheduled`, SystemHealthWidget → `/storage-settings/health` + `/jobs/queue-status`, EnvironmentWidget → `/dashboard/environment` (new endpoint). All widgets now have loading skeletons and empty states. Date: 2026-03-09.

### Registration test fails with 500 — missing cache directory
- **Where found**: `tests/Feature/AuthTest.php:17`, error in `Filesystem.php:738`
- **What's wrong**: Registration triggered cache writes via GroupService/PermissionService. File cache driver used in tests needed cache subdirectories that didn't exist.
- **Resolution**: Force `array` cache driver in `TestCase::setUp()` so tests never hit the filesystem cache. Date: 2026-03-09.

### GET /storage-settings returns incomplete data (missing provider config)
- **Where found**: `backend/app/Http/Controllers/Api/StorageSettingController.php:30`
- **What's wrong**: Storage settings schema only had 4 alert keys. `show()` uses `settingService->getGroup()` which reads from the schema, so `driver`, `max_upload_size`, and all provider credential keys were missing from the API response.
- **Resolution**: Added all 30+ storage keys (core, S3, GCS, Azure, DO Spaces, MinIO, B2) to `settings-schema.php`. Also added missing `registration`, `security`, and `defaults` groups needed by the System Settings page. Date: 2026-03-09.

### Avatar upload accepts oversized files and does not resize properly
- **Where found**: `backend/app/Http/Controllers/Api/ProfileController.php`, `frontend/components/user/avatar-upload.tsx`
- **What's wrong**: No image resizing — users could upload large images that bloated storage.
- **Resolution**: Added client-side Canvas resize to 512x512 max (JPEG 85% quality) before upload. Added backend `dimensions` validation (min 64x64, max 2048x2048). ~95% storage reduction for typical avatar uploads. Date: 2026-03-09.

### Changelog entries incorrect after v0.9.1 and bullet point style misalignment
- **Where found**: `CHANGELOG.md`
- **What's wrong**: Push script was injecting `### Changed\n- Release vX.Y.Z` into each version with no blank line before the next `## [version]` header. v0.9.2 had 4 duplicate entries. Excess blank lines at the top of the file.
- **Resolution**: Cleaned up CHANGELOG.md — removed duplicate entries, removed "Release vX.Y.Z" noise, added proper blank lines between all version sections, removed excess blank lines at top. Parser (`ChangelogService.php`) was correct; data was malformed. Date: 2026-03-09.

### WebPush / VAPID test notification fails with invalid payload
- **Where found**: `backend/app/Services/Notifications/Channels/WebPushChannel.php`
- **What's wrong**: Three issues: (1) VAPID keys read in constructor but channel instances cached by singleton orchestrator — runtime config updates from `applyNotificationsConfigForRequest` never reached the channel. (2) `send()` returned normally even when ALL notifications failed (non-expired), so test endpoint reported "success" for failed sends. (3) `sendOneNotification` exceptions were uncaught, bubbling up with library-internal messages instead of user-friendly errors.
- **Resolution**: Moved VAPID key reads from constructor to `send()` method so runtime config updates are always picked up. Added throw when all sends fail with collected error reasons. Wrapped `sendOneNotification` in try/catch. Added explicit `contentEncoding` to `Subscription::create()`. Date: 2026-03-09.

### Avatar image squishes when uploaded
- **Where found**: `frontend/components/user/avatar-upload.tsx`
- **What's wrong**: `<AvatarImage>` missing `object-cover`, so non-square images get distorted.
- **Resolution**: Added `className="object-cover"` to `<AvatarImage>`. Date: 2026-03-09.

### Breadcrumb "Account" link gives 404
- **Where found**: `frontend/components/app-breadcrumbs.tsx`
- **What's wrong**: `/dashboard/user` segment was rendered as a clickable link but no page exists at that route.
- **Resolution**: Added `NON_NAVIGABLE_SEGMENTS` set — segments listed there render as non-clickable `BreadcrumbPage` instead of `BreadcrumbLink`. Date: 2026-03-09.

### Disabling 2FA requires password but frontend sends none
- **Where found**: `backend/app/Http/Controllers/Api/TwoFactorController.php`, `frontend/components/user/security/two-factor-section.tsx`
- **What's wrong**: Backend `disable()`, `recoveryCodes()`, and `regenerateRecoveryCodes()` required password validation, but frontend sent no body — causing 422 errors.
- **Resolution**: Removed password validation from all three controller methods (`disable`, `recoveryCodes`, `regenerateRecoveryCodes`). Date: 2026-03-09.

### 2FA TOTP verification fails — InvalidCharactersException in base32 string
- **Where found**: `backend/app/Services/Auth/TwoFactorService.php`
- **What's wrong**: Decrypted `two_factor_secret` could contain whitespace or `=` padding characters that Google2FA's strict base32 validator rejects.
- **Resolution**: Added `rtrim(trim(...), '=')` sanitization on the secret before passing to `verifyKey()`. Date: 2026-03-09.

### WebPush/queue workers crash — "Not enough arguments (missing: queues)"
- **Where found**: `docker/supervisord.conf`
- **What's wrong**: `queue:work` command missing the queue connection argument, causing workers to crash on startup.
- **Resolution**: Added explicit `database` connection argument: `queue:work database --sleep=3 --tries=3 --max-time=3600`. Date: 2026-03-09.

### Notification delivery log is blank despite notifications being sent
- **Where found**: `frontend/components/notifications/delivery-log-tab.tsx`
- **What's wrong**: Frontend status type, badge variants, labels, and stats cards were missing the `"queued"` status that the backend writes for async notifications.
- **Resolution**: Added `"queued"` to `NotificationDeliveryRecord` status union, `STATUS_VARIANTS` (blue badge), `STATUS_LABELS`, and stats card grid (now 5 columns). Queue worker fix (above) also resolves records stuck in `queued` state. Date: 2026-03-09.

### AI provider edit dialog blocks model discovery — key field is blank but no fallback
- **Where found**: `frontend/components/ai/provider-dialog.tsx`, `backend/app/Http/Controllers/Api/LLMModelController.php`
- **What's wrong**: In edit mode, API key field intentionally blank (security), but discover/test guards required a key — making it impossible to refresh models without re-entering the key.
- **Resolution**: Frontend: skip credential guards in edit mode (`isEditing`), pass `provider_id` in credential payload. Backend: accept optional `provider_id` in discover/test-key endpoints, fall back to stored `AIProvider` credentials when request credentials are missing. Date: 2026-03-09.

### Theme setting in config overwritten by user preference
- **Where found**: `frontend/components/providers.tsx`, `frontend/components/theme-provider.tsx`, `frontend/lib/app-config.tsx`
- **What's wrong**: Admin-configured default theme (`defaults.default_theme`) was never read by the frontend. `ThemeProvider` used a hardcoded `"system"` fallback. The `defaults` group was also missing from `settings-schema.php`, so saving it from the System Settings page would have been rejected by schema validation.
- **Resolution**: Added `defaults` group to `settings-schema.php` with `default_theme` marked `public`. Added `defaultTheme` to `AppConfigState` (read from `/system-settings/public` response). Created `ConfiguredThemeProvider` bridge in `providers.tsx` that passes the admin default. Added effect in `ThemeProvider` to apply the prop when no localStorage preference exists. Priority: localStorage (user) > admin default > hardcoded "system". Date: 2026-03-09.
