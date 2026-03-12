"use client";

import { Pie, PieChart, Cell } from "recharts";
import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import type { ChartConfig } from "@/components/ui/chart";

const SEVERITY_CONFIG: ChartConfig = {
  info: { label: "Info", color: "hsl(217 91% 60%)" },
  warning: { label: "Warning", color: "hsl(38 92% 50%)" },
  error: { label: "Error", color: "hsl(0 84% 60%)" },
  critical: { label: "Critical", color: "hsl(262 83% 58%)" },
};

const SEVERITY_ORDER = ["info", "warning", "error", "critical"] as const;

export interface AuditSeverityChartProps {
  /** Map of severity -> count from stats API by_severity */
  bySeverity: Record<string, number>;
  className?: string;
}

export function AuditSeverityChart({ bySeverity, className }: AuditSeverityChartProps) {
  const chartData = SEVERITY_ORDER.filter((s) => (bySeverity[s] ?? 0) > 0).map(
    (severity) => ({
      name: severity,
      value: bySeverity[severity] ?? 0,
      fill: `var(--color-${severity})`,
    })
  );

  if (chartData.length === 0) {
    return (
      <div
        className={`flex min-h-[200px] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-sm text-muted-foreground ${className ?? ""}`}
      >
        No severity data in range
      </div>
    );
  }

  return (
    <ChartContainer
      config={SEVERITY_CONFIG}
      className={`min-h-[200px] w-full ${className ?? ""}`}
    >
      <PieChart accessibilityLayer>
        <ChartTooltip content={<ChartTooltipContent nameKey="name" />} />
        <Pie
          data={chartData}
          dataKey="value"
          nameKey="name"
          innerRadius={60}
          outerRadius={80}
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
