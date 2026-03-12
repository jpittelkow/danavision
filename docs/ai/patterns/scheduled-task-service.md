# ScheduledTaskService Pattern (Manual Run of Scheduled Commands)

**Whitelist-only:** Only commands listed in `ScheduledTaskService::TRIGGERABLE_COMMANDS` may be run from the Jobs UI. Prevents arbitrary Artisan execution. Add new triggerable commands there with `description` and `dangerous` (extra confirmation in UI).

## Flow

Controller validates command via `isTriggerable()`, calls `run($command, $userId, $options)`. Service creates `TaskRun` (if table exists), runs `Artisan::call()`, updates TaskRun with status/output/duration, returns `success`, `output`, `duration_ms`, `exit_code`. Controller audits `scheduled_command_run` and returns JSON (422 on failure).

## Rate Limiting

Optional `RATE_LIMIT_SECONDS` per command (e.g. `backup:run` 300s). Service uses `getLastRunAt()` from `task_runs`; block if last run was within the limit.

## Run History

`task_runs` stores command, user_id (null = scheduled), status, started_at, completed_at, output, error, duration_ms. Use `getLastRun()` / `getRunHistory()` for UI. Service checks `Schema::hasTable('task_runs')` so it works before migration.

**Key files:**
- `backend/app/Services/ScheduledTaskService.php`
- `backend/app/Http/Controllers/Api/JobController.php` (`scheduled()` parses `schedule:list`, merges triggerable metadata and last_run; appends triggerable-only commands; `run()` executes command)
- `backend/app/Models/TaskRun.php`
- `backend/routes/api.php` (jobs/* routes, admin)
- `frontend/app/(dashboard)/configuration/jobs/page.tsx` (Scheduled Tasks, Queue, Failed Jobs, Run Now)

**Related:** No dedicated recipes — this is a standalone pattern.

## Implementation Journal

- [Scheduled Jobs UI (2026-01-29)](../../journal/2026-01-29-scheduled-jobs-ui.md)
