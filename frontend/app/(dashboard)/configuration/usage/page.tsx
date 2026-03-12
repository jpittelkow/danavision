"use client";

import { useCallback, useEffect, useState } from "react";
import { api } from "@/lib/api";
import { errorLogger } from "@/lib/error-logger";
import { formatCurrency } from "@/lib/utils";
import { toast } from "sonner";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AuditStatsCard } from "@/components/audit/audit-stats-card";
import { UsageCostChart } from "@/components/usage/usage-cost-chart";
import { UsageProviderTable } from "@/components/usage/usage-provider-table";
import {
  DollarSign,
  Brain,
  Mail,
  MessageSquare,
  HardDrive,
  Download,
  Radio,
} from "lucide-react";
import { HelpLink } from "@/components/help/help-link";

interface DailyData {
  date: string;
  llm: number;
  email: number;
  sms: number;
  storage: number;
  broadcasting: number;
}

interface ProviderData {
  provider: string;
  integration: string;
  total_cost: number;
  total_quantity: number;
}

interface UsageStats {
  summary: {
    total_estimated_cost: number;
    by_integration: Record<string, number>;
  };
  daily: DailyData[];
  by_provider: ProviderData[];
}

const ALL_INTEGRATIONS = ["llm", "email", "sms", "storage", "broadcasting"];

const INTEGRATION_LABELS: Record<string, string> = {
  llm: "LLM",
  email: "Email",
  sms: "SMS",
  storage: "Storage",
  broadcasting: "Broadcasting",
};

function formatDateDefault(daysAgo: number): string {
  const d = new Date();
  d.setDate(d.getDate() - daysAgo);
  return d.toISOString().slice(0, 10);
}

