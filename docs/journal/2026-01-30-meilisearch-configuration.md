# Meilisearch Configuration - 2026-01-30

## Overview

Implemented admin-configurable Meilisearch settings per the Meilisearch Configuration roadmap: search enable/disable toggle, external instance support (URL + API key), and connection testing. Admins can now manage search entirely from the UI without rebuilding the container.

## Implementation Approach

- **Settings schema**: Added `search.enabled`, `search.use_embedded`, `search.host`, `search.api_key` to settings-schema.php. ConfigServiceProvider injects these into Laravel config and `scout.meilisearch` so Scout uses database-backed values.
- **SearchService**: Added `isEnabled()`, `testConnection()`, `getHealth()` methods. Connection testing creates a temporary Meilisearch client with provided credentials.
- **Admin API**: New endpoints `GET /admin/search/health` and `POST /admin/search/test-connection` for status and validation.
- **Public features**: `search.enabled` exposed in `/system-settings/public` features so the frontend can conditionally show/hide search UI.
- **Frontend**: Instance tab on Configuration > Search with Enable toggle, embedded/external radio, URL and API key inputs, Test Connection button, and connection status badge.
- **SearchProvider/Header**: Conditionally render SearchModal, Cmd+K handler, and search UI based on `searchEnabled` from app config.

## Challenges Encountered

- **SettingService vs SystemSetting**: The controller previously used `SystemSetting::set` which did not encrypt values. Switched to `SettingService::set` so `search.api_key` is stored encrypted per schema.
- **Config cache invalidation**: After saving instance settings, app config needed refresh. Used `queryClient.invalidateQueries({ queryKey: ["app-config"] })` to trigger refetch.

## Observations

- Kept Configuration > Search link visible when search is disabled so admins can re-enable it (hiding it would lock them out).
- Default tab set to "Instance" since it's the primary new functionality.

## Trade-offs

- No migration to seed defaults: SettingService resolveGroup returns env/schema when no DB record exists, so new installs work. A migration could pre-populate for consistency.
- Search link remains in nav when disabled for usability.

## Next Steps (Future Considerations)

- Migration to seed search.* defaults from env on upgrade
- Health check integration in system status/monitoring dashboard

## Testing Notes

- Verify search toggle hides/shows search UI after save and config refetch
- Test external connection with valid and invalid credentials
- Confirm reindex works after switching instances
