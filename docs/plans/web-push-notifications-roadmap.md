# Web Push Notifications Roadmap

Complete the Web Push notification channel implementation with service worker and frontend subscription flow.

## Overview

Web Push notifications allow users to receive browser notifications even when the app isn't open. The backend channel exists but frontend infrastructure is missing.

## Current State

- [x] Backend `WebPushChannel` with VAPID signing and encryption
- [x] Config structure for VAPID env vars
- [ ] Service worker for receiving/displaying notifications
- [ ] Frontend subscription flow
- [ ] User preferences UI for enabling browser notifications

## Phase 1: Service Worker

Create the service worker to handle incoming push notifications.

- [ ] Create `frontend/public/sw.js` service worker
- [ ] Handle `push` event to display notification
- [ ] Handle `notificationclick` event for navigation
- [ ] Handle `notificationclose` event for analytics (optional)

## Phase 2: Frontend Subscription Flow

Add UI and logic for users to subscribe to push notifications.

- [ ] Create `frontend/lib/web-push.ts` utility
  - [ ] Check browser support (`'serviceWorker' in navigator && 'PushManager' in window`)
  - [ ] Register service worker
  - [ ] Request notification permission
  - [ ] Subscribe to push with VAPID public key
  - [ ] Save subscription to user settings via API
- [ ] Add "Enable Browser Notifications" button to User Preferences
- [ ] Show permission/subscription status
- [ ] Handle unsubscribe flow

## Phase 3: Backend Fixes

Fix issues in the existing channel implementation.

- [ ] Fix `WebPushChannel` to use correct settings group: `$user->getSetting('notifications', 'webpush_subscription')`
- [ ] Add endpoint to receive subscription from frontend: `POST /api/user/webpush-subscription`
- [ ] Handle subscription refresh/update
- [ ] Clean up expired subscriptions

## Phase 4: Admin Configuration

- [ ] Add Web Push to admin Configuration page
- [ ] Show VAPID public key (for debugging)
- [ ] Add instructions for generating VAPID keys

## Environment Variables

```env
# Generate with: npx web-push generate-vapid-keys
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:admin@yourdomain.com
```

## Key Files

| File | Purpose |
|------|---------|
| `frontend/public/sw.js` | Service worker (new) |
| `frontend/lib/web-push.ts` | Web push utilities (new) |
| `frontend/app/(dashboard)/user/preferences/page.tsx` | Add enable button |
| `backend/app/Services/Notifications/Channels/WebPushChannel.php` | Fix settings group |
| `backend/app/Http/Controllers/Api/UserNotificationSettingsController.php` | Add subscription endpoint |

## Dependencies

- None (notifications system already implemented)

## Reference

- [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
- [Web Push Protocol](https://datatracker.ietf.org/doc/html/rfc8030)
- [VAPID](https://datatracker.ietf.org/doc/html/rfc8292)
