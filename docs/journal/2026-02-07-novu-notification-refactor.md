# Novu Notification Refactor - 2026-02-07

## Overview

Implemented optional Novu integration for notifications per the Novu Notification Refactor plan. When Novu is configured (API key + app identifier), notifications route through Novu's API; when not configured, the existing NotificationOrchestrator and 13 channels remain the default.

## Implementation Summary

- **Phase 1 – Foundation:** Novu settings group in `settings-schema.php`, boot-time injection in ConfigServiceProvider, `NovuService` (SDK wrapper: triggerWorkflow, syncSubscriber, testConnection), `config/novu.php` with workflow map, `.env.example` NOVU_* vars, ADR-025.
- **Phase 2 – Backend:** NotificationOrchestrator delegates to Novu when enabled; subscriber sync on register/login/update and `novu:sync-subscribers` command; NovuSettingController (admin API) and public Novu config in system settings; NovuController for subscriber HMAC (`/api/novu/subscriber-hash`).
- **Phase 3 – Frontend:** Configuration → Novu page, conditional notification bell (Novu Inbox vs local dropdown), Novu Inbox wrapper with public settings, user preferences message when Novu enabled.
- **Phase 4:** `novu:test` artisan command; configure-novu recipe with workflow map and payload notes.
- **Phase 5:** ADR-005 and ADR-017 updated; features.md, context-loading.md, README (AI), roadmaps.md updated; journal entry and code review.

## Key Files

- Backend: `NovuService.php`, `NovuSettingController.php`, `NovuController.php`, `NotificationOrchestrator.php`, `config/novu.php`, `settings-schema.php` (novu group).
- Frontend: `configuration/novu/page.tsx`, `notification-bell.tsx`, `novu-inbox.tsx`, `app-config.tsx` (novu in public settings).
- Docs: `docs/adr/025-novu-notification-integration.md`, `docs/ai/recipes/configure-novu.md`.

## Related

- [ADR-025: Novu Notification Integration](../adr/025-novu-notification-integration.md)
- [Recipe: Configure Novu](../ai/recipes/configure-novu.md)
- [Roadmaps: Notification Refactor to Novu](../roadmaps.md)
