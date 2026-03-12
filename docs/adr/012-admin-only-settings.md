# ADR-012: Admin-Only Settings Access

## Status

Accepted

## Date

2026-01-26

## Context

Currently, all authenticated users can access settings pages (Profile, Security, Notifications, AI/LLM Settings). These settings pages allow users to configure system-wide preferences and integrations. However, for a self-hosted application framework, it may be more appropriate to restrict configuration access to administrators only.

The current implementation shows settings cards on the dashboard for all users, and all users can navigate to settings pages. This creates a situation where:
- Regular users can modify system-wide settings (LLM providers, notification channels)
- Settings are mixed with user-specific preferences
- No clear separation between user preferences and system configuration

## Decision

We will restrict all settings pages to admin users only:

1. **Settings Layout Protection**: Add admin check to `frontend/app/(dashboard)/settings/layout.tsx` that redirects non-admin users to the dashboard, similar to the existing admin layout protection.

2. **Dashboard Updates**: Update the dashboard page to only show settings cards when the user is in the admin group (e.g. `user?.is_admin` or group-based check).

3. **Sidebar Integration**: The Settings button in the global left sidebar is already conditionally rendered for admin users only, providing a clear entry point.

4. **User Experience**: Regular users will see a simplified dashboard without settings options, focusing on their core functionality.

## Consequences

### Positive

- Clear separation between user features and system configuration
- Prevents non-admin users from modifying system-wide settings
- Simplified user experience for regular users
- Settings become a clear admin-only feature
- Better security posture by restricting configuration access
- Aligns with common patterns in self-hosted applications

### Negative

- Regular users lose ability to configure personal preferences (if any were intended)
- May require future separation of user preferences vs system settings if needed
- Existing non-admin users will lose access to settings immediately

### Neutral

- **[IMPLEMENTED]** User-specific preferences now live at `/user/preferences` and `/user/security`, separate from admin-only system settings under `/configuration/`
- Settings pages remain accessible to admins via sidebar and dashboard cards
- No changes to backend API - access control is frontend-only (backend should also validate)

## Related Decisions

- [ADR-011: Global Navigation Architecture](./011-global-navigation-architecture.md)
- [ADR-014: Database Settings with Environment Fallback](./014-database-settings-env-fallback.md) – Mail and other schema-backed settings are stored in the database with env fallback (SettingService); access remains admin-only per this ADR.

## Notes

This decision assumes that all current settings are system-wide configuration. If user-specific preferences are needed in the future, they should be implemented as a separate "Preferences" or "Account" section accessible to all users, distinct from the admin-only "Settings" section.

The backend API should also implement admin checks for settings endpoints to ensure security even if frontend checks are bypassed. This ADR focuses on frontend access control as part of the UI refactoring.
