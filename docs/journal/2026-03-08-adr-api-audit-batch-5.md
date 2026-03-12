# ADR API Audit — Batch 5: Infrastructure & UI

**Date**: 2026-03-08
**Related**: [ADR API Audit Roadmap](../plans/adr-api-audit-roadmap.md)

## Summary

Final batch of the ADR & API Documentation Audit. Audited 7 ADRs covering infrastructure and UI concerns, plus cross-cutting checks across all routes.

## ADRs Audited

| ADR | Title | Result | Changes Made |
|-----|-------|--------|-------------|
| 001 | Technology Stack | ✅ Clean | None — all stack claims verified |
| 008 | Testing Strategy | ⚠️ Fixed | Updated MSW → `vi.mock()` in mocking strategy |
| 009 | Docker Single-Container | ⚠️ Fixed | Updated process diagram (5→8 services), health check description, base image ref, supervisord path |
| 011 | Global Navigation Architecture | ⚠️ Fixed | Corrected header description, added localStorage persistence, AppShell provider docs |
| 013 | Responsive Mobile-First Design | ✅ Clean | None — breakpoints, hooks, CSS patterns all accurate |
| 019 | Progressive Web App | ✅ Clean | None — all 5 phases verified complete |
| 023 | Audit Logging System | ⚠️ Fixed | Fixed OpenAPI schema (removed fabricated fields, added real ones), export params, stats permission, README permission language |

## Cross-Cutting Checks

After completing all 5 batches (30/30 ADRs), performed global verification:

- **README coverage**: All ~240 api.php routes documented
- **OpenAPI accuracy**: Fixed duplicate path definitions for `/notification-settings` and `/user/notification-settings` (stale copies from Batch 2). Added web-route note to SSO redirect endpoint.
- **~25 endpoints** exist in api.php but lack OpenAPI specs (onboarding, graphql admin, user API keys, branding mutations, client-errors). These are lower-priority and can be added incrementally.

## Files Changed

### ADR Updates
- `docs/adr/008-testing-strategy.md` — MSW → vi.mock()
- `docs/adr/009-docker-single-container.md` — Process diagram, health check, base image, supervisord path
- `docs/adr/011-global-navigation-architecture.md` — Header description, localStorage, AppShell providers

### API Documentation
- `docs/api/openapi.yaml` — AuditLog schema fix, export params, stats permission, duplicate removal, SSO note
- `docs/api/README.md` — Audit logs permission language, filter lists

### Roadmap
- `docs/plans/adr-api-audit-roadmap.md` — Batch 5 table, findings, cross-cutting checks, summary counts

## Audit Totals (All Batches)

- **30/30 ADRs audited**
- **65 API doc issues found and fixed**
- **4 implementation gaps found and fixed**
- **16 ADR updates made**
- **1 bug logged** (GET /storage-settings incomplete response)

## Lessons Learned

1. **Architecture-only ADRs drift less** — ADRs 001, 013, 019 had no issues because they describe patterns, not specific endpoints/schemas.
2. **Docker ADR had the most stale claims** — The container evolved significantly (added Reverb, Scheduler, Search-Reindex) without ADR updates.
3. **OpenAPI duplicate paths are silent errors** — YAML maps overwrite duplicate keys, so the second definition silently replaces the first. Always search for duplicates after adding new endpoint sections.
