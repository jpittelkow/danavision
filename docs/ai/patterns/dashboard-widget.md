# Dashboard Widget & Section Pattern

Dashboard uses static, developer-defined widgets organized into **sections**. Widgets are self-contained React components; sections group them with headings, descriptions, and optional action links. No database storage or user configuration — just React components on a page.

## Architecture Overview

```
DashboardPage
├── WelcomeWidget                      (full-width greeting banner)
├── DashboardSection "Overview"        (4-col stat cards)
│   ├── StatsWidget                    (API-backed metric cards)
│   ├── NotificationsWidget            (summary stats grid)
│   └── QuickActionsWidget             (icon tile grid)
├── DashboardSection "Activity & System" (3-col)
│   ├── RecentActivityWidget           (list/feed, col-span-2)
│   └── SystemHealthWidget             (status indicators)
├── DashboardSection "Infrastructure"  (3-col)
│   ├── StorageOverviewWidget          (progress bar + breakdown)
│   ├── UpcomingTasksWidget            (timeline/schedule)
│   └── EnvironmentWidget              (key-value info)
└── DashboardSection "Usage & Costs"   (1-col, admin only)
    └── UsageDashboardWidget           (chart + stats)
```

## DashboardSection Component

Wraps a group of widgets with a heading, optional description, and optional action link. Controls responsive grid layout via the `columns` prop.

```tsx
// frontend/components/dashboard/dashboard-section.tsx
import { DashboardSection } from "@/components/dashboard/dashboard-section";

<DashboardSection
  title="Activity & System"
  description="Recent events and system status"
  actionHref="/configuration/audit"
  actionLabel="View audit logs"
  columns="3"  // "1" | "2" | "3" | "4"
>
  <RecentActivityWidget />
  <SystemHealthWidget />
</DashboardSection>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | required | Section heading |
| `description` | `string` | — | Subtitle below heading |
| `actionHref` | `string` | — | "View all" link URL |
| `actionLabel` | `string` | `"View all"` | Link label |
| `columns` | `"1" \| "2" \| "3" \| "4"` | `"3"` | Responsive grid columns |
| `className` | `string` | — | Additional grid container class |

**Column mappings:**
- `"1"` → `grid-cols-1`
- `"2"` → `grid-cols-1 md:grid-cols-2`
- `"3"` → `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`
- `"4"` → `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`

## Widget Catalog

### 1. Metric Card (StatCard pattern)

Large number with icon and optional variant coloring. Uses `AuditStatsCard`.

```tsx
import { AuditStatsCard } from "@/components/audit/audit-stats-card";
import { Users } from "lucide-react";

<AuditStatsCard
  title="Total Users"
  value={42}
  description="Active accounts"
  icon={Users}
  variant="info"  // "default" | "warning" | "error" | "success" | "info"
/>
```

**Variants:** `default` (primary), `warning` (amber), `error` (red), `success` (green), `info` (blue)

**Example:** `StatsWidget` — fetches from `/dashboard/stats`, renders multiple `AuditStatsCard`s.

### 2. List / Activity Feed

Vertical list of items with icons, text, and timestamps. Use `md:col-span-2` for wider lists.

```tsx
<div className="flex items-center gap-3 text-sm">
  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-500/10">
    <Icon className="h-4 w-4 text-blue-600" />
  </div>
  <div className="min-w-0 flex-1">
    <p className="font-medium truncate">{action}</p>
    <p className="text-xs text-muted-foreground">{actor}</p>
  </div>
  <span className="shrink-0 text-xs text-muted-foreground">{timestamp}</span>
</div>
```

**Example:** `RecentActivityWidget` — audit-like event feed with severity-colored icons.

### 3. Status Indicators

Colored dots with labels showing service health. Include an overall summary in the header.

```tsx
<div className="flex items-center justify-between text-sm">
  <div className="flex items-center gap-2.5">
    <span className="h-2 w-2 rounded-full bg-green-500" />
    <span>Database</span>
  </div>
  <span className="text-xs font-medium text-green-600">Healthy</span>
</div>
```

**Statuses:** `healthy` (green), `degraded` (amber), `down` (red)

**Example:** `SystemHealthWidget` — service health checklist.

### 4. Progress Bar

Stacked progress bar with color-coded segments and a breakdown legend.

```tsx
{/* Stacked bar */}
<div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
  <div className="h-full bg-blue-500" style={{ width: "38%" }} />
  <div className="h-full bg-emerald-500" style={{ width: "28%" }} />
</div>

{/* Legend */}
<div className="grid grid-cols-2 gap-2">
  <div className="flex items-center gap-2 text-sm">
    <span className="h-2.5 w-2.5 rounded-sm bg-blue-500" />
    <span className="text-muted-foreground">Documents</span>
    <span className="ml-auto font-medium tabular-nums text-xs">2.4 GB</span>
  </div>
</div>
```

**Example:** `StorageOverviewWidget` — storage usage with type breakdown.

### 5. Timeline / Schedule

Items with icons, names, schedule info, and status badges.

```tsx
<div className="flex items-center gap-3 text-sm">
  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
    <Database className="h-4 w-4 text-muted-foreground" />
  </div>
  <div className="min-w-0 flex-1">
    <p className="font-medium truncate">Database Backup</p>
    <p className="text-xs text-muted-foreground">2:00 AM &middot; Daily</p>
  </div>
  <Badge variant="secondary" className="text-[10px]">Scheduled</Badge>
