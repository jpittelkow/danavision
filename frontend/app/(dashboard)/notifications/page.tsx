"use client";

import { useState, useEffect, useCallback } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { useNotifications } from "@/lib/notifications";
import { useOnline } from "@/lib/use-online";
import { OfflineBadge } from "@/components/offline-badge";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { NotificationList } from "@/components/notifications/notification-list";
import { NotificationItem } from "@/components/notifications/notification-item";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { CheckCheck, Trash2 } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";
import type { AppNotification } from "@/lib/notifications";
import { cn } from "@/lib/utils";
import { getAllCategories } from "@/lib/notification-types";

const PER_PAGE = 20;

function getDateBucket(dateStr: string): string {
  const date = new Date(dateStr);
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  const weekAgo = new Date(today);
  weekAgo.setDate(weekAgo.getDate() - 6);

  if (date >= today) return "Today";
  if (date >= yesterday) return "Yesterday";
  if (date >= weekAgo) return "Earlier this week";
  return "Older";
}

function groupByDate(notifications: AppNotification[]): { label: string; items: AppNotification[] }[] {
  const bucketOrder = ["Today", "Yesterday", "Earlier this week", "Older"];
  const groups = new Map<string, AppNotification[]>();
  for (const n of notifications) {
    const bucket = getDateBucket(n.created_at);
    if (!groups.has(bucket)) groups.set(bucket, []);
    groups.get(bucket)!.push(n);
  }
  return bucketOrder
    .filter((label) => groups.has(label))
    .map((label) => ({ label, items: groups.get(label)! }));
}

