"use client";

import { useState, useRef, useEffect, useCallback } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { useAppLogStream, type AppLogEntry } from "@/lib/use-app-log-stream";
import { Download, Loader2, Radio, RefreshCw, Trash2, Wifi, WifiOff } from "lucide-react";
import { HelpLink } from "@/components/help/help-link";

const LEVEL_VARIANTS: Record<string, string> = {
  debug: "text-muted-foreground",
  info: "text-blue-600 dark:text-blue-400",
  notice: "text-sky-600 dark:text-sky-400",
  warning: "text-amber-600 dark:text-amber-400",
  error: "text-red-600 dark:text-red-400",
  critical: "text-red-700 dark:text-red-500",
};

function formatContext(ctx: Record<string, unknown>): string {
  const keys = Object.keys(ctx).filter(
    (k) => ctx[k] !== null && ctx[k] !== undefined && ctx[k] !== ""
  );
  if (keys.length === 0) return "";
  const pairs = keys.map((k) => `${k}=${JSON.stringify(ctx[k])}`);
  return ` ${pairs.join(" ")}`;
}

export default function ApplicationLogsPage() {
  const [liveEnabled, setLiveEnabled] = useState(false);
  const [levelFilter, setLevelFilter] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [autoScroll, setAutoScroll] = useState(true);
  const [historicalLogs, setHistoricalLogs] = useState<AppLogEntry[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [exportFilters, setExportFilters] = useState({
    date_from: "",
    date_to: "",
    level: "",
    correlation_id: "",
    format: "csv" as "csv" | "json",
  });
  const scrollRef = useRef<HTMLDivElement>(null);

  const { status, logs: liveLogs, clearLogs } = useAppLogStream(liveEnabled);

  const fetchHistoricalLogs = useCallback(async () => {
    setIsLoadingHistory(true);
    try {
      const params = new URLSearchParams();
      params.append("limit", "200");
      if (levelFilter !== "all") params.append("level", levelFilter);
      if (search) params.append("search", search);
      const res = await api.get(`/app-logs/recent?${params}`);
      const entries: AppLogEntry[] = (res.data.data ?? []).map(
        (e: { datetime: string; level: string; message: string; correlation_id: string | null }, i: number) => ({
          id: `hist-${i}-${Date.now()}`,
          level: e.level,
          message: e.message,
          context: {},
          correlation_id: e.correlation_id ?? null,
          user_id: null,
          timestamp: e.datetime,
        })
      );
      setHistoricalLogs(entries);
    } catch (error: unknown) {
      toast.error("Failed to load logs");
    } finally {
      setIsLoadingHistory(false);
    }
  }, [levelFilter, search]);

  // Load historical logs on mount
  useEffect(() => {
    fetchHistoricalLogs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Determine which logs to display: live logs take priority when live mode is on
  const displayLogs = liveEnabled && liveLogs.length > 0 ? liveLogs : historicalLogs;

  const filtered = displayLogs.filter((log) => {
    if (levelFilter !== "all" && log.level !== levelFilter) return false;
    if (
      search &&
      !log.message.toLowerCase().includes(search.toLowerCase()) &&
      !JSON.stringify(log.context).toLowerCase().includes(search.toLowerCase())
    ) {
      return false;
    }
    return true;
  });

  useEffect(() => {
    if (!autoScroll || !scrollRef.current) return;
    scrollRef.current.scrollTop = 0;
  }, [filtered.length, autoScroll]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
            Application Logs
          </h1>
          <p className="text-muted-foreground mt-1">
            Real-time console logs. Enable Live, set LOG_BROADCAST_ENABLED=true,
            add &quot;broadcast&quot; to LOG_STACK, and configure Reverb.{" "}
            <HelpLink articleId="application-logs" />
          </p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          <Button
            variant={liveEnabled ? "default" : "outline"}
            size="sm"
            onClick={() => setLiveEnabled((v) => !v)}
            title={
              status === "unavailable"
                ? "Real-time requires Reverb configuration"
                : liveEnabled
                  ? "Turn off live updates"
                  : "Stream logs in real time"
            }
          >
            <Radio
              className={`mr-2 h-4 w-4 ${liveEnabled ? "animate-pulse" : ""}`}
            />
            Live
          </Button>
          {liveEnabled && (
            <span
              className="flex items-center gap-1.5 text-xs text-muted-foreground"
              title={
                status === "connected"
                  ? "Connected to real-time stream"
                  : status === "unavailable"
                    ? "Reverb not configured or auth failed"
                    : "Connecting…"
              }
            >
              {status === "connected" ? (
                <Wifi className="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
              ) : status === "unavailable" ? (
                <WifiOff className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
              ) : (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              )}
              {status === "connected"
                ? "Live"
                : status === "unavailable"
                  ? "Unavailable"
                  : "Connecting…"}
            </span>
          )}
          <Button
            variant="outline"
            size="sm"
            onClick={fetchHistoricalLogs}
            disabled={isLoadingHistory}
            title="Refresh historical logs"
          >
            {isLoadingHistory ? (
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            ) : (
              <RefreshCw className="mr-2 h-4 w-4" />
            )}
            Refresh
          </Button>
          <Button variant="outline" size="sm" onClick={() => { clearLogs(); setHistoricalLogs([]); }}>
            <Trash2 className="mr-2 h-4 w-4" />
            Clear
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Export</CardTitle>
          <CardDescription>
            Export application log files by date range, level, and correlation ID
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div className="space-y-2">
              <Label htmlFor="export_date_from">From date</Label>
              <Input
                id="export_date_from"
                type="date"
                value={exportFilters.date_from}
                onChange={(e) =>
                  setExportFilters({ ...exportFilters, date_from: e.target.value })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="export_date_to">To date</Label>
              <Input
                id="export_date_to"
                type="date"
                value={exportFilters.date_to}
                onChange={(e) =>
                  setExportFilters({ ...exportFilters, date_to: e.target.value })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="export_level">Level</Label>
              <Select
                value={exportFilters.level || "all"}
                onValueChange={(v) =>
                  setExportFilters({ ...exportFilters, level: v === "all" ? "" : v })
                }
              >
                <SelectTrigger id="export_level">
                  <SelectValue placeholder="All" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="debug">Debug</SelectItem>
                  <SelectItem value="info">Info</SelectItem>
                  <SelectItem value="notice">Notice</SelectItem>
                  <SelectItem value="warning">Warning</SelectItem>
                  <SelectItem value="error">Error</SelectItem>
                  <SelectItem value="critical">Critical</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="export_correlation_id">Correlation ID</Label>
              <Input
                id="export_correlation_id"
                placeholder="Trace ID"
                value={exportFilters.correlation_id}
                onChange={(e) =>
                  setExportFilters({ ...exportFilters, correlation_id: e.target.value })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="export_format">Format</Label>
              <Select
                value={exportFilters.format}
                onValueChange={(v: "csv" | "json") =>
                  setExportFilters({ ...exportFilters, format: v })
                }
              >
                <SelectTrigger id="export_format">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="csv">CSV</SelectItem>
                  <SelectItem value="json">JSON Lines</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2 flex flex-col justify-end">
              <Button
                variant="secondary"
                onClick={async () => {
                  setIsExporting(true);
                  try {
                    const params = new URLSearchParams();
                    if (exportFilters.date_from) params.append("date_from", exportFilters.date_from);
                    if (exportFilters.date_to) params.append("date_to", exportFilters.date_to);
                    if (exportFilters.level) params.append("level", exportFilters.level);
                    if (exportFilters.correlation_id) params.append("correlation_id", exportFilters.correlation_id);
                    params.append("format", exportFilters.format);
                    const response = await api.get(`/app-logs/export?${params}`, {
                      responseType: "blob",
                    });
                    const url = window.URL.createObjectURL(new Blob([response.data]));
                    const link = document.createElement("a");
                    link.href = url;
                    link.setAttribute(
                      "download",
                      `app_logs_${new Date().toISOString().split("T")[0]}.${exportFilters.format === "csv" ? "csv" : "jsonl"}`
                    );
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.URL.revokeObjectURL(url);
                    toast.success("Application logs exported");
                  } catch (error: unknown) {
                    toast.error(
                      error instanceof Error ? error.message : "Failed to export application logs"
                    );
                  } finally {
                    setIsExporting(false);
                  }
                }}
                disabled={isExporting}
              >
                {isExporting ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Download className="mr-2 h-4 w-4" />
                )}
                Export
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>
            Filter displayed logs by level and search text
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-2">
              <Label htmlFor="level">Level</Label>
              <Select
                value={levelFilter}
                onValueChange={setLevelFilter}
              >
                <SelectTrigger id="level">
                  <SelectValue placeholder="All" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="debug">Debug</SelectItem>
                  <SelectItem value="info">Info</SelectItem>
                  <SelectItem value="notice">Notice</SelectItem>
                  <SelectItem value="warning">Warning</SelectItem>
                  <SelectItem value="error">Error</SelectItem>
                  <SelectItem value="critical">Critical</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="search">Search</Label>
              <Input
                id="search"
                placeholder="Filter by message or context"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <div className="space-y-2 flex flex-col justify-end">
              <label htmlFor="auto-scroll" className="flex items-center gap-2 cursor-pointer">
                <Checkbox
                  id="auto-scroll"
                  checked={autoScroll}
                  onCheckedChange={(checked) => setAutoScroll(!!checked)}
                />
                <span className="text-sm">Auto-scroll</span>
              </label>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Log output</CardTitle>
          <CardDescription>
            {filtered.length} line{filtered.length !== 1 ? "s" : ""}{liveEnabled ? " (max 500 when live)" : " (most recent 200)"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div
            ref={scrollRef}
            className="rounded-md bg-zinc-950 text-zinc-100 font-mono text-xs overflow-auto max-h-[60vh] min-h-[200px] p-4"
          >
            {isLoadingHistory ? (
              <p className="text-zinc-500">Loading logs…</p>
            ) : filtered.length === 0 ? (
              <p className="text-zinc-500">
                {liveEnabled
                  ? "No logs yet. Trigger some activity (e.g. API calls) to see output."
                  : "No log entries found. Click Refresh to reload, or enable Live for real-time streaming."}
              </p>
            ) : (
              <div className="space-y-0.5">
                {filtered.map((log) => (
                  <LogLine key={log.id} log={log} />
                ))}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function LogLine({ log }: { log: AppLogEntry }) {
  const levelClass =
    LEVEL_VARIANTS[log.level] ?? "text-zinc-400";
  const ts = new Date(log.timestamp).toLocaleTimeString(undefined, {
    hour12: false,
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
  const ctx = formatContext(log.context);

  return (
    <div className="leading-relaxed break-all">
      <span className="text-zinc-500 shrink-0">{ts}</span>{" "}
      <span className={`shrink-0 ${levelClass}`}>
        [{log.level.toUpperCase()}]
      </span>{" "}
      <span className="text-zinc-200">{log.message}</span>
      {ctx && <span className="text-zinc-500">{ctx}</span>}
    </div>
  );
}
