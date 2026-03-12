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
  ChartLegend,
  ChartLegendContent,
} from "@/components/ui/chart";
import type { ChartConfig } from "@/components/ui/chart";
import { formatCurrency } from "@/lib/utils";

const INTEGRATION_COLORS: Record<string, string> = {
  llm: "hsl(217 91% 60%)",
  email: "hsl(142 71% 45%)",
  sms: "hsl(38 92% 50%)",
  storage: "hsl(280 68% 60%)",
  broadcasting: "hsl(351 84% 60%)",
};

const COST_CHART_CONFIG: ChartConfig = {
  llm: { label: "LLM", color: INTEGRATION_COLORS.llm },
  email: { label: "Email", color: INTEGRATION_COLORS.email },
  sms: { label: "SMS", color: INTEGRATION_COLORS.sms },
  storage: { label: "Storage", color: INTEGRATION_COLORS.storage },
  broadcasting: { label: "Broadcasting", color: INTEGRATION_COLORS.broadcasting },
};

interface DailyData {
  date: string;
  llm: number;
  email: number;
  sms: number;
  storage: number;
  broadcasting: number;
}

interface UsageCostChartProps {
  data: DailyData[];
  visibleIntegrations: Set<string>;
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

export function UsageCostChart({
  data,
  visibleIntegrations,
  className,
}: UsageCostChartProps) {
  if (data.length === 0) {
    return (
      <div
        className={`flex min-h-[200px] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-sm text-muted-foreground ${className ?? ""}`}
      >
        No usage data in selected range
      </div>
    );
  }

  const integrations = ["llm", "email", "sms", "storage", "broadcasting"].filter(
    (i) => visibleIntegrations.has(i)
  );

  return (
    <ChartContainer
      config={COST_CHART_CONFIG}
      className={`min-h-[300px] w-full ${className ?? ""}`}
    >
      <AreaChart data={data} accessibilityLayer>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis
          dataKey="date"
          tickLine={false}
          tickMargin={8}
          axisLine={false}
          tickFormatter={formatChartDate}
        />
        <YAxis
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          tickFormatter={(v) => formatCurrency(Number(v))}
        />
        <ChartTooltip
          content={
            <ChartTooltipContent
              formatter={(value) => formatCurrency(Number(value))}
            />
          }
        />
        <ChartLegend content={<ChartLegendContent />} />
        {integrations.map((key) => (
          <Area
            key={key}
            type="monotone"
            dataKey={key}
            stackId="cost"
            stroke={`var(--color-${key})`}
            fill={`var(--color-${key})`}
            fillOpacity={0.3}
            strokeWidth={2}
          />
        ))}
      </AreaChart>
    </ChartContainer>
  );
}
