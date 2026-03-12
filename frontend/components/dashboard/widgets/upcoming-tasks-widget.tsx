"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Clock } from "lucide-react";

interface ScheduledTask {
  command: string;
  schedule: string;
  description: string;
  next_run: string | null;
  last_run: string | null;
  triggerable: boolean;
  dangerous: boolean;
}

interface ScheduledResponse {
  data: {
    tasks: ScheduledTask[];
  };
}

export function UpcomingTasksWidget() {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", "upcoming-tasks"],
    queryFn: async (): Promise<ScheduledResponse> => {
      const res = await api.get<ScheduledResponse>("/jobs/scheduled");
      return res.data;
    },
  });

  const tasks = data?.data?.tasks ?? [];

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "300ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 text-muted-foreground" />
          <CardTitle className="text-sm font-medium">
            Scheduled Tasks
          </CardTitle>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {isLoading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="flex items-center gap-3">
                <Skeleton className="h-8 w-8 rounded-lg" />
                <div className="flex-1 space-y-1">
                  <Skeleton className="h-4 w-2/3" />
                  <Skeleton className="h-3 w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : tasks.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            No scheduled tasks
          </p>
        ) : (
          <div className="space-y-3">
            {tasks.slice(0, 6).map((task) => (
              <div
                key={task.command}
                className="flex items-center gap-3 text-sm"
              >
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                  <Clock className="h-4 w-4 text-muted-foreground" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-medium truncate">
                    {task.description || task.command}
                  </p>
                  <p className="text-xs text-muted-foreground truncate">
                    {task.schedule}
                  </p>
                </div>
                <Badge
                  variant={task.triggerable ? "default" : "secondary"}
                  className="text-[10px] shrink-0"
                >
                  {task.triggerable ? "Triggerable" : "Scheduled"}
                </Badge>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
