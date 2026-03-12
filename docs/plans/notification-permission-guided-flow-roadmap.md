# Notification Permission Guided Flow Roadmap

Walk users through enabling browser notifications with an inline permission request — both during onboarding and in their notification preferences.

**Priority**: HIGH
**Status**: COMPLETED (all Phase 1-3 items complete)
**Last Updated**: 2026-03-10

---

## Problem

The current onboarding wizard's Notifications step is informational only: it lists available channels and links to `/user/notifications`. Users who skip this step or don't follow the link never get prompted to enable push notifications. The result is low opt-in rates for browser push — the most immediate notification channel.

## Goals

1. **Onboarding wizard**: Request notification permission inline during the wizard, so users can enable push without leaving the flow
2. **User notification preferences**: Add a guided first-time experience that explains the value of notifications before requesting permission
3. **Recover from "denied"**: Provide clear instructions when a user has previously denied permission at the browser level

## Phase 1: Enhanced Onboarding Wizard Step

Upgrade `NotificationsStep` from informational to actionable.

### Tasks

- [x] **Add inline permission request button** — "Enable Browser Notifications" button in the wizard step that calls `Notification.requestPermission()` via `subscribe()` from `web-push.ts`
- [x] **Show permission state feedback** — After the user responds to the browser prompt, show success (checkmark + "Notifications enabled!") or denial (explanation of how to re-enable via browser settings)
- [x] **Auto-subscribe on grant** — When permission is granted, automatically create the Web Push subscription and send it to the backend (reuses `subscribe()` from `web-push.ts`)
- [x] **Handle "not supported"** — If `isWebPushSupported()` returns false, show a graceful message: "Your browser doesn't support push notifications — you'll still receive in-app and email notifications"
- [x] **Handle "already granted"** — If user already has permission granted, show green confirmation state
- [x] **Send welcome notification** — On successful subscription, toast: "Notifications enabled! You'll receive alerts here."
- [x] **Conditional on admin config** — Only shows the push permission flow if `features.webpushEnabled` and `webpushVapidPublicKey` are set
- [x] **Track step completion** — Wizard modal's `handleNext` marks step complete whether user enables, denies, or skips (unchanged — the wizard already handles this)

### Files Modified

| File | Change |
|------|--------|
| `frontend/components/onboarding/steps/notifications-step.tsx` | Full rewrite: added permission state machine (loading/unsupported/prompt/granted/denied), inline subscribe button, state-specific UI components |

## Phase 2: Guided Flow in User Notification Preferences

Added a first-time guided experience on the user notifications/preferences page.

### Tasks

- [x] **Add notification permission banner** — Prominent banner at the top of the notifications preferences tab when `Notification.permission === 'default'`. Explains value and has "Enable Notifications" button
- [x] **Permission status indicator** — Shows current permission state with contextual UI: prompt (blue), denied (amber with unblock instructions), granted (green confirmation)
- [x] **Browser-specific unblock instructions** — When permission is `denied`, shows step-by-step instructions for Chrome, Firefox, Safari, and generic browsers
- [x] **Dismissible banner** — "Not now" button and X icon dismiss the prompt; 30-day re-prompt cooldown via `localStorage`
- [x] **Auto-subscribe on enable** — Clicking "Enable Notifications" subscribes, registers with backend, and enables the webpush channel in one click

### Files Modified

| File | Change |
|------|--------|
| `frontend/components/notifications/permission-banner.tsx` | New: reusable permission request banner with state machine, browser detection, unblock instructions |
| `frontend/app/(dashboard)/user/preferences/page.tsx` | Import and render `NotificationPermissionBanner` at top of notifications tab |

## Phase 3: Post-Login Contextual Prompt

For users who skipped onboarding or dismissed the preferences banner.

### Tasks

- [x] **Contextual prompt after relevant action** — `useNotificationPrompt()` hook shows a one-time toast with "Enable notifications" action after user performs notification-generating actions (e.g., starting a backup)
- [x] **Respect dismissal** — Only shows once per session; checks localStorage keys from preferences banner and PWA post-install prompt to avoid double-prompting
- [x] **Integrate with existing post-install prompt** — Reads `push-prompt-dismissed-at` and `notification-banner-dismissed-at` localStorage keys to coordinate; uses `sessionStorage` for per-session dedup
- [x] **Integrated into backup page** — `handleCreateBackup` calls `promptIfNeeded("Want to know when your backup completes?")`

### Files Modified

| File | Change |
|------|--------|
| `frontend/lib/use-notification-prompt.ts` | New: hook for contextual notification prompts with session dedup and cross-flow coordination |
| `frontend/app/(dashboard)/configuration/backup/page.tsx` | Added `useNotificationPrompt` integration after backup creation |

## Implementation Notes

- **Permission can only be requested from a user gesture** — The `Notification.requestPermission()` call must happen inside a click handler, not on page load. Both the wizard button and the banner button satisfy this requirement.
- **Permission is permanent per origin** — Once denied, only the user can re-enable via browser settings. We can't re-prompt. This is why clear "denied" state messaging matters.
- **iOS requires PWA install** — Push notifications on iOS Safari only work when the app is added to the Home Screen (iOS 16.4+). The existing `WebPushHelperText` in preferences handles iOS guidance.
- **Coordinate with existing `use-post-install-push-prompt.ts`** — The new `use-notification-prompt.ts` hook reads the same localStorage keys to avoid double-prompting PWA users.

## Key Files

| File | Purpose |
|------|---------|
| `frontend/components/onboarding/steps/notifications-step.tsx` | Onboarding wizard step with inline permission request |
| `frontend/components/notifications/permission-banner.tsx` | Preferences page permission banner |
| `frontend/lib/use-notification-prompt.ts` | Contextual post-action prompt hook |
| `frontend/lib/web-push.ts` | Core Web Push utilities (unchanged) |
| `frontend/lib/use-post-install-push-prompt.ts` | PWA post-install prompt (unchanged, coordinated via localStorage) |

## Success Metrics

- Increase in Web Push subscription rate among new users
- Reduction in users with `Notification.permission === 'default'` (never prompted)
- No increase in `Notification.permission === 'denied'` rate (users aren't feeling pressured)

## References

- [PWA Roadmap](pwa-roadmap.md) — Push notification implementation details
- [Web Push Notifications Roadmap](web-push-notifications-roadmap.md) — Original subscription flow
- [Notification System Review](notification-system-review.md) — Comprehensive audit
- [Extend Onboarding Recipe](../ai/recipes/extend-onboarding.md) — How to add wizard steps
- [ADR-005: Notification System](../adr/005-notification-system-architecture.md)
