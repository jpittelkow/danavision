"use client";

import { useState } from "react";
import { CheckCircle2, ShoppingCart, SplitSquareHorizontal, TrendingDown } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { AnalysisStore, StoreAnalysis } from "@/lib/api/shopping";
import { formatPrice, formatUnitPrice } from "@/lib/format-unit-price";

interface Props {
  analysis: StoreAnalysis;
  loading?: boolean;
}

export function StoreComparison({ analysis, loading }: Props) {
  if (loading) {
    return <StoreComparisonSkeleton />;
  }

  if (!analysis || analysis.stores.length === 0) {
    return (
      <div className="text-center py-10 text-muted-foreground text-sm">
        No price data available yet. Refresh prices to compare stores.
      </div>
    );
  }

  return (
    <Tabs defaultValue="by-store">
      <TabsList className="mb-4">
        <TabsTrigger value="by-store">
          <ShoppingCart className="w-4 h-4 mr-1.5" />
          By Store
        </TabsTrigger>
        <TabsTrigger value="best-split">
          <SplitSquareHorizontal className="w-4 h-4 mr-1.5" />
          Best Split
        </TabsTrigger>
        <TabsTrigger value="by-item">
          <TrendingDown className="w-4 h-4 mr-1.5" />
          By Item
        </TabsTrigger>
      </TabsList>

      <TabsContent value="by-store">
        <ByStoreTab analysis={analysis} />
      </TabsContent>

      <TabsContent value="best-split">
        <BestSplitTab analysis={analysis} />
      </TabsContent>

      <TabsContent value="by-item">
        <ByItemTab analysis={analysis} />
      </TabsContent>
    </Tabs>
  );
}

// ---------------------------------------------------------------------------
// Tab: By Store
// ---------------------------------------------------------------------------

function ByStoreTab({ analysis }: { analysis: StoreAnalysis }) {
  const cheapestId = analysis.cheapest_store?.store_id;
  const cheapestName = analysis.cheapest_store?.store_name;

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {analysis.stores.map((store) => {
        const isCheapest =
          (store.store_id !== null && store.store_id === cheapestId) ||
          (store.store_id === null && store.store_name === cheapestName);

        return (
          <StoreCard key={store.store_name} store={store} isCheapest={isCheapest} totalItems={analysis.total_items} />
        );
      })}
    </div>
  );
}

