"use client";

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ReferenceLine,
  ResponsiveContainer,
} from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export interface PriceChartProps {
  data: Array<{ date: string; price: number; retailer?: string }>;
  targetPrice?: number | null;
}

export function PriceChart({ data, targetPrice }: PriceChartProps) {
  if (!data || data.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Price History</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            No price history available yet.
          </p>
        </CardContent>
      </Card>
    );
  }

  const prices = data.map((d) => d.price);
  const minPrice = Math.min(...prices);
  const maxPrice = Math.max(...prices);
  const padding = (maxPrice - minPrice) * 0.1 || 1;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Price History</CardTitle>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={data}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
            <XAxis
              dataKey="date"
              tick={{ fontSize: 12 }}
              className="text-muted-foreground"
            />
            <YAxis
              domain={[
                Math.floor(minPrice - padding),
                Math.ceil(maxPrice + padding),
              ]}
              tickFormatter={(v: number) => `$${Number(v).toFixed(2)}`}
              tick={{ fontSize: 12 }}
              className="text-muted-foreground"
            />
            <Tooltip
              formatter={(value: number) => [`$${Number(value).toFixed(2)}`, "Price"]}
              labelFormatter={(label: string) => `Date: ${label}`}
            />
            <Line
              type="monotone"
              dataKey="price"
              stroke="hsl(var(--primary))"
              strokeWidth={2}
              dot={{ r: 3 }}
              activeDot={{ r: 5 }}
            />
            {targetPrice != null && (
              <ReferenceLine
                y={targetPrice}
                stroke="hsl(var(--destructive))"
                strokeDasharray="5 5"
                label={{
                  value: `Target: $${Number(targetPrice).toFixed(2)}`,
                  position: "insideTopRight",
                  fill: "hsl(var(--destructive))",
                  fontSize: 12,
                }}
              />
            )}
          </LineChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
