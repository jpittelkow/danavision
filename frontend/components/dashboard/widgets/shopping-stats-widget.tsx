"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import {
  TrendingDown,
  Star,
  DollarSign,
  Target,
  RefreshCw,
  Share2,
  ShoppingCart,
  List,
} from "lucide-react";
import { Area, AreaChart, XAxis, YAxis, CartesianGrid, Bar, BarChart } from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart";
import { AuditStatsCard } from "@/components/audit/audit-stats-card";
import { fetchShoppingStats, type ShoppingStats } from "@/lib/api/shopping";
import { errorLogger } from "@/lib/error-logger";

// ---------------------------------------------------------------------------
// Shared hook
// ---------------------------------------------------------------------------

function useShoppingStats() {
  return useQuery<ShoppingStats>({
    queryKey: ["dashboard", "shopping-stats"],
    queryFn: async () => {
      const res = await fetchShoppingStats();
      return res.data.data;
    },
    staleTime: 60_000,
  });
}

// ---------------------------------------------------------------------------
// Stat cards
// ---------------------------------------------------------------------------

export function ShoppingStatCards() {
  const { data, isLoading, error } = useShoppingStats();

  if (error) {
    errorLogger.report(error instanceof Error ? error : new Error(String(error)), {
      context: "ShoppingStatCards",
    });
    return null;
  }

  if (isLoading) {
    return (
      <>
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-[120px] rounded-lg" />
        ))}
      </>
    );
  }

  if (!data) return null;

  const stats = [
    {
      title: "Price Drops",
      value: data.price_drops,
      icon: TrendingDown,
      variant: data.price_drops > 0 ? "success" as const : "default" as const,
      description: data.price_drops > 0 ? "Items with lower prices" : undefined,
    },
    {
      title: "Potential Savings",
      value: `$${Number(data.total_savings).toFixed(2)}`,
      icon: DollarSign,
      variant: data.total_savings > 0 ? "success" as const : "default" as const,
    },
    {
      title: "All-Time Lows",
      value: data.all_time_lows,
      icon: Star,
      variant: data.all_time_lows > 0 ? "info" as const : "default" as const,
    },
    {
      title: "Below Target",
      value: data.below_target,
      icon: Target,
      variant: data.below_target > 0 ? "success" as const : "default" as const,
    },
  ];

  return (
    <>
      {stats.map((stat, i) => (
        <div
          key={stat.title}
          className="animate-in fade-in slide-in-from-bottom-2"
          style={{ animationDelay: `${i * 75}ms`, animationFillMode: "backwards" }}
        >
          <AuditStatsCard
            title={stat.title}
            value={stat.value}
            icon={stat.icon}
            variant={stat.variant}
            description={stat.description}
          />
        </div>
      ))}
    </>
  );
}

// ---------------------------------------------------------------------------
// Overview counters (lists, items, refresh, shares)
// ---------------------------------------------------------------------------

export function ShoppingOverviewCards() {
  const { data, isLoading, error } = useShoppingStats();

  if (error || isLoading || !data) {
    if (isLoading) {
      return (
        <>
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-[120px] rounded-lg" />
          ))}
        </>
      );
    }
    return null;
  }

  const items = [
    { title: "Shopping Lists", value: data.total_lists, icon: List, variant: "default" as const },
    { title: "Tracked Items", value: data.total_items, icon: ShoppingCart, variant: "default" as const },
    {
      title: "Needs Refresh",
      value: data.needs_refresh,
      icon: RefreshCw,
      variant: data.needs_refresh > 5 ? "warning" as const : "default" as const,
    },
    {
      title: "Pending Shares",
      value: data.pending_shares,
      icon: Share2,
      variant: data.pending_shares > 0 ? "info" as const : "default" as const,
    },
  ];

  return (
    <>
      {items.map((stat, i) => (
        <div
          key={stat.title}
          className="animate-in fade-in slide-in-from-bottom-2"
          style={{ animationDelay: `${i * 75}ms`, animationFillMode: "backwards" }}
        >
          <AuditStatsCard
            title={stat.title}
            value={stat.value}
            icon={stat.icon}
            variant={stat.variant}
          />
        </div>
      ))}
    </>
  );
}

// ---------------------------------------------------------------------------
// Recent price drops
// ---------------------------------------------------------------------------

