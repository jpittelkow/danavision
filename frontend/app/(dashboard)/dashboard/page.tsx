"use client";

import {
  WelcomeWidget,
  StatsWidget,
  QuickActionsWidget,
  RecentActivityWidget,
  SystemHealthWidget,
  StorageOverviewWidget,
  UpcomingTasksWidget,
  NotificationsWidget,
  EnvironmentWidget,
} from "@/components/dashboard/widgets";
import { DashboardSection } from "@/components/dashboard/dashboard-section";
import { OfflineBadge } from "@/components/offline-badge";
import { UsageDashboardWidget } from "@/components/usage/usage-dashboard-widget";
import { useAuth, isAdminUser } from "@/lib/auth";

export default function DashboardPage() {
  const { user } = useAuth();
  const canViewUsage = user
    ? isAdminUser(user) || (user.permissions?.includes("usage.view") ?? false)
    : false;

  return (
    <div className="space-y-8">
      <div className="flex items-center gap-2 flex-wrap">
        <OfflineBadge />
      </div>

      <WelcomeWidget />

      {/* Overview — stat cards in a 4-col grid */}
      <DashboardSection title="Overview" columns="4">
        <StatsWidget />
        <NotificationsWidget />
        <QuickActionsWidget />
      </DashboardSection>

      {/* Activity & System — two-column layout with list + status widgets */}
      <DashboardSection
        title="Activity & System"
        actionHref="/configuration/audit"
        actionLabel="View audit logs"
        columns="3"
      >
        <RecentActivityWidget />
        <SystemHealthWidget />
      </DashboardSection>

      {/* Infrastructure — storage, tasks, environment */}
      <DashboardSection
        title="Infrastructure"
        description="Storage, scheduled jobs, and environment details"
        columns="3"
      >
        <StorageOverviewWidget />
        <UpcomingTasksWidget />
        <EnvironmentWidget />
      </DashboardSection>

      {/* Usage & Costs — admin only */}
      {canViewUsage && (
        <DashboardSection
          title="Usage & Costs"
          actionHref="/configuration/usage"
          actionLabel="View details"
          columns="1"
        >
          <UsageDashboardWidget />
        </DashboardSection>
      )}
    </div>
  );
}
