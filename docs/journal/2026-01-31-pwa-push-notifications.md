# PWA Phase 2: Push Notifications - 2026-01-31

## Overview

Implemented the complete web push notification flow for the PWA: service worker push handling, frontend subscription management, backend endpoints, and user preferences UI. Users can now enable browser push notifications from User Preferences when an admin has configured VAPID keys.

## Implementation Approach

- **Service worker**: Added `push` and `notificationclick` event handlers to `frontend/public/sw.js`. Push handler parses JSON payload (title, body, icon, badge, data) and displays notifications; click handler focuses existing window or opens URL from `notification.data.url`.
- **Web push utility**: Created `frontend/lib/web-push.ts` with `isWebPushSupported()`, `getPermissionStatus()`, `requestPermission()`, `subscribe()`, `unsubscribe()`, and `getSubscription()`. Uses `urlBase64ToUint8Array` for VAPID key conversion.
- **Backend endpoints**: Added `POST /api/user/webpush-subscription` and `DELETE /api/user/webpush-subscription` to UserNotificationSettingsController. Subscription stored as JSON in user settings under notifications group.
- **Public settings**: Exposed `webpush_enabled` and `webpush_vapid_public_key` in `GET /api/system-settings/public` features so the frontend can subscribe without auth for the key.
- **Channel metadata**: Added webpush to `isUserConfigurableChannel()` in NotificationChannelMetadata trait. Webpush configured status based on subscription presence.
- **Preferences UI**: Web push channel shows "Enable Browser Notifications" button when not subscribed; Switch + Test when subscribed. Uses `useAppConfig()` for VAPID key.
- **Admin config**: Added VAPID key generation instruction (`npx web-push generate-vapid-keys`) to Configuration > Notifications Web Push card description.

## Challenges Encountered

- **Notification click navigation**: Used `client.navigate()` with fallback for older browsers; ensure full URL construction for relative paths.
- **User settings group**: WebPushChannel already uses `getSetting('webpush_subscription')` (legacy key lookup); subscription is stored with group `notifications` for consistency.

## Observations

- Web push appears in channel list only when admin enables it in Configuration > Notifications channel config.
- Permission denied state is handled with clear toast messaging.
- Service worker must be registered before subscribing; `registerServiceWorker()` ensures this.

## Trade-offs

- Disabling web push removes the subscription entirely; re-enabling requires a new subscription (browser permission may be cached).
- VAPID public key exposed in public settings; acceptable since it is intended to be public.

## Next Steps (Future Considerations)

- Phase 3: Offline experience (cached app shell, background sync)
- Rich notifications with actions, notification categories, quiet hours
- Badge count on app icon

## Testing Notes

- Verify enable flow: request permission, subscribe, POST to backend, channel shows as configured
- Test notification display with title, body, icon
- Test notification click navigates to URL
- Verify disable flow clears subscription and updates channel state
- Confirm permission denied shows appropriate message
