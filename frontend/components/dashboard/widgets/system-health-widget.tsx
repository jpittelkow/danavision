"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

type ServiceStatus = "healthy" | "degraded" | "down";

interface StorageHealthResponse {
  writable: boolean;
  disk_free_bytes: number;
  disk_total_bytes: number;
  disk_used_percent: number;
  status: string;
  disk_free_formatted: string;
  disk_total_formatted: string;
}

interface QueueStatusResponse {
  pending: number;
  failed: number;
}

interface ServiceInfo {
  name: string;
  status: ServiceStatus;
  detail?: string;
}

const statusStyles: Record<ServiceStatus, { dot: string; label: string }> = {
  healthy: {
    dot: "bg-green-500",
    label: "text-green-600 dark:text-green-400",
  },
  degraded: {
    dot: "bg-amber-500",
    label: "text-amber-600 dark:text-amber-400",
  },
  down: { dot: "bg-red-500", label: "text-red-600 dark:text-red-400" },
};

const statusLabels: Record<ServiceStatus, string> = {
  healthy: "Healthy",
  degraded: "Degraded",
  down: "Down",
};

export function SystemHealthWidget() {
  const { data: storageHealth, isLoading: storageLoading } = useQuery({
    queryKey: ["dashboard", "storage-health"],
    queryFn: async (): Promise<StorageHealthResponse> => {
      const res = await api.get<StorageHealthResponse>("/storage-settings/health");
      return res.data;
    },
    refetchInterval: 60_000,
  });

  const { data: queueStatus, isLoading: queueLoading } = useQuery({
    queryKey: ["dashboard", "queue-status"],
    queryFn: async (): Promise<QueueStatusResponse> => {
      const res = await api.get<QueueStatusResponse>("/jobs/queue");
      return res.data;
    },
    refetchInterval: 60_000,
  });

  const isLoading = storageLoading || queueLoading;

  const services: ServiceInfo[] = [];

  if (storageHealth) {
    services.push({
      name: "Storage",
      status: storageHealth.status === "healthy" ? "healthy" : "degraded",
      detail: `${storageHealth.disk_free_formatted} free`,
    });
    services.push({
      name: "Disk Writable",
      status: storageHealth.writable ? "healthy" : "down",
    });
  }

  if (queueStatus) {
    services.push({
      name: "Queue",
      status: queueStatus.failed > 0 ? "degraded" : "healthy",
      detail: `${queueStatus.pending} pending${queueStatus.failed > 0 ? `, ${queueStatus.failed} failed` : ""}`,
    });
  }

  const allHealthy = services.length > 0 && services.every((s) => s.status === "healthy");

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "150ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium">System Health</CardTitle>
          {!isLoading && services.length > 0 && (
            <div className="flex items-center gap-1.5">
              <span
                className={cn(
                  "h-2 w-2 rounded-full",
                  allHealthy ? "bg-green-500" : "bg-amber-500"
                )}
              />
              <span className="text-xs text-muted-foreground">
                {allHealthy ? "All systems operational" : "Issues detected"}
              </span>
            </div>
          )}
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {isLoading ? (
          <div className="space-y-2.5">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-6 w-full" />
            ))}
          </div>
        ) : services.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            Unable to retrieve health data
          </p>
        ) : (
          <div className="space-y-2.5">
            {services.map((service) => {
              const styles = statusStyles[service.status];
              return (
                <div
                  key={service.name}
                  className="flex items-center justify-between text-sm"
                >
                  <div className="flex items-center gap-2.5">
                    <span
                      className={cn("h-2 w-2 rounded-full shrink-0", styles.dot)}
                    />
                    <span>{service.name}</span>
                  </div>
                  <div className="flex items-center gap-3">
                    {service.detail && (
                      <span className="text-xs text-muted-foreground">
                        {service.detail}
                      </span>
                    )}
                    <span className={cn("text-xs font-medium", styles.label)}>
                      {statusLabels[service.status]}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