export default function UsagePage() {
  const [stats, setStats] = useState<UsageStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);

  // Filters
  const [dateFrom, setDateFrom] = useState(formatDateDefault(30));
  const [dateTo, setDateTo] = useState(formatDateDefault(0));
  const [visibleIntegrations, setVisibleIntegrations] = useState<Set<string>>(
    new Set(ALL_INTEGRATIONS)
  );

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      params.append("date_from", dateFrom);
      params.append("date_to", dateTo);

      const response = await api.get<UsageStats>(`/usage/stats?${params}`);
      setStats(response.data);
    } catch (err: unknown) {
      errorLogger.captureMessage("Failed to fetch usage stats", "error");
      toast.error("Failed to load usage data");
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo]);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const toggleIntegration = (integration: string) => {
    setVisibleIntegrations((prev) => {
      const next = new Set(prev);
      if (next.has(integration)) {
        // Don't allow deselecting all
        if (next.size > 1) next.delete(integration);
      } else {
        next.add(integration);
      }
      return next;
    });
  };

  const setPresetRange = (days: number) => {
    setDateFrom(formatDateDefault(days));
    setDateTo(formatDateDefault(0));
  };

  const setMonthRange = (monthsAgo: number) => {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth() - monthsAgo, 1);
    const end = monthsAgo === 0
      ? now
      : new Date(now.getFullYear(), now.getMonth() - monthsAgo + 1, 0);
    setDateFrom(start.toISOString().slice(0, 10));
    setDateTo(end.toISOString().slice(0, 10));
  };

  const handleExport = async () => {
    try {
      setExporting(true);
      const params = new URLSearchParams();
      params.append("date_from", dateFrom);
      params.append("date_to", dateTo);

      const response = await api.get(`/usage/export?${params}`, {
        responseType: "blob",
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.download = `usage-export-${dateFrom}-to-${dateTo}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success("Export downloaded");
    } catch (err: unknown) {
      errorLogger.captureMessage("Failed to export usage data", "error");
      toast.error("Failed to export usage data");
    } finally {
      setExporting(false);
    }
  };

  if (loading && !stats) {
    return <SettingsPageSkeleton />;
  }

  const summary = stats?.summary ?? {
    total_estimated_cost: 0,
    by_integration: {},
  };

  const isEmpty =
    stats?.daily.length === 0 && stats?.by_provider.length === 0;

  return (
    <div className="space-y-6">
      {/* Page Title */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Usage & Costs</h1>
        <p className="text-muted-foreground">
          Track and monitor costs across all paid integrations.{" "}
          <HelpLink articleId="usage-costs" />
        </p>
      </div>

      {/* Date Range & Presets */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Date Range</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div className="space-y-2">
                <Label htmlFor="date_from">From</Label>
                <Input
                  id="date_from"
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="date_to">To</Label>
                <Input
                  id="date_to"
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" onClick={() => setPresetRange(7)}>
                Last 7 days
              </Button>
              <Button variant="outline" size="sm" onClick={() => setPresetRange(30)}>
                Last 30 days
              </Button>
              <Button variant="outline" size="sm" onClick={() => setPresetRange(90)}>
                Last 90 days
              </Button>
              <Button variant="outline" size="sm" onClick={() => setMonthRange(0)}>
                This month
              </Button>
              <Button variant="outline" size="sm" onClick={() => setMonthRange(1)}>
                Last month
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {isEmpty ? (
        <Card>
          <CardContent className="flex min-h-[200px] items-center justify-center">
            <div className="text-center text-muted-foreground">
              <DollarSign className="mx-auto mb-3 h-10 w-10 opacity-40" />
              <p className="text-lg font-medium">No usage data yet</p>
              <p className="mt-1 text-sm">
                Usage tracking begins automatically when paid integrations (LLM, email, SMS, storage) are configured and used.
              </p>
            </div>
          </CardContent>
        </Card>
      ) : (
        <>
          {/* Summary Stat Cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <AuditStatsCard
              title="Total Cost"
              value={formatCurrency(summary.total_estimated_cost)}
              description="Selected period"
              icon={DollarSign}
              variant={summary.total_estimated_cost > 100 ? "warning" : "default"}
            />
            {visibleIntegrations.has("llm") && (
              <AuditStatsCard
                title="LLM"
                value={formatCurrency(summary.by_integration.llm ?? 0)}
                description="Token costs"
                icon={Brain}
              />
            )}
            {visibleIntegrations.has("email") && (
              <AuditStatsCard
                title="Email"
                value={formatCurrency(summary.by_integration.email ?? 0)}
                description="Message costs"
                icon={Mail}
              />
            )}
            {visibleIntegrations.has("sms") && (
              <AuditStatsCard
                title="SMS"
                value={formatCurrency(summary.by_integration.sms ?? 0)}
                description="Message costs"
                icon={MessageSquare}
              />
            )}
            {visibleIntegrations.has("storage") && (
              <AuditStatsCard
                title="Storage"
                value={formatCurrency(summary.by_integration.storage ?? 0)}
                description="Transfer costs"
                icon={HardDrive}
              />
            )}
            {visibleIntegrations.has("broadcasting") && (
              <AuditStatsCard
                title="Broadcasting"
                value={formatCurrency(summary.by_integration.broadcasting ?? 0)}
                description="Connection costs"
                icon={Radio}
              />
            )}
          </div>

          {/* Integration Filter Toggles */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">Cost Trend</CardTitle>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleExport}
                  disabled={exporting}
                >
                  <Download className="mr-1 h-4 w-4" />
                  {exporting ? "Exporting..." : "Export CSV"}
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className="mb-4 flex flex-wrap gap-2">
                {ALL_INTEGRATIONS.map((key) => (
                  <Button
                    key={key}
                    variant={visibleIntegrations.has(key) ? "default" : "outline"}
                    size="sm"
                    onClick={() => toggleIntegration(key)}
                  >
                    {INTEGRATION_LABELS[key]}
                  </Button>
                ))}
              </div>
              <UsageCostChart
                data={stats?.daily ?? []}
                visibleIntegrations={visibleIntegrations}
              />
            </CardContent>
          </Card>

          {/* Provider Breakdown Table */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Provider Breakdown</CardTitle>
            </CardHeader>
            <CardContent>
              <UsageProviderTable data={stats?.by_provider ?? []} />
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
