# ADR-025: Novu Notification Integration

## Status

Accepted

## Date

2026-02-07

## Context

Sourdough has a custom notification system (ADR-005, ADR-017) with 13 channels, per-channel templates, and a custom in-app notification UI. To support workflow orchestration, digest/batching, a pre-built notification center, and simpler multi-channel management, we want to support [Novu](https://novu.co/) as an optional unified notification infrastructure. Novu can be used as Cloud (SaaS) or self-hosted; the existing system must remain fully functional when Novu is not configured.

## Decision

We will integrate Novu as an **optional** notification path:

- **When Novu is configured** (API key + application identifier): Notifications are sent via the Novu API; the frontend uses the `@novu/react` Inbox component for the notification center; users are synced as Novu subscribers.
- **When Novu is not configured**: The full existing NotificationOrchestrator and all 13 custom channels continue to be used; the existing notification bell and dropdown are shown.

### Deployment

- Support both **Novu Cloud** (default API URL) and **self-hosted Novu** (user provides API URL and API key in Configuration).
- Settings are stored in the database with env fallback (SettingService, `novu` group in settings-schema).

### Mode Detection

- **Backend**: `NovuService::isEnabled()` checks `config('novu.enabled')` and a non-empty API key (config is injected at boot from SettingService).
- **Frontend**: Public system settings expose `novu.enabled`, `novu.app_identifier`, `novu.api_url`, `novu.socket_url` so the app can conditionally render the Novu Inbox or the local notification bell.

### Subscriber Sync

- Each user is represented in Novu as a subscriber with ID `user_{id}` (stable, unique).
- Subscribers are created/updated on user registration, login, and profile update; a bulk sync command `novu:sync-subscribers` is provided for initial or remedial sync.

### Workflows

- Existing notification types (e.g. `backup.completed`, `auth.login`) are mapped to Novu workflow identifiers via `config('novu.workflow_map')`. When Novu is enabled, the orchestrator triggers the corresponding workflow with the user as subscriber and passes template variables as payload.

### Frontend

- When Novu is enabled and `app_identifier` is set, the header shows the `@novu/react` Inbox component (bell + popover). When disabled, the existing NotificationBell and NotificationDropdown are used.
- HMAC subscriber authentication is used for the Inbox when configured (backend endpoint provides hash for the current user).
- **Next.js 16 / Turbopack compatibility**: `@novu/react` requires client-only rendering. Load the Inbox wrapper via `next/dynamic({ ssr: false })`, and apply the Turbopack resolution workaround (`frontend/next.config.js` alias + `frontend/scripts/patch-novu-exports.js` via `predev`/`prebuild`) to avoid module resolution issues with Novu’s exports map.

## Consequences

### Positive

- Optional adoption: no change for existing or fresh installs until Novu is configured.
- Single code path for triggering: callers still use `NotificationOrchestrator::sendByType()` or `send()`; the orchestrator delegates to Novu or local channels.
- Novu provides workflow editor, digest, and multi-channel delivery without maintaining custom channel code when Novu is used.
- Self-hosted or Cloud choice remains with the deployer.

### Negative

- Two notification UIs and two backend paths to maintain (Novu path vs local path).
- Novu workflows must be created in the Novu dashboard (or via API) to match the workflow map; documentation or export is required.

### Neutral

- Novu SDK is an optional Composer dependency; if not installed, Novu mode is effectively disabled.

## Related Decisions

- [ADR-005: Notification System Architecture](005-notification-system-architecture.md) — existing orchestrator and channels; Novu is an alternative orchestration mode.
- [ADR-017: Notification Template System](017-notification-template-system.md) — when Novu is enabled, templates are managed in Novu; local templates remain for local mode.

## Notes

### Key files

- `backend/app/Services/NovuService.php` — SDK wrapper, trigger, subscriber sync, test connection
- `backend/config/novu.php` — workflow map and defaults
- `backend/config/settings-schema.php` — `novu` group
- `backend/app/Providers/ConfigServiceProvider.php` — injectNovuConfig
- `frontend/components/notifications/novu-inbox.tsx` — Novu Inbox wrapper
- `frontend/components/notifications/notification-bell.tsx` — conditional Novu vs local
- `frontend/next.config.js` — Turbopack resolve alias for `@novu/react`
- `frontend/scripts/patch-novu-exports.js` — flattens `@novu/react` exports for Turbopack

## API Documentation

- [Novu API Reference](https://docs.novu.co/api-reference/overview)
- [Novu React SDK](https://docs.novu.co/sdks/react)

### Recipe

- [Configure Novu](../ai/recipes/configure-novu.md)

## Implementation Journal

- [Novu Notification Refactor (2026-02-07)](../journal/2026-02-07-novu-notification-refactor.md)
- [Novu Turbopack Compat (2026-02-07)](../journal/2026-02-07-novu-turbopack-compat.md)
