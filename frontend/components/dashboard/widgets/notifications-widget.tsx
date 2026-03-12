"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Bell, BellDot } from "lucide-react";
import { cn } from "@/lib/utils";

interface UnreadCountResponse {
  count: number;
}

interface Notification {
  id: string;
  type: string;
  read_at: string | null;
  created_at: string;
  data: Record<string, unknown>;
}

interface NotificationsResponse {
  data: Notification[];
  meta?: { total: number };
}

export function NotificationsWidget() {
  const { data: unreadData, isLoading: unreadLoading } = useQuery({
    queryKey: ["dashboard", "notifications-unread-count"],
    queryFn: async (): Promise<UnreadCountResponse> => {
      const res = await api.get<UnreadCountResponse>("/notifications/unread-count");
      return res.data;
    },
  });

  const { data: recentData, isLoading: recentLoading } = useQuery({
    queryKey: ["dashboard", "notifications-recent"],
    queryFn: async (): Promise<NotificationsResponse> => {
      const res = await api.get<NotificationsResponse>("/notifications?per_page=5");
      return res.data;
    },
  });

  const unreadCount = unreadData?.count ?? 0;
  const recent = recentData?.data ?? [];
  const totalCount = recentData?.meta?.total ?? 0;
  const isLoading = unreadLoading || recentLoading;

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "225ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium">Notifications</CardTitle>
          {unreadCount > 0 && (
            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-primary-foreground">
              {unreadCount}
            </span>
          )}
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {isLoading ? (
          <div className="space-y-2">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-10 w-full rounded-lg" />
            ))}
          </div>
        ) : recent.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            No notifications
          </p>
        ) : (
          <div className="space-y-2">
            <div className="grid grid-cols-2 gap-2">
              <div className="flex items-center gap-2.5 rounded-lg border p-2.5">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                  <BellDot className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                  <p className="text-lg font-bold tabular-nums leading-none">
                    {unreadCount}
                  </p>
                  <p className="text-[10px] text-muted-foreground mt-0.5">
                    Unread
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2.5 rounded-lg border p-2.5">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                  <Bell className={cn("h-4 w-4 text-muted-foreground")} />
                </div>
                <div>
                  <p className="text-lg font-bold tabular-nums leading-none">
                    {totalCount}
                  </p>
                  <p className="text-[10px] text-muted-foreground mt-0.5">
                    Total
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
