"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Skeleton } from "@/components/ui/skeleton";
import { errorLogger } from "@/lib/error-logger";
import { Users, HardDrive } from "lucide-react";
import { AuditStatsCard, type AuditStatsCardVariant } from "@/components/audit/audit-stats-card";
import type { LucideIcon } from "lucide-react";

interface Metric {
  label: string;
  value: string | number;
}

interface StatsResponse {
  metrics?: Metric[];
}

const metricIcons: Record<string, LucideIcon> = {
  "Total Users": Users,
  "Storage Used": HardDrive,
};

const metricVariants: Record<string, AuditStatsCardVariant> = {
  "Total Users": "info",
  "Storage Used": "success",
};

export function StatsWidget() {
  const { data, isLoading, error } = useQuery({
    queryKey: ["dashboard", "stats"],
    queryFn: async (): Promise<StatsResponse> => {
      const res = await api.get<StatsResponse>("/dashboard/stats");
      return res.data;
    },
  });

  if (error) {
    errorLogger.report(error instanceof Error ? error : new Error(String(error)), {
      context: "StatsWidget",
    });
    return null;
  }

  if (isLoading) {
    return (
      <>
        <Skeleton className="h-[120px] rounded-lg" />
        <Skeleton className="h-[120px] rounded-lg" />
      </>
    );
  }

  const metrics = data?.metrics ?? [];

  return (
    <>
      {metrics.map((metric: Metric, index: number) => (
        <div
          key={metric.label}
          className="animate-in fade-in slide-in-from-bottom-2"
          style={{ animationDelay: `${index * 75}ms`, animationFillMode: "backwards" }}
        >
          <AuditStatsCard
            title={metric.label}
            value={metric.value}
            icon={metricIcons[metric.label] ?? Users}
            variant={metricVariants[metric.label] ?? "default"}
          />
        </div>
      ))}
    </>
  );
}
