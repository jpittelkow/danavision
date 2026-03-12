# Help Guide Permission Audit & Enforcement

**Date:** 2026-02-15

## Summary

Refactored the in-app help system from a binary admin/non-admin model to granular permission-based filtering, aligned with the existing permission system used throughout the application. Added 13 missing help articles to cover all configuration pages, and established enforcement mechanisms to keep help guides in sync with features.

## Changes

### Permission-Based Help Filtering (Part 1)

- **`HelpCategory` interface**: Replaced `adminOnly?: boolean` with `permission?: string`
- **Helper functions**: Changed `getAllCategories(isAdmin)`, `findArticle(articleId, isAdmin)`, and `getSearchableArticles(isAdmin)` to accept `permissions: string[]` instead of `isAdmin: boolean`
- **Split monolithic categories**: Broke the single "Admin Settings" category (10 articles) into 10 permission-aligned categories matching the configuration navigation groups (Administration, User Management, Security & Access, Communications, Integrations, Audit Logs, Application Logs, Log Settings & Jobs, Usage & Costs, Backup & Data)
- **Updated `HelpCenterModal`**: Passes `user?.permissions ?? []` instead of `isAdminUser(user)` boolean

### Missing Help Articles (Part 2)

Added 13 new help articles for config pages that had no help documentation:

| Article | Config Page | Permission |
|---|---|---|
| User Groups & Permissions | `/configuration/groups` | `groups.view` |
| Notification Channels | `/configuration/notifications` | `settings.view` |
| Notification Templates | `/configuration/notification-templates` | `settings.view` |
| Novu Configuration | `/configuration/novu` | `settings.view` |
| Storage Configuration | `/configuration/storage` | `settings.view` |
| Audit Log | `/configuration/audit` | `audit.view` |
| Application Logs | `/configuration/logs` | `logs.view` |
| Access Logs (HIPAA) | `/configuration/access-logs` | `logs.view` |
| Log Retention | `/configuration/log-retention` | `settings.view` |
| Scheduled Jobs | `/configuration/jobs` | `settings.view` |
| API & Webhooks | `/configuration/api` | `settings.view` |
| Usage & Costs | `/configuration/usage` | `usage.view` |
| Changelog | `/configuration/changelog` | (all users) |

### Search Entries (Part 3)

- Added search entries in `backend/config/search-pages.php` for all 13 new help articles
- Added `permission` field to all permission-gated help search entries alongside `admin_only`

### HelpLink Components

Added `HelpLink` import and usage to all 13 config pages that were missing contextual help links.

### Recipe & Process Updates (Parts 4-6)

- **`setup-features-auth.md` (Tier 2)**: Added Step 10 (Update Help Guides) with feature-to-article removal mapping and two new checklist items
- **`setup-new-project.md`**: Updated Tier 2 result line to mention help guide trimming
- **`add-help-article.md`**: Rewrote to reference `permission` field, include category-to-permission mapping table, and remove old `adminOnly` guidance
- **`.cursor/rules/help-guide-enforcement.mdc`**: New Cursor rule that triggers on config pages and controllers, enforcing help guide creation/update/removal

### Documentation Updates (Part 7)

- **`features.md`**: Updated "In-App Help & Onboarding" section to reference permission-based filtering
- **`context-loading.md`**: Updated Help/Documentation section with new key concepts and file references
- **`ui-patterns.md`**: Updated Help System pattern to reference `permission` field and `getAllCategories(permissions)`

## Files Modified

| Category | Files |
|---|---|
| Help system core | `frontend/lib/help/help-content.ts` |
| Help modal | `frontend/components/help/help-center-modal.tsx` |
| Search pages | `backend/config/search-pages.php` |
| Config pages (13) | `groups`, `notifications`, `novu`, `storage`, `audit`, `logs`, `access-logs`, `log-retention`, `jobs`, `api`, `usage`, `changelog`, `notification-templates` page.tsx files |
| Recipes | `add-help-article.md`, `setup-features-auth.md`, `setup-new-project.md` |
| Rules | `.cursor/rules/help-guide-enforcement.mdc` (new) |
| Docs | `features.md`, `context-loading.md`, `ui-patterns.md` |

## Impact

- Non-admin users with specific permissions (e.g., `backups.view`) can now see relevant help articles
- All configuration pages now have contextual help links
- The "Get Cooking" setup process properly trims help guides when features are removed
- New features will automatically trigger help guide creation via the Cursor rule
