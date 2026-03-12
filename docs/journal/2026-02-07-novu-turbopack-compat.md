# Novu + Turbopack Compatibility Fix - 2026-02-07

## Overview

Fixed a Next.js 16 (Turbopack) module resolution issue with `@novu/react` that prevented the app from running in dev/SSR contexts when Novu mode is enabled.

## Problem

`@novu/react`’s `package.json` exports map nests `import`/`require` conditions inside a `browser` condition. Turbopack fails to resolve this structure and errors with a module resolution failure for `@novu/react`.

Additionally, `@novu/react` must be rendered client-side only (it depends on solid-js internals that are not compatible with SSR under Turbopack).

## Solution

- **Client-only load**: Load the Novu Inbox wrapper with `next/dynamic({ ssr: false })`.
- **Turbopack alias**: Add a Turbopack `resolveAlias` for `@novu/react` to point directly at the ESM entry.
- **Exports patch script**: Add `frontend/scripts/patch-novu-exports.js` and run it via `predev`/`prebuild` to flatten the problematic exports map in `node_modules/@novu/react/package.json` when present.

## Related UI Tweaks

- Standardized “Connected/Verified/Active” badges to use `Badge` variants (`success`).
- Minor sidebar + configuration nav active-state styling adjustments (`bg-muted` / `text-foreground`).

## Files Changed

- `frontend/components/notifications/notification-bell.tsx`
- `frontend/components/notifications/novu-inbox.tsx`
- `frontend/next.config.js`
- `frontend/scripts/patch-novu-exports.js`
- `frontend/package.json`
- Configuration/admin UI files updated for badge variants and small layout tweaks

## Notes

- The patch script is designed to be non-fatal: if `@novu/react` is not installed or the exports map shape differs, it exits without modifying anything.
- Follow-up hardening: added stricter shape checks in the patch script and added warning-level client error reporting if the subscriber hash fetch fails.

