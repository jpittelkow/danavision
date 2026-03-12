# Dashboard Section Redesign

**Date**: 2026-03-08
**Type**: Enhancement
**Scope**: Frontend dashboard, documentation

## Context

The dashboard homepage had a flat layout: a welcome banner, a 3-column grid with 2 stat cards + quick actions, and an admin-only Usage & Costs section that often showed just one card. The layout lacked visual hierarchy and clear grouping.

## Changes

### New: DashboardSection component
- `frontend/components/dashboard/dashboard-section.tsx`
- Wraps widgets with heading, optional description, and optional "View all" action link
- `columns` prop controls responsive grid: `"1"` | `"2"` | `"3"` | `"4"`
- Clean, consistent section structure across the dashboard

### New: 6 example widgets (9 visual patterns documented)

| Widget | Pattern | File |
|--------|---------|------|
| RecentActivityWidget | List/feed | `widgets/recent-activity-widget.tsx` |
| SystemHealthWidget | Status indicators | `widgets/system-health-widget.tsx` |
| StorageOverviewWidget | Progress bar + legend | `widgets/storage-overview-widget.tsx` |
| UpcomingTasksWidget | Timeline/schedule | `widgets/upcoming-tasks-widget.tsx` |
| NotificationsWidget | Summary stats grid | `widgets/notifications-widget.tsx` |
| EnvironmentWidget | Key-value info | `widgets/environment-widget.tsx` |

All example widgets use static data with clear comments showing how to replace with React Query API calls.

### Redesigned dashboard layout

4 sections with clear visual hierarchy:
1. **Overview** (4-col) — StatsWidget + NotificationsWidget + QuickActionsWidget
2. **Activity & System** (3-col) — RecentActivityWidget (col-span-2) + SystemHealthWidget
3. **Infrastructure** (3-col) — StorageOverviewWidget + UpcomingTasksWidget + EnvironmentWidget
4. **Usage & Costs** (1-col, admin only) — UsageDashboardWidget

### Updated documentation
- `docs/ai/patterns/dashboard-widget.md` — complete rewrite with architecture overview, all 9 widget patterns documented with code snippets, section component API, widget inventory table
- `docs/ai/recipes/add-dashboard-widget.md` — updated reference implementations list and Step 2 to use DashboardSection

## Decisions

- **Static example data**: Widgets use hardcoded data arrays rather than API calls. Each has a JSDoc comment with the React Query pattern to follow when wiring to real endpoints. This keeps the examples simple and avoids creating unnecessary backend endpoints.
- **DashboardSection over raw divs**: Extracted the section heading + grid pattern into a reusable component to enforce consistency and reduce boilerplate.
- **`space-y-8` over `space-y-6`**: Increased vertical spacing between sections to better separate the grouped content.

## Follow-up

- Wire example widgets to real API endpoints as features are built
- Consider adding a `DashboardController` method for health checks, storage breakdown, etc.
- The `AuditStatsCard` component works well as a generic stat card despite its name — consider renaming to `StatCard` in a future cleanup

## Related

- [Dashboard Widget Pattern](../ai/patterns/dashboard-widget.md)
- [Recipe: Add Dashboard Widget](../ai/recipes/add-dashboard-widget.md)
- [Dashboard Static Simplification (2026-01-30)](2026-01-30-dashboard-static-simplification.md)
- [Integration Usage Dashboard (2026-02-14)](2026-02-14-integration-usage-dashboard.md)
- [Bug Tracker](../plans/bug-tracker.md) — dashboard entry
