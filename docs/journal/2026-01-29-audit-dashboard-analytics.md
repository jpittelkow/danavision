# Audit Dashboard Analytics (Phase 2) - 2026-01-29

## Overview

Implemented the Dashboard & Analytics phase of the Audit Logs roadmap: added an audit summary widget to the admin dashboard with stats cards, severity donut chart, activity trends area chart, and recent warnings list. Enhanced the stats API with `daily_trends` and `recent_warnings`.

## Implementation Approach

- **Charts**: Installed shadcn chart component (Recharts). Created `AuditSeverityChart` (donut) and `AuditTrendsChart` (area) using `ChartContainer`, `ChartTooltip`/`ChartTooltipContent`, and severity/trend config.
- **Stats API**: Extended `AuditLogController::stats()` with `daily_trends` (date → count for time-series) and `recent_warnings` (latest 5 warning/error/critical logs with user).
- **Widget**: `AuditDashboardWidget` fetches `/audit-logs/stats` (last 30 days), shows loading skeleton and error fallback, renders `AuditStatsCard` (total actions, warnings/errors), both charts, and optional recent-warnings list with “View all logs” link to `/configuration/audit`.
- **Dashboard**: Wired “System Activity” section and `AuditDashboardWidget` into the dashboard page for admin users only.

## Challenges Encountered

- **JSX structure**: Dashboard had two sibling divs (settings grid + audit section) under `user?.is_admin && (...)`. Wrapped them in a fragment to fix parse error.
- **Chart stroke**: Severity pie used `var(--color-background)`; that key is not in chart config. Switched to `hsl(var(--background))` for sector strokes.

## Observations

- Recharts + shadcn chart slot in cleanly; `ChartConfig` and `var(--color-*)` work well for theming.
- Stats cards use a simple variant system (default/warning/error) for subtle emphasis.

## Trade-offs

- Alerting for suspicious activity patterns remains unimplemented (roadmap item).
- Widget always requests last 30 days; no UI for custom date range (audit page has filters).

## Next Steps (Future Considerations)

- Add alerting for suspicious patterns (e.g. spike in auth.login_failed, many backup.restored).
- Optional date-range selector for the dashboard widget.

## Testing Notes

- As admin, open Dashboard; confirm “System Activity” section with stats, charts, and “View all logs”.
- Trigger some audit events (login, user changes, etc.); confirm stats and trends update.
- As non-admin, confirm audit widget is not shown.
- Check empty states when no data in range (e.g. new install).
- On stats fetch failure, use "Retry" in the error state to refetch.

## Follow-up (double-check pass)

- **Widget**: Extracted `fetchStats` (useCallback), added Retry button in error state.
- **Trends chart**: Parse `Y-m-d` as local date (`parseLocalDate`) to avoid timezone shifts in `formatChartDate`.
- **Docs**: Context-loading (dashboard widget, stats API, chart components); features.md (Audit Logs section); api-reference + api/README (audit-logs endpoints and stats response); add-dashboard-widget recipe (shadcn ChartContainer, audit widget see-also); patterns.md (Charts shadcn + Recharts pattern).
