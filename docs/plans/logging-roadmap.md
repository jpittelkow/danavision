# Logging Roadmap

Future work for log storage, archival, export, and cleanup.

**Priority**: LOW  
**Status**: Planned  
**Last Updated**: 2026-01-29

**Dependencies**: Live console logs and HIPAA access logging are implemented.

---

## Log Storage & Archival (Future)

- [x] Evaluate log storage options (file, database, external e.g. S3) — see [Log Storage Evaluation](log-storage-evaluation.md)
- [ ] Implement archival for old entries (compress and move to cold storage)
- [x] Add indexes for common query patterns (audit_logs: severity+created_at; access_logs: action+created_at)
- [ ] Consider log aggregation service integration (Datadog, ELK)

## Log Export (Future)

- [x] Export application logs (not just access/audit) to CSV/JSON — GET /api/app-logs/export (admin)
- [x] Filter by date range, level, correlation ID
- [ ] Scheduled export to external storage

## Log Cleanup (Future)

- [x] Configurable retention per log type — Configuration > Log retention; app (1–365d), audit (30–730d), access (6yr min)
- [x] Scheduled cleanup command with dry-run option — `php artisan log:cleanup --dry-run`
- [x] Archive-before-delete option — `php artisan log:cleanup --archive` (CSV to storage/app/log-archive/)
- [x] Admin UI for cleanup configuration — Configuration > Log retention

---

## Key Files

- `backend/config/logging.php` — retention config, channels
- `backend/app/Services/AccessLogService.php`
- `backend/app/Services/AppLogExportService.php` — app log file export
- `backend/app/Http/Controllers/Api/AppLogExportController.php` — GET /api/app-logs/export
- `backend/app/Http/Controllers/Api/LogRetentionController.php` — log retention settings
- `backend/app/Console/Commands/LogCleanupCommand.php` — log:cleanup
- `backend/app/Console/Commands/CheckSuspiciousActivityCommand.php` — log:check-suspicious
- `backend/app/Services/SuspiciousActivityService.php` — suspicious pattern detection
- `docs/logging.md`

## Related

- [Audit Logs Roadmap](audit-logs-roadmap.md)
- [Logging standards](../logging.md)

## Implementation Journal

- [Console and Application Logging](../journal/2026-01-29-console-app-logging.md)
- [Live Console Logs & HIPAA Access Logging](../journal/2026-01-29-live-logs-hipaa-logging.md)
- [Access Logs Field Tracking](../journal/2026-01-29-access-logs-field-tracking.md)
- [HIPAA Access Logging Toggle](../journal/2026-01-29-hipaa-logging-toggle.md)
