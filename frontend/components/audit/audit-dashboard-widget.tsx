"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Activity, AlertTriangle, ExternalLink } from "lucide-react";
import { AuditStatsCard } from "./audit-stats-card";
import { AuditSeverityChart } from "./audit-severity-chart";
import { AuditTrendsChart } from "./audit-trends-chart";

interface AuditStats {
  total_actions: number;
  by_severity: Record<string, number>;
  daily_trends: Record<string, number>;
  recent_warnings: Array<{
    id: number;
    action: string;
    severity: string;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
  }>;
}

function dateRange() {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 30);
  return {
    date_from: from.toISOString().slice(0, 10),
    date_to: to.toISOString().slice(0, 10),
  };
}

export function AuditDashboardWidget() {
  const [stats, setStats] = useState<AuditStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(() => {
    setLoading(true);
    setError(null);
    const { date_from, date_to } = dateRange();
    api
      .get<AuditStats>("/audit-logs/stats", { params: { date_from, date_to } })
      .then((r) => {
        setStats(r.data);
        setError(null);
      })
      .catch((e) =>
        setError(e instanceof Error ? e.message : "Failed to load stats")
      )
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Skeleton className="h-[120px] rounded-lg" />
          <Skeleton className="h-[120px] rounded-lg" />
          <Skeleton className="h-[120px] rounded-lg" />
        </div>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Skeleton className="h-[240px] rounded-lg" />
          <Skeleton className="h-[240px] rounded-lg" />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <Card className="border-destructive/50">
        <CardContent className="flex flex-col items-center justify-center gap-3 py-12">
          <p className="text-sm text-muted-foreground">{error}</p>
          <Button variant="outline" size="sm" onClick={fetchStats}>
            Retry
          </Button>
        </CardContent>
      </Card>
    );
  }

  if (!stats) return null;

  const warningsCount =
    (stats.by_severity?.warning ?? 0) +
    (stats.by_severity?.error ?? 0) +
    (stats.by_severity?.critical ?? 0);

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <AuditStatsCard
          title="Total actions"
          value={stats.total_actions.toLocaleString()}
          description="Last 30 days"
          icon={Activity}
          variant="default"
        />
        <AuditStatsCard
          title="Warnings & errors"
          value={warningsCount.toLocaleString()}
          description="Requires attention"
          icon={AlertTriangle}
          variant={warningsCount > 0 ? "warning" : "default"}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Activity trend</CardTitle>
            <p className="text-sm text-muted-foreground">
              Daily audit events (last 30 days)
            </p>
          </CardHeader>
          <CardContent>
            <AuditTrendsChart dailyTrends={stats.daily_trends ?? {}} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">By severity</CardTitle>
            <p className="text-sm text-muted-foreground">
              Distribution of log severity
            </p>
          </CardHeader>
          <CardContent>
            <AuditSeverityChart bySeverity={stats.by_severity ?? {}} />
          </CardContent>
        </Card>
      </div>

      {Array.isArray(stats.recent_warnings) &&
        stats.recent_warnings.length > 0 && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-base">Recent warnings</CardTitle>
                <p className="text-sm text-muted-foreground">
                  Latest warning, error, or critical events
                </p>
              </div>
              <Button variant="outline" size="sm" asChild>
                <Link href="/configuration/audit">
                  View all logs
                  <ExternalLink className="ml-2 h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent>
              <ul className="space-y-2 text-sm">
                {stats.recent_warnings.map((w) => (
                  <li
                    key={w.id}
                    className="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-md border bg-muted/30 px-3 py-2"
                  >
                    <span
                      className={`font-medium capitalize ${
                        w.severity === "critical" || w.severity === "error"
                          ? "text-destructive"
                          : "text-amber-600 dark:text-amber-400"
                      }`}
                    >
                      {w.severity}
                    </span>
                    <span className="text-muted-foreground">{w.action}</span>
                    {w.user && (
                      <span className="text-muted-foreground">
                        — {w.user.name} ({w.user.email})
                      </span>
                    )}
                    <span className="ml-auto text-muted-foreground">
                      {new Date(w.created_at).toLocaleString()}
                    </span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        )}

      <div className="flex justify-end">
        <Button variant="outline" asChild>
          <Link href="/configuration/audit">
            View all logs
            <ExternalLink className="ml-2 h-4 w-4" />
          </Link>
        </Button>
      </div>
    </div>
  );
}
