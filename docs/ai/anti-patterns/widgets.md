# Widget Anti-Patterns

### Don't: Create Widgets Without Loading/Error States

```tsx
// BAD - no loading or error handling
function StatsWidget() {
  const { data } = useQuery({ queryKey: ["stats"], queryFn: fetchStats });
  return <div>{data?.total}</div>;
}

// GOOD - handle loading and error states
function StatsWidget() {
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["stats"],
    queryFn: fetchStats,
  });

  if (isLoading) return <WidgetSkeleton />;
  if (error) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center py-4">
          <AlertCircle className="h-6 w-6 text-destructive" />
          <p className="text-sm text-muted-foreground">Failed to load</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            Retry
          </Button>
        </CardContent>
      </Card>
    );
  }

  return <div>{data?.total}</div>;
}
```

### Don't: Forget Permission Checks for Admin Widgets

```tsx
// BAD - admin-only widget shown to all users
function Dashboard() {
  return (
    <div className="grid gap-4">
      <WelcomeWidget />
      <SystemHealthWidget /> {/* Should be admin-only! */}
    </div>
  );
}

// GOOD - use permission checks for sensitive widgets
function Dashboard() {
  const { hasPermission } = usePermission();
  return (
    <div className="grid gap-4">
      <WelcomeWidget />
      {hasPermission("admin") && <SystemHealthWidget />}
      {hasPermission("audit.view") && <RecentActivityWidget />}
    </div>
  );
}
```

### Don't: Create User-Configurable Widget Infrastructure

Widgets are static and developer-defined. Do not add:

- Database tables for widgets or user widget layout
- Backend registries or handler interfaces for widget types
- Frontend modals for adding/removing widgets or drag-and-drop layout
- Permissions for "widgets.view" or "widgets.configure"

To add a new widget, create a React component in `frontend/components/dashboard/widgets/`, add it to the dashboard page, and optionally add a data endpoint in `DashboardController`. See [Recipe: Add Dashboard Widget](../recipes/add-dashboard-widget.md).
