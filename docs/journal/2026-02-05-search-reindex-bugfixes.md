# Search Reindex Bugfixes - 2026-02-05

## Overview

Fixed two bugs in the `search:reindex` artisan command that caused failures during container startup reindexing:

1. **notification_templates reindex failed** with "Model [App\Models\App\Console\Commands\NotificationTemplate] not found" due to a missing import.
2. **Pages sync failed** with "The provided API key is invalid" — improved error handling and diagnostics.

## Root Cause

### Bug 1: Missing NotificationTemplate Import

`SearchReindexCommand.php` referenced `NotificationTemplate::class` in the `$searchableModels` array but did not have a `use App\Models\NotificationTemplate;` import. PHP resolved the unqualified class name relative to the current namespace (`App\Console\Commands`), producing `App\Console\Commands\NotificationTemplate`. When `scout:import` received this class name, it failed to find the model.

### Bug 2: Pages Sync API Key Error

The pages sync calls `app(\Meilisearch\Client::class)` directly (same singleton used by Scout), so the API key should be identical. The most likely cause is an invalid API key stored in the database settings (either a wrong value saved by the admin, or an encrypted value that can no longer be decrypted due to APP_KEY rotation). The previous error handling masked the root cause by printing only the Meilisearch error without any diagnostic context. Additionally, pages sync failure during a full reindex silently returned FAILURE which the `handle()` method ignored, producing confusing output.

## Implementation Approach

- **`SearchReindexCommand.php`**: Added `use App\Models\NotificationTemplate;` import. Changed `reindexPages()` to accept a `$fatal` parameter: when called during full reindex (`fatal: false`), failures produce a warning and the command continues; when called as the explicit target (`fatal: true`), failures return FAILURE. Added an actionable hint for API key errors pointing to Configuration > Search or the MEILI_MASTER_KEY env var.
- **`SearchService::syncPagesToIndex()`**: Added structured error logging with diagnostic context (host, whether key is set, key source hint for API key errors) to aid troubleshooting.

## Files Changed

- `backend/app/Console/Commands/SearchReindexCommand.php` — added missing import, improved error handling
- `backend/app/Services/Search/SearchService.php` — added diagnostic logging in `syncPagesToIndex()`

## Testing Notes

- Rebuild container and verify `search:reindex` output no longer shows `NotificationTemplate` error
- Verify pages sync API key errors now include the hint about Configuration > Search
- Verify that pages sync failure during full reindex does not prevent model reindexing