function StoreCard({
  store,
  isCheapest,
  totalItems,
}: {
  store: AnalysisStore;
  isCheapest: boolean;
  totalItems: number;
}) {
  return (
    <Card className={isCheapest ? "border-green-500 ring-1 ring-green-500" : undefined}>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between gap-2">
          <CardTitle className="text-base">{store.store_name}</CardTitle>
          {isCheapest && (
            <Badge variant="secondary" className="text-green-700 bg-green-100 shrink-0">
              <CheckCircle2 className="w-3 h-3 mr-1" />
              Cheapest
            </Badge>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-2xl font-semibold tabular-nums">{formatPrice(store.total_cost)}</p>

        {/* Coverage bar */}
        <div className="space-y-1">
          <div className="flex justify-between text-xs text-muted-foreground">
            <span>{store.items_found} of {totalItems} items</span>
            <span>{store.coverage_percent}%</span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
            <div
              className="h-full rounded-full bg-primary transition-all"
              style={{ width: `${store.coverage_percent}%` }}
            />
          </div>
        </div>

        {store.savings_vs_highest !== null && store.savings_vs_highest > 0 && (
          <p className="text-xs text-green-700">
            Save {formatPrice(store.savings_vs_highest)} vs. most expensive store
          </p>
        )}

        {store.items_missing > 0 && (
          <p className="text-xs text-amber-600">{store.items_missing} item{store.items_missing > 1 ? "s" : ""} not found</p>
        )}
      </CardContent>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Tab: Best Split
// ---------------------------------------------------------------------------

function BestSplitTab({ analysis }: { analysis: StoreAnalysis }) {
  const split = analysis.split_shopping;

  if (split.stores.length === 0) {
    return (
      <p className="text-sm text-muted-foreground py-6 text-center">
        Not enough price data to compute a split-shopping plan.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 flex-wrap">
        <div>
          <p className="text-sm text-muted-foreground">Total split cost</p>
          <p className="text-2xl font-semibold tabular-nums">{formatPrice(split.total_cost)}</p>
        </div>
        {split.total_savings !== null && split.total_savings > 0 && (
          <div>
            <p className="text-sm text-muted-foreground">Savings vs single store</p>
            <p className="text-xl font-semibold text-green-700 tabular-nums">
              {formatPrice(split.total_savings)}
            </p>
          </div>
        )}
        <Badge variant="outline">{split.store_count} store{split.store_count !== 1 ? "s" : ""}</Badge>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        {split.stores.map((store) => (
          <Card key={store.store_name}>
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">{store.store_name}</CardTitle>
                <span className="font-semibold tabular-nums text-sm">{formatPrice(store.subtotal)}</span>
              </div>
            </CardHeader>
            <CardContent>
              <ul className="space-y-1">
                {store.items.map((item) => (
                  <li key={item.item_id} className="flex justify-between text-sm">
                    <span className="truncate mr-2 text-muted-foreground">{item.name}</span>
                    <span className="tabular-nums shrink-0">
                      {formatPrice(item.price)}
                      {item.unit_price != null && item.unit_type && (
                        <span className="text-xs text-muted-foreground ml-1">
                          ({formatUnitPrice(item.unit_price, item.unit_type)})
                        </span>
                      )}
                    </span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Tab: By Item
// ---------------------------------------------------------------------------

function ByItemTab({ analysis }: { analysis: StoreAnalysis }) {
  const storeNames = analysis.stores.map((s) => s.store_name);

  // Build lookup: storeName → itemId → itemData
  const storePriceMap: Record<string, Record<number, { price: number; unit_price: number | null; unit_type: string | null; is_cheapest: boolean }>> = {};
  for (const store of analysis.stores) {
    storePriceMap[store.store_name] = {};
    for (const item of store.items) {
      storePriceMap[store.store_name][item.item_id] = {
        price: item.price,
        unit_price: item.unit_price,
        unit_type: item.unit_type,
        is_cheapest: item.is_cheapest,
      };
    }
  }

  // Collect all unique items
  const itemMap: Record<number, string> = {};
  for (const store of analysis.stores) {
    for (const item of store.items) {
      itemMap[item.item_id] = item.name;
    }
  }
  const items = Object.entries(itemMap).map(([id, name]) => ({ id: Number(id), name }));

  if (items.length === 0) {
    return <p className="text-sm text-muted-foreground py-6 text-center">No items found.</p>;
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="min-w-[160px]">Item</TableHead>
            {storeNames.map((name) => (
              <TableHead key={name} className="text-right min-w-[110px]">{name}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.map(({ id, name }) => (
            <TableRow key={id}>
              <TableCell className="font-medium">{name}</TableCell>
              {storeNames.map((storeName) => {
                const entry = storePriceMap[storeName]?.[id];
                if (!entry) {
                  return (
                    <TableCell key={storeName} className="text-right text-muted-foreground">
                      —
                    </TableCell>
                  );
                }
                return (
                  <TableCell
                    key={storeName}
                    className={`text-right tabular-nums${entry.is_cheapest ? " text-green-700 font-semibold" : ""}`}
                  >
                    {formatPrice(entry.price)}
                    {entry.unit_price != null && entry.unit_type && (
                      <div className="text-xs text-muted-foreground">
                        {formatUnitPrice(entry.unit_price, entry.unit_type)}
                      </div>
                    )}
                  </TableCell>
                );
              })}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Skeleton
// ---------------------------------------------------------------------------

function StoreComparisonSkeleton() {
  return (
    <div className="space-y-4">
      <div className="flex gap-2">
        <Skeleton className="h-9 w-28" />
        <Skeleton className="h-9 w-28" />
        <Skeleton className="h-9 w-28" />
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Card key={i}>
            <CardHeader className="pb-2">
              <Skeleton className="h-5 w-32" />
            </CardHeader>
            <CardContent className="space-y-3">
              <Skeleton className="h-8 w-24" />
              <Skeleton className="h-2 w-full" />
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
