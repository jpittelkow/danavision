# Notification & LLM Settings Migration (Env to Database Phase 3–4) - 2026-01-28

## Overview

Migrated notification channel credentials (Phase 3) and LLM system-wide settings (Phase 4) from environment variables to database storage using the established SettingService pattern. Admins can configure notification channels and LLM defaults via the UI; values fall back to `.env` when not set in the database.

## Implementation Approach

### Phase 3: Notification channel configuration

- **Settings schema** (`backend/config/settings-schema.php`): Added `notifications` group with ~30 keys for Telegram, Discord, Slack, Signal, Twilio, Vonage, SNS, Web Push (VAPID), FCM, ntfy, and Matrix. Secrets marked with `encrypted => true`.
- **NotificationSettingController**: `show()`, `update()`, `reset($key)`, `testChannel($channel)`. Uses SettingService and ApiResponseTrait. Test applies current settings to config for the request then calls NotificationOrchestrator::sendTestNotification.
- **ConfigServiceProvider**: Added `injectNotificationsConfig()` to map DB settings to `config('notifications.channels.*')`. Boot-time injection when `notifications` group is present.
- **Routes**: `GET/PUT /notification-settings`, `POST /notification-settings/test/{channel}`, `DELETE /notification-settings/keys/{key}` under `can:manage-settings`.
- **Frontend**: Extended Configuration > Notifications page with a “Channel credentials” form. Fetches both `/admin/notification-channels` and `/notification-settings`. Uses react-hook-form with `mode: "onBlur"`, `reset()` for initial values, SaveButton, SettingsPageSkeleton. Per-channel cards with optional Test button. Empty strings sent as null.

### Phase 4: LLM system-wide settings

- **Settings schema**: Added `llm` group with `mode`, `primary`, `timeout`, `logging_enabled`, `council_min_providers`, `council_strategy`, `aggregation_parallel`, `aggregation_include_sources`.
- **LLMSettingController**: `show()`, `update()`, `reset($key)`. Validation for mode (single/aggregation/council), primary provider, timeout (10–600), booleans, council strategy (majority/weighted/synthesize).
- **ConfigServiceProvider**: Added `injectLLMConfig()` to map DB settings to `config('llm.*')`, `config('llm.council.*')`, `config('llm.aggregation.*')`.
- **Routes**: `GET/PUT /llm-settings`, `DELETE /llm-settings/keys/{key}` under `can:manage-settings`.
- **Frontend**: New page Configuration > LLM system (`/configuration/llm-system`). Form for mode, primary provider, timeout, logging toggle; council section (min providers, strategy) when mode is council; aggregation section (parallel, include sources) when mode is aggregation. Uses SaveButton, SettingsPageSkeleton, FormField, Select, Switch. Added nav item and page title.

## Key Files

- `backend/config/settings-schema.php` (notifications + llm groups)
- `backend/app/Http/Controllers/Api/NotificationSettingController.php`
- `backend/app/Http/Controllers/Api/LLMSettingController.php`
- `backend/app/Providers/ConfigServiceProvider.php` (injectNotificationsConfig, injectLLMConfig)
- `backend/routes/api.php` (notification-settings, llm-settings)
- `frontend/app/(dashboard)/configuration/notifications/page.tsx` (credentials form)
- `frontend/app/(dashboard)/configuration/llm-system/page.tsx`
- `frontend/app/(dashboard)/configuration/layout.tsx` (LLM system nav item)
- `frontend/components/page-title-manager.tsx` (llm-system title)

## Challenges Encountered

- **Notification test**: Test channel must apply current DB-backed settings to config for the request before calling the orchestrator, so the test uses the latest credentials (including unsaved form state is not supported; user must save first).
- **SNS**: SNS channel uses shared AWS credentials from mail config; only `sns_enabled` is stored in notifications group. Config injection checks `config('mail.mailers.ses.key')` for SNS enabled.
- **Boolean storage**: SystemSetting value is stored via JSON; booleans (ntfy_enabled, sns_enabled, logging_enabled, etc.) are encoded/decoded correctly by the model’s value Attribute.

## Observations

- Following the same pattern as mail (schema, controller, ConfigServiceProvider, routes, frontend with reset/SaveButton/SettingsPageSkeleton) kept the implementation consistent and predictable.
- Notification credentials form is long (one card per channel); optional Test button per channel improves UX without cluttering the main flow.

## Trade-offs

- Notification credentials are edited in one large form; future improvement could be per-channel expand/collapse or a separate “Edit credentials” modal per channel.
- LLM system page is separate from per-user AI Settings; this keeps “system defaults” vs “user overrides” clear.

## Next Steps (Future Considerations)

- Migrate SSO and backup settings (Phases 5–6).
- Document which settings remain env-only in a dedicated doc section.
- Consider per-setting “reset to default” in the notification credentials UI using `DELETE /notification-settings/keys/{key}`.

## Testing Notes

- Run `php artisan settings:import-env --group=notifications` and `--group=llm` to seed DB from env.
- Verify Configuration > Notifications: load credentials, save, test a channel (e.g. Telegram if token set).
- Verify Configuration > LLM system: change mode/primary/timeout, save; confirm council/aggregation sections show when relevant.
- Confirm config injection: e.g. `config('notifications.channels.telegram.bot_token')` and `config('llm.mode')` reflect DB after save and next request.

---

**Update (2026-01-29):** The LLM system UI (`/configuration/llm-system`) was consolidated into Configuration > AI / LLM (`/configuration/ai`) as an admin-only "System Defaults" card. The separate LLM system page and nav entry were removed. See [2026-01-29-llm-settings-consolidation.md](2026-01-29-llm-settings-consolidation.md).