</div>
```

**Example:** `UpcomingTasksWidget` — scheduled job preview.

### 6. Summary Stats Grid

2x2 grid of compact metric tiles with icons.

```tsx
<div className="grid grid-cols-2 gap-2">
  <div className="flex items-center gap-2.5 rounded-lg border p-2.5">
    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/10">
      <BellDot className="h-4 w-4 text-blue-600" />
    </div>
    <div>
      <p className="text-lg font-bold tabular-nums leading-none">3</p>
      <p className="text-[10px] text-muted-foreground mt-0.5">Unread</p>
    </div>
  </div>
</div>
```

**Example:** `NotificationsWidget` — notification counts by type.

### 7. Key-Value Info

Definition-list style rows showing system information.

```tsx
<div className="flex items-center justify-between text-sm">
  <span className="text-muted-foreground">PHP Version</span>
  <div className="flex items-center gap-2">
    <span className="font-medium">8.3.12</span>
    <Badge variant="default" className="text-[10px] h-5">Live</Badge>
  </div>
</div>
```

**Example:** `EnvironmentWidget` — environment details with optional badges.

### 8. Icon Tile Grid

2x2 grid of action tiles with icons and labels.

```tsx
<div className="grid grid-cols-2 gap-2">
  <Link href="/configuration/audit"
    className="flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-colors hover:bg-muted min-h-[72px] justify-center">
    <ClipboardList className="h-5 w-5 text-muted-foreground" />
    <span className="text-xs font-medium">Audit Logs</span>
  </Link>
</div>
```

**Example:** `QuickActionsWidget` — admin quick-links.

### 9. Chart Widget

Full-width area/bar chart with Recharts and time range toggles.

**Example:** `UsageDashboardWidget` — daily spend trend with 7d/14d/30d toggle.

## Widget Spanning

Use `md:col-span-2` or `lg:col-span-2` to make a widget wider within a section grid:

```tsx
<Card className="md:col-span-2">
  {/* This widget spans 2 of 3 columns */}
</Card>
```

## Animation Pattern

Staggered fade-in animations with increasing delays:

```tsx
<Card
  className="animate-in fade-in slide-in-from-bottom-2"
  style={{ animationDelay: "150ms", animationFillMode: "backwards" }}
>
```

## Data Fetching

**React Query (recommended):**
```tsx
const { data, isLoading, error } = useQuery({
  queryKey: ["dashboard", "my-widget"],
  queryFn: () => api.get("/dashboard/my-endpoint").then(r => r.data),
  staleTime: 5 * 60 * 1000,
});
```

**Static/example widgets:** Use a static data array with a comment noting "replace with API call". Each example widget in `frontend/components/dashboard/widgets/` follows this pattern.

## Permission-Based Visibility

Wrap sections or individual widgets in permission checks:

```tsx
{canViewUsage && (
  <DashboardSection title="Usage & Costs" columns="1">
    <UsageDashboardWidget />
  </DashboardSection>
)}
```

## Key Files

| Component | Path |
|-----------|------|
| Dashboard page | `frontend/app/(dashboard)/dashboard/page.tsx` |
| DashboardSection | `frontend/components/dashboard/dashboard-section.tsx` |
| WidgetCard | `frontend/components/dashboard/widget-card.tsx` |
| WidgetSkeleton | `frontend/components/dashboard/widget-skeleton.tsx` |
| Widget barrel export | `frontend/components/dashboard/widgets/index.ts` |
| AuditStatsCard | `frontend/components/audit/audit-stats-card.tsx` |

**Widget inventory:**

| Widget | Pattern | File |
|--------|---------|------|
| WelcomeWidget | Banner | `widgets/welcome-widget.tsx` |
| StatsWidget | Metric cards (API) | `widgets/stats-widget.tsx` |
| QuickActionsWidget | Icon tile grid | `widgets/quick-actions-widget.tsx` |
| RecentActivityWidget | List/feed | `widgets/recent-activity-widget.tsx` |
| SystemHealthWidget | Status indicators | `widgets/system-health-widget.tsx` |
| StorageOverviewWidget | Progress bar | `widgets/storage-overview-widget.tsx` |
| UpcomingTasksWidget | Timeline/schedule | `widgets/upcoming-tasks-widget.tsx` |
| NotificationsWidget | Summary stats grid | `widgets/notifications-widget.tsx` |
| EnvironmentWidget | Key-value info | `widgets/environment-widget.tsx` |
| UsageDashboardWidget | Chart + stats | `components/usage/usage-dashboard-widget.tsx` |

## Implementation Journal

- [Dashboard Static Simplification (2026-01-30)](../../journal/2026-01-30-dashboard-static-simplification.md)
- [Integration Usage Dashboard (2026-02-14)](../../journal/2026-02-14-integration-usage-dashboard.md)
- [Dashboard Section Redesign (2026-03-08)](../../journal/2026-03-08-dashboard-section-redesign.md)

**Related:** [Recipe: Add Dashboard Widget](../recipes/add-dashboard-widget.md), [Anti-patterns: Widgets](../anti-patterns/widgets.md)
