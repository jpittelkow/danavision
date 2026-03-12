# Log Storage Evaluation

Decision record for where and how application, audit, and access logs are stored.

**Date**: 2026-01-29  
**Status**: Decided  
**Related**: [Logging Roadmap](logging-roadmap.md), [Audit Logs Roadmap](audit-logs-roadmap.md), [Logging standards](../logging.md)

---

## Summary

- **Application logs**: Remain file-based (single/daily channels). No move to database or external storage for current scale.
- **Audit logs**: Remain in the application database (SQLite/MySQL). Indexes added for common query patterns.
- **Access logs**: Remain in the application database. HIPAA retention (6 years) satisfied with existing DB storage; archival to cold storage is optional future work.

External storage (e.g. S3) and log aggregation services (Datadog, ELK) are deferred until operational need (scale, compliance, or centralization) justifies them.

---

## Options Considered

| Option | Pros | Cons |
|--------|------|------|
| **File only** | Simple, no DB growth, works with log shippers | No in-app query/export by level or correlation_id |
| **Database only** | Queryable, consistent with audit/access | Application log volume can grow quickly; DB size and backup bloat |
| **Hybrid (current)** | App logs in file (rotation), audit/access in DB | Two storage paths to maintain |
| **External (S3, blob)** | Scalable, durable, off-app storage | Extra infra, cost, and tooling; not required at current scale |
| **Log aggregation (Datadog, ELK)** | Centralized search, dashboards, alerting | Cost and operational overhead; optional for future |

---

## Decision

- **Keep current hybrid model**: Application logs stay in files (with optional JSON and broadcast channels). Audit and access logs stay in the database with appropriate indexes.
- **Indexes**: Add composite indexes for common filters (e.g. severity + created_at for audit, action + created_at for access) to keep list/export/stats queries fast.
- **Archival**: When retention or size becomes an issue, implement archive-before-delete (e.g. compress old DB rows or daily log files to S3) rather than moving live logs to external storage first.
- **Revisit**: Re-evaluate external storage or aggregation when (a) log volume or retention requirements grow, (b) multi-instance or multi-app centralization is needed, or (c) compliance requires long-term off-app retention.

---

## Indexes Added (Phase 1)

- **audit_logs**: `(severity, created_at)` — supports filter by severity and date-ordered listing/stats.
- **access_logs**: `(action, created_at)` — supports filter by action and date-ordered listing/stats.

Existing indexes already cover user_id, action (audit), resource_type, and created_at.

---

## Key Files

- `backend/config/logging.php` — channel configuration (single, daily, json, broadcast).
- `backend/database/migrations/2026_01_29_000016_add_log_indexes_for_queries.php` — new indexes.
- `docs/logging.md` — logging standards and configuration.
