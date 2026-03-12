"use client";

import Link from "next/link";
import { Pie, PieChart, Cell } from "recharts";
import { formatBytes } from "@/lib/utils";
import { isAdminUser } from "@/lib/auth";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import type { ChartConfig } from "@/components/ui/chart";
import { Loader2, BarChart3 } from "lucide-react";

interface StorageAnalytics {
  driver: string;
  by_type?: Record<string, number>;
  top_files?: Array<{
    path: string;
    name: string;
    size: number;
    size_formatted: string;
    lastModified: number;
    lastModifiedFormatted: string;
  }>;
  recent_files?: Array<{
    path: string;
    name: string;
    size: number;
    size_formatted: string;
    lastModified: number;
    lastModifiedFormatted: string;
  }>;
  note?: string;
}

const CHART_COLORS = [
  "hsl(217 91% 60%)",
  "hsl(38 92% 50%)",
  "hsl(142 71% 45%)",
  "hsl(262 83% 58%)",
  "hsl(0 84% 60%)",
  "hsl(173 58% 39%)",
  "hsl(27 96% 61%)",
  "hsl(199 89% 48%)",
];

function StorageByTypeChart({ byType }: { byType: Record<string, number> }) {
  const entries = Object.entries(byType)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 8);
  const total = entries.reduce((acc, [, v]) => acc + v, 0);
  if (total === 0 || entries.length === 0) {
    return (
      <div className="flex min-h-[200px] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-sm text-muted-foreground">
        No file type data
      </div>
    );
  }
  const chartConfig: ChartConfig = Object.fromEntries(
    entries.map(([ext], i) => [
      ext,
      { label: ext === "none" ? "(no ext)" : `.${ext}`, color: CHART_COLORS[i % CHART_COLORS.length] },
    ])
  );
  const chartData = entries.map(([name, value]) => ({
    name,
    value: Math.round((value / total) * 1000) / 10,
    size: value,
    fill: `var(--color-${name})`,
  }));
  return (
    <ChartContainer config={chartConfig} className="min-h-[200px] w-full max-w-[300px]">
      <PieChart accessibilityLayer>
        <ChartTooltip
          content={
            <ChartTooltipContent
              nameKey="name"
              formatter={(value, _name, item) => {
                const payload = (item as { payload?: { name: string; size: number } })?.payload;
                const ext = payload?.name ?? "";
                const size = payload?.size ?? 0;
                return (
                  <span>
                    {ext === "none" ? "(no ext)" : `.${ext}`}: {formatBytes(size)} ({value}%)
                  </span>
                );
              }}
            />
          }
        />
        <Pie
          data={chartData}
          dataKey="value"
          nameKey="name"
          innerRadius={50}
          outerRadius={70}
          paddingAngle={2}
          strokeWidth={1}
          stroke="hsl(var(--background))"
        >
          {chartData.map((entry, index) => (
            <Cell key={`cell-${index}`} fill={entry.fill} />
          ))}
        </Pie>
      </PieChart>
    </ChartContainer>
  );
}

interface StorageAnalyticsCardProps {
  analytics: StorageAnalytics;
  isLoading: boolean;
  user: Parameters<typeof isAdminUser>[0];
}

export function StorageAnalyticsCard({ analytics, isLoading, user }: StorageAnalyticsCardProps) {
  if (analytics.driver !== "local") return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <BarChart3 className="h-5 w-5" />
          Storage Analytics
        </CardTitle>
        <CardDescription>
          File type breakdown and largest files
        </CardDescription>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : analytics.note ? (
          <p className="text-sm text-muted-foreground py-4">{analytics.note}</p>
        ) : (
          <div className="space-y-6">
            {analytics.by_type && Object.keys(analytics.by_type).length > 0 && (
              <div>
                <div className="text-sm font-medium text-muted-foreground mb-3">Storage by file type</div>
                <StorageByTypeChart byType={analytics.by_type} />
              </div>
            )}
            {analytics.top_files && analytics.top_files.length > 0 && (
              <div>
                <div className="text-sm font-medium text-muted-foreground mb-3">Top 10 largest files</div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Path</TableHead>
                      <TableHead className="text-right">Size</TableHead>
                      <TableHead>Modified</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {analytics.top_files.map((f, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-medium">{f.name}</TableCell>
                        <TableCell>
                          {isAdminUser(user) ? (
                            <Link
                              href={`/configuration/storage/files?path=${encodeURIComponent(f.path)}`}
                              className="text-primary hover:underline truncate block max-w-[200px]"
                            >
                              {f.path}
                            </Link>
                          ) : (
                            <span className="truncate block max-w-[200px] text-muted-foreground">{f.path}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">{f.size_formatted}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">{f.lastModifiedFormatted}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
            {analytics.recent_files && analytics.recent_files.length > 0 && (
              <div>
                <div className="text-sm font-medium text-muted-foreground mb-3">Recently modified</div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Path</TableHead>
                      <TableHead className="text-right">Size</TableHead>
                      <TableHead>Modified</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {analytics.recent_files.map((f, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-medium">{f.name}</TableCell>
                        <TableCell>
                          {isAdminUser(user) ? (
                            <Link
                              href={`/configuration/storage/files?path=${encodeURIComponent(f.path)}`}
                              className="text-primary hover:underline truncate block max-w-[200px]"
                            >
                              {f.path}
                            </Link>
                          ) : (
                            <span className="truncate block max-w-[200px] text-muted-foreground">{f.path}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">{f.size_formatted}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">{f.lastModifiedFormatted}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
            {(!analytics.by_type || Object.keys(analytics.by_type).length === 0) &&
              (!analytics.top_files || analytics.top_files.length === 0) &&
              (!analytics.recent_files || analytics.recent_files.length === 0) && (
              <p className="text-sm text-muted-foreground py-4">No analytics data available.</p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
