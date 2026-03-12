# Dashboard Static Simplification - 2026-01-30

## Overview

The dashboard was simplified from a user-configurable widget system to static, developer-defined widgets. All infrastructure for user layout, widget selection, and dynamic registries was removed. The dashboard now displays a fixed set of widgets in a responsive grid with well-documented patterns for adding new widgets.

## Implementation Approach

- **Phase 1 (Backend cleanup):** Removed `Widget` and `UserWidget` models, `widgets` and `user_widgets` tables, `WidgetController`, `WidgetService`, `WidgetTypeRegistry`, `WidgetTypeHandler` contract, widget-related permissions (`WIDGETS_VIEW`, `WIDGETS_CONFIGURE`, `WIDGETS_MANAGE`), and user dashboard layout routes. Updated `AppServiceProvider` and `UserGroupSeeder`.
- **Phase 2 (Frontend cleanup):** Removed `add-widget-modal.tsx`, `widget-settings-modal.tsx`, `widget-grid.tsx`, `use-widgets.ts`, `frontend/lib/widgets/` (registry and types), and `@dnd-kit/*` dependencies. Simplified `WidgetCard` to a title + children wrapper; kept `WidgetSkeleton`.
- **Phase 3 (Static dashboard):** Rewrote dashboard page with a static grid. Created sample widgets in `frontend/components/dashboard/widgets/`: `WelcomeWidget` (static content), `StatsWidget` (data-fetching), `QuickActionsWidget` (navigation links).
- **Phase 4 (Backend data):** Replaced `DashboardController` with a minimal implementation exposing only `stats()` returning Total Users and Storage Used; added `GET /dashboard/stats` route.
- **Phase 5 (Documentation):** Deleted `add-widget-type.md`. Updated `add-dashboard-widget.md`, `patterns.md`, `anti-patterns.md`, `context-loading.md`, `features.md`, and `roadmaps.md`. Added anti-pattern: "Don't create user-configurable widget infrastructure."

## Challenges Encountered

- PowerShell does not support `&&` for chaining commands; used `;` for `npm install` after package.json change.
- No handler implementations or widget seed data existed; removal was straightforward with no migration rollback needed for existing data (migration file deleted).

## Observations

- The previous system had no concrete widget types registered—only infrastructure. The static approach delivers immediate value with three reference widgets.
- `WidgetCard` is now a simple presentational wrapper; individual widgets use it or `Card` directly as appropriate.
- Documentation is aligned: patterns, anti-patterns, recipe, and context-loading all describe the static approach and reference the new widget folder.

## Trade-offs

- **Lost:** User ability to add/remove/reorder widgets. If needed later, a simple preference store (e.g. user settings JSON) could drive conditional rendering without reintroducing database tables or registries.
- **Gained:** Simpler codebase, no widget-related permissions or migrations, faster dashboard load (no layout API), and clear patterns for AI-assisted widget creation.

## Next Steps (Future Considerations)

- Add permission-based widget visibility examples (e.g. admin-only system health widget) when such widgets are added.
- Optional: add more reference widgets (e.g. activity chart) following the same pattern.

## Testing Notes

- Dashboard page loads and displays Welcome, Stats, and Quick Actions widgets.
- Stats widget fetches from `GET /api/dashboard/stats` and shows Total Users and Storage Used.
- No references to removed hooks, modals, or registry in the dashboard code path.
- Run `npm run build` and backend tests to confirm no regressions.