export function RecentDropsWidget() {
  const { data, isLoading, error } = useShoppingStats();

  if (error) return null;

  if (isLoading) {
    return (
      <Card>
        <CardHeader className="pb-2">
          <Skeleton className="h-4 w-32" />
        </CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded" />
          ))}
        </CardContent>
      </Card>
    );
  }

  const drops = data?.recent_drops ?? [];

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-sm font-medium flex items-center gap-2">
          <TrendingDown className="h-4 w-4 text-green-500" />
          Recent Price Drops
        </CardTitle>
      </CardHeader>
      <CardContent>
        {drops.length === 0 ? (
          <p className="text-sm text-muted-foreground">No recent price drops</p>
        ) : (
          <div className="space-y-3">
            {drops.map((drop) => {
              const savings = drop.previous_price - drop.current_price;
              const pct = drop.previous_price > 0
                ? ((savings / drop.previous_price) * 100).toFixed(0)
                : "0";

              return (
                <Link
                  key={drop.id}
                  href={`/lists/${drop.shopping_list_id}`}
                  className="flex items-center justify-between rounded-md border p-3 transition-colors hover:bg-muted/50"
                >
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{drop.product_name}</p>
                    <p className="text-xs text-muted-foreground">
                      {drop.shopping_list?.name ?? "Unknown list"}
                      {drop.current_retailer ? ` \u00b7 ${drop.current_retailer}` : ""}
                    </p>
                  </div>
                  <div className="ml-3 flex items-center gap-2 text-right">
                    <div>
                      <p className="text-sm font-semibold text-green-600 dark:text-green-400">
                        ${Number(drop.current_price).toFixed(2)}
                      </p>
                      <p className="text-xs text-muted-foreground line-through">
                        ${Number(drop.previous_price).toFixed(2)}
                      </p>
                    </div>
                    <Badge variant="secondary" className="text-xs text-green-600 dark:text-green-400">
                      -{pct}%
                    </Badge>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// 7-day price activity chart
// ---------------------------------------------------------------------------

const activityChartConfig: ChartConfig = {
  count: {
    label: "Price Checks",
    color: "hsl(var(--chart-1))",
  },
};

export function PriceActivityChart() {
  const { data, isLoading, error } = useShoppingStats();

  if (error) return null;

  if (isLoading) {
    return (
      <Card>
        <CardHeader className="pb-2">
          <Skeleton className="h-4 w-40" />
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[180px] w-full rounded" />
        </CardContent>
      </Card>
    );
  }

  // Fill missing days with zero counts
  const raw = data?.seven_day_activity ?? [];
  const filled: { date: string; count: number }[] = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    const match = raw.find((r) => r.date === key);
    filled.push({ date: key, count: match?.count ?? 0 });
  }

  const formatted = filled.map((d) => ({
    ...d,
    label: new Date(d.date + "T12:00:00").toLocaleDateString(undefined, { weekday: "short" }),
  }));

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-sm font-medium">7-Day Price Activity</CardTitle>
      </CardHeader>
      <CardContent>
        {formatted.every((d) => d.count === 0) ? (
          <p className="text-sm text-muted-foreground">No price checks in the last 7 days</p>
        ) : (
          <ChartContainer config={activityChartConfig} className="h-[180px] w-full">
            <AreaChart data={formatted} margin={{ top: 4, right: 4, bottom: 0, left: -20 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis
                dataKey="label"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
              />
              <YAxis
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                allowDecimals={false}
              />
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
        )}
      </CardContent>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Store leaderboard
// ---------------------------------------------------------------------------

const leaderboardChartConfig: ChartConfig = {
  items_count: {
    label: "Items",
    color: "hsl(var(--chart-2))",
  },
};

export function StoreLeaderboard() {
  const { data, isLoading, error } = useShoppingStats();

  if (error) return null;

  if (isLoading) {
    return (
      <Card>
        <CardHeader className="pb-2">
          <Skeleton className="h-4 w-36" />
        </CardHeader>
        <CardContent className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-8 w-full rounded" />
          ))}
        </CardContent>
      </Card>
    );
  }

  const stores = data?.store_leaderboard ?? [];

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-sm font-medium">Store Leaderboard</CardTitle>
      </CardHeader>
      <CardContent>
        {stores.length === 0 ? (
          <p className="text-sm text-muted-foreground">No store data yet</p>
        ) : (
          <ChartContainer config={leaderboardChartConfig} className="h-[180px] w-full">
            <BarChart
              data={stores.slice(0, 6)}
              layout="vertical"
              margin={{ top: 4, right: 4, bottom: 0, left: 0 }}
            >
              <CartesianGrid strokeDasharray="3 3" horizontal={false} />
              <XAxis type="number" tickLine={false} axisLine={false} allowDecimals={false} />
              <YAxis
                type="category"
                dataKey="vendor"
                tickLine={false}
                axisLine={false}
                width={90}
                tickFormatter={(v: string) => v.length > 12 ? v.slice(0, 12) + "\u2026" : v}
              />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar
                dataKey="items_count"
                fill="var(--color-items_count)"
                radius={[0, 4, 4, 0]}
              />
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  );
}
