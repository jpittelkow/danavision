# Dashboard Improvements Roadmap

Static dashboard with developer-defined widgets and comprehensive documentation for AI-assisted widget creation.

**Priority**: MEDIUM  
**Status**: Complete (2026-01-30)  
**Last Updated**: 2026-01-30

---

## Overview

Create a clean, static dashboard with developer-defined widgets. Focus on well-documented patterns that enable AI to easily create new widget types in future projects. No user configuration or widget selection—widgets are defined by developers and displayed to all users (with optional permission-based visibility).

### Key Principles

1. **Static by Default** - Widgets are code-defined, not user-selectable
2. **Documentation-First** - Patterns are thoroughly documented for AI reuse
3. **Simple Architecture** - Minimal infrastructure, maximum clarity
4. **Permission-Aware** - Widgets can be shown/hidden based on user permissions

---

## Task Checklist

### Phase 1: Cleanup - Remove User-Configurable Infrastructure

Remove the user-selection widget system built in earlier iterations:

- [x] Remove `user_widgets` table and `UserWidget` model
- [x] Remove `widgets` table and `Widget` model (widgets defined in code only)
- [x] Remove `WidgetController` and related API endpoints
- [x] Remove `DashboardController` API endpoints for user layout
- [x] Remove frontend `add-widget-modal.tsx`, `widget-settings-modal.tsx`
- [x] Remove frontend `useWidgets` hook (user widget management)
- [x] Remove `WidgetService` and `WidgetTypeRegistry` (no dynamic registry needed)
- [x] Remove `WidgetTypeHandler` interface (widgets are simple components)
- [x] Update permissions: remove `WIDGETS_VIEW`, `WIDGETS_CONFIGURE`, `WIDGETS_MANAGE`
- [x] Remove drag-and-drop dependencies if no longer needed (`@dnd-kit/*`)

### Phase 2: Static Dashboard Implementation

Build a simple, static dashboard:

- [x] Create dashboard page with static widget layout (grid)
- [x] Keep `WidgetCard` component (simplified - no settings/remove buttons)
- [x] Keep `WidgetSkeleton` for loading states
- [x] Remove `WidgetGrid` drag-and-drop (replace with simple CSS grid)
- [x] Implement permission-based widget visibility (optional, via `usePermission`)

### Phase 3: Sample Widgets (Reference Implementations)

Create 2-3 well-documented reference widgets:

- [x] **Welcome Widget** - Simple static content (reference for basic widget)
- [x] **Stats Widget** - Config-driven metrics display (reference for data-fetching)
- [x] **Quick Actions Widget** - Navigation links (reference for action widgets)

### Phase 4: Documentation & Recipe

Create comprehensive documentation for AI-assisted development:

- [x] Create recipe: `docs/ai/recipes/add-dashboard-widget.md` (simplified)
- [x] Document widget patterns in `docs/ai/patterns.md`
- [x] Add widget section to `docs/ai/context-loading.md`
- [x] Update `docs/features.md` with dashboard documentation

---

## Architecture

### Dashboard Layout

The dashboard displays a fixed set of widgets in a responsive grid:

```tsx
// frontend/app/(dashboard)/dashboard/page.tsx
export default function DashboardPage() {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
      <WelcomeWidget />
      <StatsWidget metrics={["users", "storage"]} />
      <QuickActionsWidget />
      {/* Add more widgets here */}
    </div>
  );
}
```

### Widget Component Pattern

Each widget is a self-contained React component:

```tsx
// frontend/components/dashboard/widgets/stats-widget.tsx
"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

interface StatsWidgetProps {
  /** Which metrics to display */
  metrics?: string[];
  /** Optional title override */
  title?: string;
}

export function StatsWidget({
  metrics = ["users", "storage"],
  title = "System Stats",
}: StatsWidgetProps) {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", "stats", metrics],
    queryFn: () => api.get("/api/dashboard/stats", { params: { metrics } }),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="animate-pulse space-y-2">
            <div className="h-4 bg-muted rounded w-3/4" />
            <div className="h-4 bg-muted rounded w-1/2" />
          </div>
        ) : (
          <div className="space-y-2">
            {data?.metrics?.map((metric: { label: string; value: string | number }) => (
              <div key={metric.label} className="flex justify-between text-sm">
                <span className="text-muted-foreground">{metric.label}</span>
                <span className="font-medium">{metric.value}</span>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
```

### Backend API Pattern (Optional)

For widgets that need data, create simple API endpoints:

```php
// backend/app/Http/Controllers/Api/DashboardController.php
class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $metrics = $request->input('metrics', ['users', 'storage']);
        $result = [];

        foreach ($metrics as $metric) {
            $result[] = match ($metric) {
                'users' => ['label' => 'Total Users', 'value' => User::count()],
                'storage' => ['label' => 'Storage Used', 'value' => $this->getStorageUsed()],
                'backups' => ['label' => 'Backups', 'value' => Backup::count()],
                default => ['label' => $metric, 'value' => 0],
            };
        }

        return response()->json(['metrics' => $result]);
    }
}
```