interface PaginatedResponse {
  data: AppNotification[];
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export default function NotificationsPage() {
  const {
    markAsRead,
    markAllAsRead,
    deleteNotification,
    fetchNotifications: refetchContext,
    fetchUnreadCount,
  } = useNotifications();
  const { isOffline } = useOnline();

  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [category, setCategory] = useState<string>("all");
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [deleting, setDeleting] = useState(false);

  const lastPage = Math.ceil(total / PER_PAGE) || 1;
  const hasUnread = notifications.some((n) => !n.read_at);
  const hasSelection = selectedIds.size > 0;

  const fetch = useCallback(
    async (p: number, unreadOnly: boolean, cat: string) => {
      setIsLoading(true);
      try {
        const params: Record<string, unknown> = {
          page: p,
          per_page: PER_PAGE,
          unread: unreadOnly ? 1 : 0,
        };
        if (cat !== "all") {
          params.category = cat;
        }
        const { data } = await api.get<PaginatedResponse>("/notifications", {
          params,
        });
        setNotifications(data.data ?? []);
        setTotal(data.total ?? 0);
        setPage(data.current_page ?? 1);
      } catch {
        toast.error("Failed to load notifications");
        setNotifications([]);
        setTotal(0);
      } finally {
        setIsLoading(false);
      }
    },
    []
  );

  useEffect(() => {
    fetch(1, filter === "unread", category);
  }, [filter, category, fetch]);

  const goToPage = (p: number) => {
    if (p < 1 || p > lastPage) return;
    fetch(p, filter === "unread", category);
    setSelectedIds(new Set());
  };

  const handleMarkAllRead = async () => {
    try {
      await markAllAsRead();
      await fetchUnreadCount();
      fetch(page, filter === "unread", category);
      toast.success("All notifications marked as read");
    } catch {
      toast.error("Failed to mark all as read");
    }
  };

  const handleMarkRead = async (ids: string[]) => {
    try {
      await markAsRead(ids);
      await fetchUnreadCount();
      setNotifications((prev) =>
        prev.map((n) =>
          ids.includes(n.id)
            ? { ...n, read_at: n.read_at ?? new Date().toISOString() }
            : n
        )
      );
      setSelectedIds((s) => {
        const next = new Set(s);
        ids.forEach((id) => next.delete(id));
        return next;
      });
    } catch {
      toast.error("Failed to mark as read");
    }
  };

  const handleDeleteSelected = async () => {
    if (!hasSelection) return;
    setDeleting(true);
    try {
      await api.post("/notifications/delete-batch", {
        ids: Array.from(selectedIds),
      });
      setSelectedIds(new Set());
      await fetchUnreadCount();
      await refetchContext();
      await fetch(1, filter === "unread", category);
      toast.success("Selected notifications deleted");
    } catch {
      toast.error("Failed to delete notifications");
    } finally {
      setDeleting(false);
    }
  };

  const toggleSelect = (id: string) => {
    setSelectedIds((s) => {
      const next = new Set(s);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === notifications.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(notifications.map((n) => n.id)));
    }
  };

  return (
    <div className="container py-6 md:py-8">
      <div className="mb-6 flex items-center gap-2 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Notifications</h1>
          <p className="text-muted-foreground mt-1">
            {isOffline
              ? "You're offline. Notifications are read-only; actions will sync when back online."
              : "View and manage your notifications."}
          </p>
        </div>
        <OfflineBadge />
      </div>

      <Tabs
        value={filter}
        onValueChange={(v) => setFilter(v as "all" | "unread")}
      >
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="unread">Unread</TabsTrigger>
            </TabsList>
            <Select value={category} onValueChange={setCategory}>
              <SelectTrigger className="w-[160px] h-9">
                <SelectValue placeholder="All categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All categories</SelectItem>
                {getAllCategories().map((cat) => (
                  <SelectItem key={cat.value} value={cat.value}>
                    {cat.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {hasUnread && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleMarkAllRead}
                disabled={isLoading || isOffline}
              >
                <CheckCheck className="mr-2 h-4 w-4" />
                Mark all read
              </Button>
            )}
            {hasSelection && (
              <Button
                variant="destructive"
                size="sm"
                onClick={handleDeleteSelected}
                disabled={deleting || isOffline}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete selected ({selectedIds.size})
              </Button>
            )}
          </div>
        </div>

        <TabsContent value="all" className="mt-4">
          <NotificationsContent
            notifications={notifications}
            isLoading={isLoading}
            onMarkRead={handleMarkRead}
            selectedIds={selectedIds}
            onToggleSelect={toggleSelect}
            onToggleSelectAll={toggleSelectAll}
            emptyMessage="No notifications yet."
          />
        </TabsContent>
        <TabsContent value="unread" className="mt-4">
          <NotificationsContent
            notifications={notifications}
            isLoading={isLoading}
            onMarkRead={handleMarkRead}
            selectedIds={selectedIds}
            onToggleSelect={toggleSelect}
            onToggleSelectAll={toggleSelectAll}
            emptyMessage="No unread notifications."
          />
        </TabsContent>
      </Tabs>

      {!isLoading && total > 0 && (
        <div className="mt-6 flex flex-col items-center gap-2">
          <span className="text-sm text-muted-foreground">
            Showing {Math.min((page - 1) * PER_PAGE + 1, total)}&ndash;{Math.min(page * PER_PAGE, total)} of {total} notifications
          </span>
          {lastPage > 1 && (
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => goToPage(page - 1)}
                disabled={page <= 1}
              >
                Previous
              </Button>
              <span className="text-sm text-muted-foreground px-2">
                Page {page} of {lastPage}
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => goToPage(page + 1)}
                disabled={page >= lastPage}
              >
                Next
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

interface NotificationsContentProps {
  notifications: AppNotification[];
  isLoading: boolean;
  onMarkRead: (ids: string[]) => void;
  selectedIds: Set<string>;
  onToggleSelect: (id: string) => void;
  onToggleSelectAll: () => void;
  emptyMessage: string;
}

function NotificationsContent({
  notifications,
  isLoading,
  onMarkRead,
  selectedIds,
  onToggleSelect,
  onToggleSelectAll,
  emptyMessage,
}: NotificationsContentProps) {
  if (isLoading) {
    return (
      <NotificationList
        notifications={[]}
        isLoading
        emptyMessage={emptyMessage}
        compact={false}
      />
    );
  }

  if (notifications.length === 0) {
    return (
      <NotificationList
        notifications={[]}
        emptyMessage={emptyMessage}
        compact={false}
      />
    );
  }

  const dateGroups = groupByDate(notifications);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Checkbox
          id="select-all"
          checked={notifications.every((n) => selectedIds.has(n.id))}
          onCheckedChange={() => onToggleSelectAll()}
        />
        <label htmlFor="select-all" className="text-sm text-muted-foreground cursor-pointer">
          Select all on page
        </label>
      </div>
      <div className="space-y-4">
        {dateGroups.map((group) => (
          <div key={group.label} className="space-y-2">
            <h3 className="text-sm font-medium text-muted-foreground py-1">
              {group.label}
            </h3>
            {group.items.map((n) => (
              <div key={n.id} className="flex items-start gap-3">
                <Checkbox
                  checked={selectedIds.has(n.id)}
                  onCheckedChange={() => onToggleSelect(n.id)}
                  className="mt-4 shrink-0"
                  aria-label={`Select notification: ${n.title}`}
                />
                <div className="flex-1 min-w-0">
                  <NotificationItem
                    notification={n}
                    compact={false}
                    showMarkRead
                    onMarkRead={(id) => onMarkRead([id])}
                  />
                </div>
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
