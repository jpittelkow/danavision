# ADR-019: Progressive Web App (PWA) Service Worker

## Status

Accepted

## Date

2026-01-30

## Context

Sourdough should offer a native app-like experience: installable, faster loads via caching, offline support, and later push notifications. We need a service worker implementation that works with Next.js App Router and provides a solid foundation for future PWA phases.

## Decision

We will implement a **manual service worker** using **Workbox runtime** (bundled locally in `frontend/public/workbox/` and loaded via `importScripts`), rather than using `next-pwa` or build-time precaching.

### Rationale for Manual SW + Workbox over next-pwa

- **Control**: Full control over caching behavior and update flow; no opaque build-time plugin behavior.
- **Debugging**: Easier to inspect and modify `sw.js` directly.
- **Simplicity**: No webpack/Next.js plugin integration; the service worker is a static file in `public/`.
- **Workbox runtime**: Bundled locally (Workbox 7.3.0 in `frontend/public/workbox/`); no npm dependency or CDN fetch for the SW itself. Workbox provides industry-standard caching strategies.

### Caching Strategies

| Request Type | Strategy | Cache Name | Rationale |
|--------------|----------|------------|-----------|
| `/_next/static/*`, scripts, styles, images, fonts | Cache-first | `sourdough-v1-static` | Versioned assets; cache forever with 30-day expiry. Fast loads. |
| `/api/*` (GET) | Network-first | `sourdough-v1-api` | Data freshness priority; cache fallback for offline. 5-min expiry. |
| Navigation | Network-first | `sourdough-v1-pages` | Fresh HTML when online; fallback to offline.html when offline. |

### Update Handling

- New SW installs and goes to "waiting" state.
- App shows toast: "New version available" with "Refresh" action.
- User clicks Refresh → app posts `SKIP_WAITING` to waiting SW → SW activates → `controllerchange` fires → page reloads to load fresh assets.

### Offline Fallback

- Precache `offline.html` on install.
- Navigation requests that fail (offline) are caught and served from `offline.html`.

### Browser Support

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Service Worker | Yes | Yes | Yes | Yes |
| Cache API | Yes | Yes | Yes | Yes |

## Consequences

- Faster repeat loads via static asset caching.
- Basic offline experience (offline page, cached API when available).
- Foundation for Phase 2 (push notifications) and Phase 3 (offline experience).
- Workbox CDN request on first SW install; SW then caches Workbox for offline use.
- No precaching of app shell; using runtime caching only to avoid build complexity.

## Key Files

- `frontend/public/sw.js` - Service worker
- `frontend/public/offline.html` - Offline fallback page
- `frontend/lib/service-worker.ts` - Registration utility
- `frontend/components/service-worker-setup.tsx` - Client registration component
- `scripts/generate-pwa-icons.mjs` - Icon generation script

## Implementation Journal

- [PWA Push Notifications (2026-01-31)](../journal/2026-01-31-pwa-push-notifications.md)
- [PWA Phase 3 Offline Experience (2026-01-31)](../journal/2026-01-31-pwa-phase-3-offline-experience.md)
- [PWA Phase 4-5 Install and Advanced (2026-01-31)](../journal/2026-01-31-pwa-phase-4-5-install-and-advanced.md)
- [PWA Review (2026-02-05)](../journal/2026-02-05-pwa-review.md)
- [PWA Hardening (2026-02-05)](../journal/2026-02-05-pwa-hardening.md)
