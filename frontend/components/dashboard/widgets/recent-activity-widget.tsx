"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import {
  LogIn,
  Settings,
  Upload,
  UserPlus,
  Shield,
  Activity,
  type LucideIcon,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface AuditLogEntry {
  id: number;
  action: string;
  severity?: string;
  created_at: string;
  user?: {
    name: string;
    email: string;
  };
}

interface AuditLogsResponse {
  data: AuditLogEntry[];
}

const actionIcons: Record<string, LucideIcon> = {
  "auth.login": LogIn,
  "auth.register": UserPlus,
  "user.created": UserPlus,
  "file.uploaded": Upload,
  "settings.updated": Settings,
  "security.": Shield,
};

const severityStyles: Record<string, string> = {
  info: "bg-blue-500/10 text-blue-600 dark:text-blue-400",
  warning: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  error: "bg-red-500/10 text-red-600 dark:text-red-400",
  critical: "bg-red-700/10 text-red-700 dark:text-red-500",
};

function getIconForAction(action: string): LucideIcon {
  for (const [key, icon] of Object.entries(actionIcons)) {
    if (action.startsWith(key)) return icon;
  }
  return Activity;
}

function formatAction(action: string): string {
  return action.replace(/\./g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

export function RecentActivityWidget() {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", "recent-activity"],
    queryFn: async (): Promise<AuditLogsResponse> => {
      const res = await api.get<AuditLogsResponse>("/audit-logs?per_page=5");
      return res.data;
    },
  });

  const activities = data?.data ?? [];

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2 md:col-span-2"
      style={{ animationDelay: "75ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <CardTitle className="text-sm font-medium">Recent Activity</CardTitle>
      </CardHeader>
      <CardContent className="pt-0">
        {isLoading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex items-center gap-3">
                <Skeleton className="h-8 w-8 rounded-full" />
                <div className="flex-1 space-y-1">
                  <Skeleton className="h-4 w-3/4" />
                  <Skeleton className="h-3 w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : activities.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            No recent activity
          </p>
        ) : (
          <div className="space-y-3">
            {activities.map((entry) => {
              const Icon = getIconForAction(entry.action);
              const severity = entry.severity || "info";
              return (
                <div
                  key={entry.id}
                  className="flex items-center gap-3 text-sm"
                >
                  <div
                    className={cn(
                      "flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
                      severityStyles[severity] || severityStyles.info
                    )}
                  >
                    <Icon className="h-4 w-4" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="font-medium truncate">
                      {formatAction(entry.action)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {entry.user?.email ?? "System"}
                    </p>
                  </div>
                  <span className="shrink-0 text-xs text-muted-foreground">
                    {timeAgo(entry.created_at)}
                  </span>
                </div>
              );
            })}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
