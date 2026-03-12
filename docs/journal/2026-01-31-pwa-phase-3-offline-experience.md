# PWA Phase 3: Offline Experience - 2026-01-31

## Overview

Implemented Phase 3 of the PWA roadmap: offline experience with network detection, offline indicator, user data caching, failed request queue with background sync (and online fallback), and graceful degradation on dashboard, preferences, and notifications pages.

## Implementation Approach

- **useOnline hook** (`frontend/lib/use-online.ts`): Tracks `navigator.onLine` and `online`/`offline` events for reactive UI.
- **OfflineIndicator**: Fixed banner at top when offline; added to AppShell with top padding when offline.
- **Service worker**: Extended `CACHEABLE_API_PATHS` to include `/api/auth/user`, `/api/dashboard/stats`, `/api/notifications` for network-first with cache fallback. Added Background Sync handler to retry queued requests (Chrome/Edge); page `online` listener used as fallback elsewhere.
- **Request queue** (`frontend/lib/request-queue.ts`): IndexedDB store for failed POST/PUT/PATCH/DELETE; `addToRequestQueue`, `processRequestQueue`, `registerBackgroundSync`, `setupOfflineRetry`.
- **API client**: Response interceptor queues mutations on network error and calls `registerBackgroundSync`; `setupOfflineRetry(api)` runs on load to retry queue when back online.
- **OfflineBadge**: Small "Offline" or "Cached" badge for pages showing cached data.
- **Offline utilities** (`frontend/lib/offline-utils.ts`): `isOfflineError`, `isOffline`, `withOfflineFallback` for graceful degradation.
- **Pages**: Dashboard and Notifications show OfflineBadge when offline; User Preferences and Notifications disable save/actions when offline and show read-only messaging.
- **offline.html**: "Go to Dashboard" link, automatic reload when back online, improved styling.

## Challenges Encountered

- **Background Sync**: Only supported in Chrome/Edge; fallback to page `online` listener ensures queue is processed in Firefox/Safari.
- **API URL in queue**: Stored full path (e.g. `/api/user/settings`) or absolute URL when `NEXT_PUBLIC_API_URL` is set so the service worker can replay with correct origin.

## Observations

- Navigation requests are already cached network-first by the existing SW; adding user-data API paths gives offline read access to auth user, dashboard stats, and notifications after first fetch.
- RadioGroup/Select `disabled` prop used on preferences for read-only mode when offline.

## Trade-offs

- Background Sync not used in Firefox/Safari; online event in the page is the only retry trigger there.
- User data cache is session-scoped in practice (short TTL); no explicit "clear on logout" in SW (cache expires by age).

## Next Steps (Future Considerations)

- Phase 4: Install experience (install prompt, manifest enhancements).
- Optional: Clear user-data cache on logout via message to SW.

## Testing Notes

- Verify offline indicator appears when DevTools Network is set to Offline.
- Visit dashboard/preferences/notifications while online, then go offline: cached data and offline badge/read-only behavior.
- Queue a mutation (e.g. change preference) while offline; go online and confirm retry (or Background Sync).
- Confirm offline.html shows "Go to Dashboard" and auto-reloads when back online.
