# Notification Config Split (Global vs Per-User) - 2026-01-28

## Overview

Implemented the split between global (admin) and per-user notification configuration. Admins enable which channels are available and set the preferred SMS provider; users configure their own webhooks, phone numbers, and accept usage in Preferences.

## Implementation Approach

- **SystemSetting** stores `channel_{id}_available` (admin toggles) and `sms_provider` (Twilio/Vonage/SNS) under group `notifications`.
- **NotificationOrchestrator** checks `isChannelAvailableToUsers()` after `isChannelEnabled` and before user preferences; database and email are always available.
- **Admin API** (`GET/PUT /api/admin/notification-channels`): channel availability toggles and SMS preferred provider. Configuration > Notifications uses this; user-specific config (webhooks, etc.) removed from that page.
- **User API** (`GET/PUT /api/user/notification-settings`): returns only admin-enabled channels (and preferred SMS when applicable). Rejects enabling a channel not made available. Supports `usage_accepted` and per-channel settings.
- **User Preferences** notification card: fetches user API, shows enable toggle, settings form (webhook/phone), Save, Test, and "I accept usage" checkbox. Enable disabled until configured and, when applicable, usage accepted.
- **SMS channels** (Twilio, Vonage, SNS): `phone_number` added to required user settings; channels read `{channel}_phone_number` from user settings (with legacy `phone_number` fallback).

## Key Files

- `backend/app/Services/Notifications/NotificationOrchestrator.php` – `isChannelAvailableToUsers`
- `backend/app/Services/Notifications/NotificationChannelMetadata.php` – shared trait for channel name/description/always-available check
- `backend/app/Http/Controllers/Api/NotificationChannelConfigController.php` – admin API
- `backend/app/Http/Controllers/Api/UserNotificationSettingsController.php` – user API
- `frontend/app/(dashboard)/configuration/notifications/page.tsx` – admin UI (toggles, SMS provider)
- `frontend/app/(dashboard)/user/preferences/page.tsx` – user UI (settings, test, accept, enable)
- `backend/app/Services/Notifications/Channels/TwilioChannel.php` (and Vonage, SNS) – channel-specific phone number

## Refactoring (2026-01-28)

- **Bug fix:** Email and database channels were not showing for users when `config('notifications.channels.{id}.enabled')` was false. Fixed `getAvailableChannelIds()` to bypass the `providerConfigured` check for always-available channels.
- **Code duplication:** Extracted `getChannelName()`, `getChannelDescription()`, and `isAlwaysAvailableChannel()` into `NotificationChannelMetadata` trait, used by both controllers and the orchestrator.
- **Bug fix:** Slack and Discord toggles were disabled in admin Configuration page because they checked for global webhooks, but users provide their own webhooks. Added `isUserConfigurableChannel()` to trait; user-configurable channels now show `provider_configured: true` even without global credentials.
- **Bug fix:** SlackChannel and DiscordChannel were using wrong format for user settings (`getSetting('key')` instead of `getSetting('notifications', 'key')`). Fixed to use correct group-based retrieval.
- **UX simplification:** Removed "I accept usage" checkbox from User Preferences - unnecessary friction. Users can now enable channels as soon as they've configured their settings.

## Documentation Updates (2026-01-28)

- **Recipe updated:** `docs/ai/recipes/add-notification-channel.md` - Complete rewrite to reflect current architecture (two-layer config, `ChannelInterface`, metadata trait)
- **Patterns updated:** `docs/ai/patterns.md` - Fixed Channel/Provider pattern to use correct interface
- **Context loading updated:** `docs/ai/context-loading.md` - Added new files and recipe links
- **Architecture updated:** `docs/architecture.md` - Added new key files for ADR-005
- **Trigger notifications recipe:** Added references to new files

## Testing Notes

- NotificationOrchestratorTest passes. Feature notification tests have pre-existing failures (API shape) unrelated to this work.
- Verify: admin enables a channel in Configuration, user sees it in Preferences, configures and enables; orchestration skips channels not available or not user-enabled.
