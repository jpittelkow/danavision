# Meilisearch Configuration Roadmap

Admin-configurable Meilisearch settings with external instance support and search toggle.

## Overview

Refactor Meilisearch integration to allow administrators to configure the Meilisearch instance URL and keys through the admin UI rather than environment variables only. Additionally, provide a toggle to disable search entirely, which removes the search bar from the header.

**Why this enhancement:**
- Allows using external/hosted Meilisearch instances (Meilisearch Cloud, dedicated server)
- Enables switching between embedded and external search without container rebuild
- Provides option to disable search for deployments that don't need it
- Reduces attack surface when search is not required

## Prerequisites

- Meilisearch Integration complete (2026-01-30)
- Settings system in place (SettingService)

## Phase 1: Database Settings

Add Meilisearch configuration to the settings system.

- [x] Add search settings to `config/settings-schema.php`:
  - `search.enabled` (boolean, default: true)
  - `search.host` (string, default: `http://127.0.0.1:7700`)
  - `search.api_key` (string, encrypted, default: from `MEILI_MASTER_KEY` env)
  - `search.use_embedded` (boolean, default: true) - hint for UI
- [x] ConfigServiceProvider injects settings into config and scout.meilisearch
- [x] Add `isEnabled()`, `testConnection()`, `getHealth()` to SearchService
- [x] Add validation for Meilisearch URL format (testConnection)

**Key files:**
- `backend/config/settings-schema.php`
- `backend/app/Services/Search/SearchService.php`

## Phase 2: Admin Configuration UI

Update Configuration > Search page with instance settings.

- [x] Add "Instance" tab to search configuration page
- [x] Add toggle: "Enable Search" (when disabled, hides search UI globally)
- [x] Add radio: "Use embedded instance" vs "Use external instance"
- [x] When external selected, show:
  - Meilisearch URL input
  - API Key input (masked/password field)
  - "Test Connection" button
- [x] Show connection status indicator (connected/error)
- [x] Add health check endpoint (GET /admin/search/health)

**Key files:**
- `frontend/app/(dashboard)/configuration/search/page.tsx`
- `backend/app/Http/Controllers/Api/Admin/SearchAdminController.php`

## Phase 3: Search Toggle Integration

Hide search UI when disabled.

- [x] Add `search.enabled` to public settings features response
- [x] Update `AppConfigProvider` to include searchEnabled in features
- [x] Conditionally render search button and SearchInline in Header based on setting
- [x] Conditionally register Cmd+K shortcut based on setting (SearchProvider)
- [x] Update SearchProvider to check enabled state before rendering modal

**Key files:**
- `frontend/lib/app-config.tsx`
- `frontend/components/header.tsx`
- `frontend/components/search/search-provider.tsx`

## Phase 4: Connection Management

Handle connection switching and validation.

- [x] Add POST /admin/search/test-connection endpoint
- [x] Show warning when switching from embedded to external (data won't migrate)
- [x] Add "Reindex All" prompt when saving external config
- [x] SearchService has graceful degradation (existing try/catch, getHealth)

**Key files:**
- `backend/app/Http/Controllers/Api/Admin/SearchAdminController.php`
- `backend/app/Services/Search/SearchService.php`

## Success Criteria

- [x] Admin can enable/disable search from Configuration > Search
- [x] When search is disabled, search bar and Cmd+K shortcut are hidden
- [x] Admin can configure external Meilisearch instance (URL + API key)
- [x] "Test Connection" validates external instance before saving
- [x] Embedded instance remains the default for new installations
- [x] Settings stored in database, not requiring env changes

## Future Enhancements

- Automatic failover from external to embedded instance
- Multiple index configuration (different instances per model type)
- Search analytics dashboard integration
- Meilisearch Cloud wizard/integration

## Implementation Journal

- [Meilisearch Configuration](../journal/2026-01-30-meilisearch-configuration.md)
- [Meilisearch Embedded in Container](../journal/2026-01-30-meilisearch-embedded.md)
- [Alpine to Debian Migration for Meilisearch](../journal/2026-01-30-alpine-to-debian-meilisearch.md)
- [Meilisearch Production Permission Denied Fix](../journal/2026-02-05-meilisearch-production-permissions.md)
- [Search Reindex Bugfixes](../journal/2026-02-05-search-reindex-bugfixes.md)
