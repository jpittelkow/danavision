# HIPAA Access Logging Toggle – 2026-01-29

## Overview

Added a toggle to enable/disable HIPAA access logging. When disabled, no new access logs are created and "Delete all access logs" becomes available. Deleting all shows a confirmation dialog that it violates HIPAA 6-year retention.

## Implementation Approach

- **Setting**: `hipaa_access_logging_enabled` in `logging` group (settings-schema, env `HIPAA_ACCESS_LOGGING_ENABLED`, default true). Injected into `config('logging.hipaa_access_logging_enabled')` via ConfigServiceProvider.
- **AccessLogService**: Skips creating logs when `config('logging.hipaa_access_logging_enabled')` is false.
- **Log retention API**: GET returns `hipaa_access_logging_enabled`; PUT accepts it, persists, and audits.
- **Delete all**: `DELETE /api/access-logs` allowed only when HIPAA logging disabled. Returns 422 with message otherwise. On success, truncates `access_logs`, audits `access_logs.delete_all` (severity warning) with `deleted_count`.
- **UI**: Configuration > Log retention – "HIPAA access logging" card with toggle and, when disabled, "Delete all access logs" button. Confirmation dialog warns about HIPAA 6-year retention violation before proceeding.

## Challenges Encountered

- AccessLogFieldTrackingTest started failing because HIPAA logging was effectively off in test env. Added `beforeEach` with `Config::set('logging.hipaa_access_logging_enabled', true)` so those tests explicitly enable logging.

## Observations

- Config bootstrap uses DB settings; toggle change takes effect on next request after save.
- "Save the setting above before deleting" note added so users persist the toggle before delete-all.

## Trade-offs

- Delete-all is allowed only when disabled; retention min 6 years unchanged. No reduction of retention below 6 years when enabled.

## Next Steps (Future Considerations)

- None.

## Testing Notes

- `HipaaLoggingToggleTest`: log retention GET/PUT, delete-all 422 when enabled, delete-all 200 when disabled, no access logs when disabled.
- `AccessLogFieldTrackingTest`: still passes with HIPAA explicitly enabled in `beforeEach`.
