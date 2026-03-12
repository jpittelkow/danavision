"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

interface StorageStatsResponse {
  driver: string;
  total_size: number;
  total_size_formatted: string;
  file_count: number;
  breakdown?: Record<string, { size: number; size_formatted: string }>;
}

interface StorageHealthResponse {
  disk_free_bytes: number;
  disk_total_bytes: number;
  disk_used_percent: number;
  disk_free_formatted: string;
  disk_total_formatted: string;
}

const categoryColors: Record<string, string> = {
  "app": "bg-blue-500",
  "app/public": "bg-emerald-500",
  "app/backups": "bg-amber-500",
  "framework/cache": "bg-purple-500",
  "framework/sessions": "bg-pink-500",
  "logs": "bg-orange-500",
};

const categoryLabels: Record<string, string> = {
  "app": "App Files",
  "app/public": "Public",
  "app/backups": "Backups",
  "framework/cache": "Cache",
  "framework/sessions": "Sessions",
  "logs": "Logs",
};

export function StorageOverviewWidget() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ["dashboard", "storage-stats"],
    queryFn: async (): Promise<StorageStatsResponse> => {
      const res = await api.get<StorageStatsResponse>("/storage-settings/stats");
      return res.data;
    },
  });

  const { data: health, isLoading: healthLoading } = useQuery({
    queryKey: ["dashboard", "storage-health"],
    queryFn: async (): Promise<StorageHealthResponse> => {
      const res = await api.get<StorageHealthResponse>("/storage-settings/health");
      return res.data;
    },
  });

  const isLoading = statsLoading || healthLoading;

  const diskTotal = health?.disk_total_bytes ?? 0;
  const usedPercent = health?.disk_used_percent ?? 0;

  const categories = stats?.breakdown
    ? Object.entries(stats.breakdown)
        .filter(([, v]) => v.size > 0)
        .map(([key, v]) => ({
          label: categoryLabels[key] || key,
          size: v.size_formatted,
          bytes: v.size,
          color: categoryColors[key] || "bg-gray-500",
        }))
    : [];

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "225ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium">Storage</CardTitle>
          {!isLoading && health && (
            <span className="text-xs text-muted-foreground">
              {Math.round(usedPercent)}% of {health.disk_total_formatted} used
            </span>
          )}
        </div>
      </CardHeader>
      <CardContent className="pt-0 space-y-4">
        {isLoading ? (
          <div className="space-y-3">
            <Skeleton className="h-3 w-full rounded-full" />
            <div className="grid grid-cols-2 gap-2">
              {[1, 2, 3, 4].map((i) => (
                <Skeleton key={i} className="h-5 w-full" />
              ))}
            </div>
          </div>
        ) : categories.length > 0 ? (
          <>
            {/* Stacked progress bar */}
            <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
              {categories.map((category) => {
                const width = diskTotal > 0
                  ? (category.bytes / diskTotal) * 100
                  : 0;
                return (
                  <div
                    key={category.label}
                    className={cn("h-full first:rounded-l-full last:rounded-r-full", category.color)}
                    style={{ width: `${Math.max(width, 0.5)}%` }}
                  />
                );
              })}
            </div>

            {/* Breakdown legend */}
            <div className="grid grid-cols-2 gap-2">
              {categories.map((category) => (
                <div key={category.label} className="flex items-center gap-2 text-sm">
                  <span
                    className={cn(
                      "h-2.5 w-2.5 rounded-sm shrink-0",
                      category.color
                    )}
                  />
                  <span className="text-muted-foreground truncate">
                    {category.label}
                  </span>
                  <span className="ml-auto font-medium tabular-nums text-xs">
                    {category.size}
                  </span>
                </div>
              ))}
            </div>
          </>
        ) : (
          <p className="text-sm text-muted-foreground text-center py-2">
            {stats?.total_size_formatted ?? "No data"} total
          </p>
        )}
      </CardContent>
    </Card>
  );
}
