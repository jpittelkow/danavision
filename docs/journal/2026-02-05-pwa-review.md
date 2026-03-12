# PWA Review and Code Audit - 2026-02-05

## Overview

Completed a comprehensive review of the PWA implementation per the PWA Review and Code Audit plan. Addressed missing assets, code quality issues, and security hardening. Documentation updated to reflect the review.

## Implementation Approach

- **Code review:** Audited all PWA-related files (sw.js, manifest, service-worker.ts, use-install-prompt.ts, request-queue.ts, offline-utils.ts, install-prompt.tsx, offline-indicator.tsx, service-worker-setup.tsx, share/page.tsx) against the [code-review recipe](ai/recipes/code-review.md).
- **Functional testing:** Verified app loads, service worker registration, and offline behavior via browser. Lighthouse PWA audit could not be run in the automation environment (requires Chrome); documented manual command for future runs.
- **Fixes applied:** See Fixes section below.

## Fixes Applied

1. **Missing screenshots:** Manifest (API route and static) referenced `/screenshots/dashboard.png` and `/screenshots/mobile.png` which do not exist. Removed the `screenshots` array from both [frontend/app/api/manifest/route.ts](../../frontend/app/api/manifest/route.ts) and [frontend/public/manifest.json](../../frontend/public/manifest.json) to avoid 404s during install. Screenshots are optional; can be re-added when assets exist.

2. **Logging compliance:** Replaced `console.error` in [frontend/lib/service-worker.ts](../../frontend/lib/service-worker.ts) with `errorLogger.report()` per [logging-compliance](../../.cursor/rules/logging-compliance.mdc). Uses dynamic import to avoid circular dependency.

3. **Share page XSS hardening:** [frontend/app/share/page.tsx](../../frontend/app/share/page.tsx) receives `url` from query params (Share Target API). Added validation so only `http://` and `https://` URLs are rendered as clickable links. Non-http(s) URLs (e.g. `javascript:`, `data:`) are displayed as plain text to prevent XSS.

## Challenges Encountered

- **Lighthouse:** `npx lighthouse` requires Chrome to be installed. The automation environment did not have Chrome in the expected path. Documented the manual command in the PWA roadmap testing checklist.
- **Manifest sources:** The app uses a dynamic manifest at `/api/manifest` (layout.tsx points there). Both the API route and the static fallback manifest had to be updated to remove screenshots.

## Observations

- PWA implementation is feature-complete with service worker, install prompt, offline support, push notifications, and share target.
- Install prompt logic (visit count, 30-day dismissal) and offline indicator are well-implemented.
- Share target page correctly handles title, text, and url params; URL validation improves security for user-shared content.

## Trade-offs

- **Screenshots removed:** Install dialogs on Android may show fewer preview images. Re-adding requires generating dashboard.png and mobile.png in `frontend/public/screenshots/`.
- **Dynamic import for errorLogger:** Used to avoid loading error-logger at registration time and potential circular deps; adds slight async overhead only on registration failure.

## Testing Notes

Manual verification steps documented in [pwa-roadmap.md](../plans/pwa-roadmap.md):

- Lighthouse: `npx lighthouse http://localhost:8080 --only-categories=pwa`
- Install prompt: clear `pwa-visit-count` and `pwa-install-dismissed-at` from localStorage, visit twice
- Offline: DevTools > Network > Offline
- SW update: change CACHE_VERSION in sw.js, verify "New version available" toast and refresh

## Documentation Updates

- **docs/plans/pwa-roadmap.md:** Added PWA Review completion note, Lighthouse manual command, link to this journal.
- **docs/roadmaps.md:** Moved PWA Review from Next Up to Completed (2026-02-05), added journal entry.
