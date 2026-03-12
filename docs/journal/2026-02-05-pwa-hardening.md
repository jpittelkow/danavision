# PWA Hardening - 2026-02-05

## Overview

Comprehensive hardening pass on the PWA implementation addressing 9 issues found during code review. The most critical fix resolves a broken server-side fetch in the dynamic manifest route that caused production to always serve the static fallback manifest, defeating branding customization. Other fixes improve iOS compatibility, Android icon rendering, offline resilience, and mobile UX.

## Implementation Approach

### Fix 1: Dynamic Manifest Route (HIGH)

The `/api/manifest` Next.js route handler called `fetch('/api/branding')` with a relative URL. Server-side `fetch()` in Node.js requires absolute URLs, so this always threw a `TypeError` in production, falling back to the static manifest.

**Solution:** Use Next.js `headers()` to reconstruct the request origin, with a cascading fallback: `NEXT_PUBLIC_API_URL` -> request host header -> `INTERNAL_API_URL` env var (defaults to `http://127.0.0.1:80` in the Docker container). The `INTERNAL_API_URL` is exported in `entrypoint.sh` and documented in `.env.example`.

### Fix 2: Missing Icons (MEDIUM)

`layout.tsx` referenced `/apple-icon.png` (180x180) and `/favicon.ico` but neither existed. Updated `scripts/generate-pwa-icons.mjs` to produce both files. The `pngToIco()` helper wraps a 32x32 PNG in a minimal ICO container (modern browsers accept PNG-in-ICO).

### Fix 3: Theme-Color Meta Tag (MEDIUM)

Added `export const viewport = { themeColor: '#3b82f6' }` to `layout.tsx` for the static default, and dynamic `<meta name="theme-color">` updates in `AppConfigProvider` when branding settings load.

### Fix 4: Icon Purpose Split (MEDIUM)

Split `"purpose": "any maskable"` into two separate icon entries (`"any"` and `"maskable"`) in the static manifest, dynamic manifest route, and fallback manifest. This allows browsers to choose the correct variant -- maskable clips the outer ~10% for adaptive icons on Android.

### Fix 5: Local Workbox (MEDIUM)

Downloaded Workbox 7.3.0 modules to `frontend/public/workbox/` and updated `sw.js` to use `importScripts('/workbox/workbox-sw.js')` with `modulePathPrefix: '/workbox/'`. This eliminates the Google CDN dependency, preventing first-install failures when offline and pinning to a known version.

### Fix 6: Stale Queue Cleanup (LOW-MEDIUM)

Added 24-hour expiry and 4xx error removal to both `processRequestQueue()` (TypeScript) and `processQueueInSW()` (service worker). Stale items with expired sessions/CSRF tokens are discarded. Non-retryable client errors (400-499) are removed instead of being left for infinite retry.

### Fix 7: Dynamic App Name on Share Page (LOW)

Replaced hardcoded "Sourdough" with dynamic app name from `useAppConfig()`.

### Fix 8: Notification Click Fallback (LOW)

Added `postMessage({ type: 'NAVIGATE', url })` fallback in the SW's `notificationclick` handler for browsers where `client.navigate()` is unavailable. The `ServiceWorkerSetup` component now listens for these messages and navigates via `window.location.href`.

### Fix 9: Safe-Area and Standalone CSS (LOW)

Added `@media (display-mode: standalone)` CSS for `overscroll-behavior-y: contain` (prevents pull-to-refresh bounce). Added `safe-area-top` and `safe-area-bottom` utility classes for iOS notch/home indicator support. Applied to `OfflineIndicator` and `InstallPrompt`.

## Trade-offs

| Decision | Choice | Alternative | Rationale |
|----------|--------|-------------|-----------|
| Workbox loading | Local bundle (7.3.0) | CDN, npm build plugin | Eliminates external dependency; manual update on Workbox releases |
| Manifest URL resolution | headers() + env fallback | Build-time bake | Works at runtime without rebuild; handles dynamic hosts |
| Icon purpose | Separate entries (same file) | Separate files (maskable with padding) | Correct semantics; separate files can be a future enhancement |
| Stale queue threshold | 24 hours | Configurable | Sensible default; sessions/CSRF rarely last longer |
| ICO generation | PNG-in-ICO via Node.js | External tool / npm package | Zero dependencies; modern browsers handle it |

## Files Changed

| File | Change |
|------|--------|
| `frontend/app/api/manifest/route.ts` | headers()-based URL resolution, icon purpose split |
| `frontend/app/layout.tsx` | Added `viewport` export with themeColor |
| `frontend/app/globals.css` | Standalone mode CSS, safe-area utilities |
| `frontend/app/share/page.tsx` | Dynamic app name |
| `frontend/lib/app-config.tsx` | Dynamic theme-color meta tag |
| `frontend/lib/request-queue.ts` | 24h expiry, 4xx removal |
| `frontend/public/sw.js` | Local Workbox, stale queue, NAVIGATE postMessage |
| `frontend/public/manifest.json` | Icon purpose split |
| `frontend/public/workbox/` | New: Workbox 7.3.0 local modules |
| `frontend/public/apple-icon.png` | New: 180x180 Apple touch icon |
| `frontend/public/favicon.ico` | New: 32x32 ICO favicon |
| `frontend/components/service-worker-setup.tsx` | NAVIGATE message listener |
| `frontend/components/install-prompt.tsx` | safe-area-bottom class |
| `frontend/components/offline-indicator.tsx` | safe-area-top class |
| `scripts/generate-pwa-icons.mjs` | Generates apple-icon.png + favicon.ico |
| `docker/entrypoint.sh` | Exports INTERNAL_API_URL |
| `.env.example` | Documents INTERNAL_API_URL |

## Testing Notes

- Verify manifest at `/api/manifest` returns dynamic branding (not fallback) in production Docker
- Verify `apple-icon.png` and `favicon.ico` are served correctly
- Verify theme-color meta tag updates when branding primary color changes
- Test notification click navigation on mobile Chrome and Firefox
- Test offline queue: queue a mutation, wait >24h (or adjust threshold for test), verify item is pruned
- Verify Workbox loads from `/workbox/` (check Network tab for no Google CDN requests)
- Test safe-area rendering on iPhone with notch
