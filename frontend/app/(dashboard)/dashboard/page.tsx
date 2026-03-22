"use client";

import {
  WelcomeWidget,
  QuickActionsWidget,
  ShoppingStatCards,
  RecentDropsWidget,
  PriceActivityChart,
  StoreLeaderboard,
} from "@/components/dashboard/widgets";
import { DashboardSection } from "@/components/dashboard/dashboard-section";
import { OfflineBadge } from "@/components/offline-badge";

export default function DashboardPage() {
  return (
    <div className="space-y-8">
      <div className="flex items-center gap-2 flex-wrap">
        <OfflineBadge />
      </div>

      <WelcomeWidget />

      <QuickActionsWidget />

      {/* Shopping — price tracking stats */}
      <DashboardSection
        title="Shopping"
        description="Price tracking and savings overview"
        actionHref="/lists"
        actionLabel="View lists"
        columns="4"
      >
        <ShoppingStatCards />
      </DashboardSection>

      {/* Shopping details — drops, activity, stores */}
      <DashboardSection title="Shopping Activity" columns="3">
        <RecentDropsWidget />
        <PriceActivityChart />
        <StoreLeaderboard />
      </DashboardSection>
    </div>
  );
}
