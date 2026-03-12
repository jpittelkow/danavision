# Scheduled Tasks/Jobs UI - 2026-01-29

## Overview

Implemented manual trigger (Run Now) for scheduled tasks on the Jobs page, run history tracking, and enhanced task display (last run, triggerable status, dangerous-command warnings). Admins can run whitelisted commands (backup:run, log:cleanup, log:check-suspicious) from Configuration > Jobs with confirmation and output feedback.

## Implementation Approach

### Backend

- **ScheduledTaskService**: Command whitelist (`TRIGGERABLE_COMMANDS`), rate limiting for backup:run (5 min), `run(command, userId, options)` executing via Artisan::call and recording to TaskRun. `getLastRun` / `getRunHistory` for UI. Graceful behavior when `task_runs` table is missing (e.g. before migration).
- **TaskRun model + migration**: `task_runs` table stores command, user_id (null = scheduled), status (running/success/failed), started_at, completed_at, output, error, duration_ms. Used for last-run display and rate limiting.
- **JobController**: Injects ScheduledTaskService and AuditService. `scheduled()` parses `schedule:list` output, merges triggerable metadata and last_run; appends triggerable-only commands (e.g. log:cleanup) not in schedule. `run(Request, command)` validates whitelist, calls service, audits `scheduled_command_run`, returns success/output/duration (422 on failure).
- **Routes**: `POST /api/jobs/run/{command}` (admin) with optional body `{ options: { "--dry-run": true } }`.

### Frontend

- **Jobs page**: Extended ScheduledTask type with triggerable, dangerous, last_run. Table columns: Command (with Destructive badge), Schedule, Last Run, Description, Actions (Run Now for triggerable tasks). Run Now opens dialog: confirmation (extra warning for dangerous), then run; shows spinner, then success/failure and output; Close clears and refreshes list.
- **Dialog**: Prevents close (onInteractOutside, onEscapeKeyDown) while running. Uses errorLogger for failed requests.

### Security

- Only whitelisted commands can be triggered (no arbitrary Artisan).
- Admin-only routes (existing middleware).
- Audit log entry for every manual run (command, success, duration_ms, exit_code).
- Rate limit on backup:run (5 minutes) to avoid spam.
- Dangerous flag (e.g. log:cleanup) shows extra confirmation in UI.

## Challenges Encountered

- Laravel `schedule:list` output format varies; parser uses regex for "artisan X" and "Next Due:" to support different table layouts.
- TaskRun recording must not break when migration has not run; service checks `Schema::hasTable('task_runs')` before create/read.

## Observations

- Backup schedule configuration remains in Configuration > Backup (Settings tab); Jobs page is for monitoring and manual run, not editing schedule.
- Run history (task_runs) enables future "Recent runs" per command without new endpoints.

## Trade-offs

- Commands run synchronously in the web request; long-running commands (e.g. large backup) may hit timeouts. Future option: dispatch to queue and poll status.
- schedule:list parsing is best-effort; if Laravel changes output format, we may need to adjust or use Schedule facade.

## Next Steps (Future Considerations)

- Optional: Task detail dialog with last 10 runs and optional parameters (e.g. --dry-run for log:cleanup).
- Notification digest settings (from Admin Features roadmap) remain separate.

## Testing Notes

- Run migration: `php artisan migrate` (or via Docker).
- As admin, open Configuration > Jobs, Scheduled Tasks tab; verify tasks list with Last Run and Run Now on triggerable rows.
- Run backup:run (or log:check-suspicious); confirm dialog, output, and last run update. Run again within 5 min for backup:run and confirm rate-limit message.
- Verify audit log shows `scheduled_command_run` with command and success.