### Permission-Based Visibility

Show/hide widgets based on user permissions:

```tsx
// In dashboard page
import { usePermission } from "@/lib/use-permission";

export default function DashboardPage() {
  const { hasPermission } = usePermission();
  
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
      <WelcomeWidget />
      <StatsWidget />
      {hasPermission("admin") && <SystemHealthWidget />}
      {hasPermission("audit.view") && <RecentActivityWidget />}
    </div>
  );
}
```

---

## Widget Types Reference

### 1. Static Content Widget

No data fetching, just renders static content:

```tsx
export function WelcomeWidget() {
  const { user } = useAuth();
  return (
    <Card>
      <CardHeader>
        <CardTitle>Welcome back, {user?.name}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-sm text-muted-foreground">
          Here's your dashboard overview.
        </p>
      </CardContent>
    </Card>
  );
}
```

### 2. Data-Fetching Widget

Fetches data from an API endpoint:

```tsx
export function StatsWidget({ metrics }: { metrics: string[] }) {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", "stats", metrics],
    queryFn: () => api.get("/api/dashboard/stats", { params: { metrics } }),
  });
  // ... render with loading state
}
```

### 3. Action Widget

Contains buttons/links for quick navigation:

```tsx
export function QuickActionsWidget() {
  const actions = [
    { label: "View Audit Logs", href: "/audit", icon: ClipboardList },
    { label: "Manage Users", href: "/configuration/users", icon: Users },
    { label: "System Settings", href: "/configuration/system", icon: Settings },
  ];
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium">Quick Actions</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2">
        {actions.map((action) => (
          <Link key={action.href} href={action.href}>
            <Button variant="ghost" className="w-full justify-start">
              <action.icon className="mr-2 h-4 w-4" />
              {action.label}
            </Button>
          </Link>
        ))}
      </CardContent>
    </Card>
  );
}
```

### 4. Chart Widget

Uses shadcn charts for visualization:

```tsx
export function ActivityChartWidget() {
  const { data } = useQuery({
    queryKey: ["dashboard", "activity-chart"],
    queryFn: () => api.get("/api/dashboard/activity-chart"),
  });
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium">Activity (7 days)</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig}>
          <BarChart data={data?.chart ?? []}>
            {/* chart components */}
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
```

---

## Files Reference

### Removed (Phase 1 – completed)

The following were removed as part of the static dashboard simplification:

- Backend: `Widget.php`, `UserWidget.php`, `WidgetTypeHandler.php`, `WidgetService.php`, `WidgetTypeRegistry.php`, `WidgetController.php`, migration `*_create_widgets_tables.php`
- Frontend: `add-widget-modal.tsx`, `widget-settings-modal.tsx`, `widget-grid.tsx`, `use-widgets.ts`, `lib/widgets/registry.ts`, `lib/widgets/types.ts`
- Docs: `docs/ai/recipes/add-widget-type.md`

### Current layout and widgets (Phase 2–3)

```
frontend/app/(dashboard)/dashboard/page.tsx - Static dashboard layout
frontend/components/dashboard/widget-card.tsx - Simplified card wrapper
frontend/components/dashboard/widget-skeleton.tsx - Loading skeleton
frontend/components/dashboard/widgets/*.tsx - Individual widget components
backend/app/Http/Controllers/Api/DashboardController.php - Data endpoints (minimal)
docs/ai/recipes/add-dashboard-widget.md - Simplified recipe
```

---

## API Endpoints

Simplified API for widget data:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard/stats` | Get metrics for stats widget |
| GET | `/api/dashboard/activity` | Get recent activity for activity widget |
| GET | `/api/dashboard/health` | Get system health (admin only) |

---

## Documentation Goals

The primary goal is **excellent documentation** that enables AI to create new widgets easily:

1. **Pattern Documentation** (`docs/ai/patterns.md`)
   - Widget component structure
   - Data-fetching patterns (React Query)
   - Loading states and error handling
   - Permission-based visibility

2. **Recipe** (`docs/ai/recipes/add-dashboard-widget.md`)
   - Step-by-step guide to add a new widget
   - Examples of each widget type
   - Checklist for completeness

3. **Context Loading** (`docs/ai/context-loading.md`)
   - Which files to read when working on dashboard
   - Reference implementations to follow

4. **Feature Documentation** (`docs/features.md`)
   - User-facing documentation of dashboard features
   - Available widgets and what they show

---

## Implementation Notes

### Why Static Over User-Configurable

1. **Simpler Architecture** - No database tables, no API for user layouts
2. **Faster Development** - New widgets are just React components
3. **Easier Maintenance** - No migration/seeder complexity
4. **Better Performance** - No extra API calls to fetch user layout
5. **AI-Friendly** - Clear patterns are easier to follow and extend

### Future Extensibility

If user-configurable widgets are needed later:

1. The widget component pattern remains the same
2. Add a simple preference store (user settings JSON field)
3. Dashboard reads preferences and conditionally renders widgets
4. No need for complex registry/handler infrastructure
