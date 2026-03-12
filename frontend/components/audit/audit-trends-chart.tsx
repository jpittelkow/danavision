"use client";

import {
  Area,
  AreaChart,
  CartesianGrid,
  XAxis,
  YAxis,
} from "recharts";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart";
import type { ChartConfig } from "@/components/ui/chart";

const TRENDS_CONFIG: ChartConfig = {
  count: { label: "Actions", color: "hsl(217 91% 60%)" },
};

export interface AuditTrendsChartProps {
  /** Map of date (Y-m-d) -> count from stats API daily_trends */
  dailyTrends: Record<string, number>;
  className?: string;
}

/** Parse Y-m-d as local date to avoid timezone shifts. */
function parseLocalDate(dateStr: string): Date {
  const [y, m, d] = dateStr.split("-").map(Number);
  return new Date(y, m - 1, d);
}

function formatChartDate(dateStr: string) {
  return parseLocalDate(dateStr).toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });
}

export function AuditTrendsChart({
  dailyTrends,
  className,
}: AuditTrendsChartProps) {
  const chartData = Object.entries(dailyTrends)
    .map(([date, count]) => ({ date, count }))
    .sort((a, b) => a.date.localeCompare(b.date));

  if (chartData.length === 0) {
    return (
      <div
        className={`flex min-h-[200px] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-sm text-muted-foreground ${className ?? ""}`}
      >
        No activity in range
      </div>
    );
  }

  return (
    <ChartContainer
      config={TRENDS_CONFIG}
      className={`min-h-[200px] w-full ${className ?? ""}`}
    >
      <AreaChart data={chartData} accessibilityLayer>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis
          dataKey="date"
          tickLine={false}
          tickMargin={8}
          axisLine={false}
          tickFormatter={formatChartDate}
        />
        <YAxis tickLine={false} axisLine={false} tickMargin={8} />
        <ChartTooltip content={<ChartTooltipContent />} />
        <Area
          type="monotone"
          dataKey="count"
          stroke="var(--color-count)"
          fill="var(--color-count)"
          fillOpacity={0.2}
          strokeWidth={2}
        />
      </AreaChart>
    </ChartContainer>
  );
}
