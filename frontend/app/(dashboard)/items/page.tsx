"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import {
  Package,
  TrendingDown,
  Target,
  Star,
  Filter,
  ArrowUpDown,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Card,
  CardContent,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { PriceBadge } from "@/components/shopping/price-badge";
import { api } from "@/lib/api";
import { fetchLists, type ShoppingList, type ShoppingItem } from "@/lib/api/shopping";

interface AllItemsResponse {
  data: (ShoppingItem & { shopping_list?: { id: number; name: string } })[];
  current_page: number;
  last_page: number;
  total: number;
}

export default function AllItemsPage() {
  usePageTitle("All Items");

  const [listFilter, setListFilter] = useState<string>("all");
  const [statusFilter, setStatusFilter] = useState<string>("active");
  const [priceStatusFilter, setPriceStatusFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<string>("updated");
  const [sortDir, setSortDir] = useState<string>("desc");
  const [page, setPage] = useState(1);

  const { data: listsResponse } = useQuery({
    queryKey: ["shopping-lists"],
    queryFn: fetchLists,
  });

  const lists: ShoppingList[] = listsResponse?.data?.data ?? [];

  const { data: itemsResponse, isLoading } = useQuery({
    queryKey: ["all-items", listFilter, statusFilter, priceStatusFilter, sortBy, sortDir, page],
    queryFn: () => {
      const params: Record<string, string> = {
        status: statusFilter,
        sort: sortBy,
        direction: sortDir,
        page: page.toString(),
      };
      if (listFilter !== "all") params.list_id = listFilter;
      if (priceStatusFilter !== "all") params.price_status = priceStatusFilter;

      return api.get<AllItemsResponse>("/items", { params });
    },
  });

  const items = itemsResponse?.data?.data ?? [];
  const totalPages = itemsResponse?.data?.last_page ?? 1;
  const totalItems = itemsResponse?.data?.total ?? 0;

  function toggleSort(field: string) {
    if (sortBy === field) {
      setSortDir(sortDir === "asc" ? "desc" : "asc");
    } else {
      setSortBy(field);
      setSortDir(field === "name" ? "asc" : "desc");
    }
    setPage(1);
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
          <Package className="h-6 w-6" />
          All Items
        </h1>
        <p className="text-muted-foreground mt-1">
          View and filter all tracked items across your shopping lists.
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-muted-foreground" />
          <Select value={listFilter} onValueChange={(v) => { setListFilter(v); setPage(1); }}>
            <SelectTrigger className="w-40">
              <SelectValue placeholder="All Lists" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Lists</SelectItem>
              {lists.map((list) => (
                <SelectItem key={list.id} value={list.id.toString()}>
                  {list.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
          <SelectTrigger className="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="purchased">Purchased</SelectItem>
            <SelectItem value="all">All</SelectItem>
          </SelectContent>
        </Select>

        <Select value={priceStatusFilter} onValueChange={(v) => { setPriceStatusFilter(v); setPage(1); }}>
          <SelectTrigger className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Any Price</SelectItem>
            <SelectItem value="drop">
              <span className="flex items-center gap-1.5">
                <TrendingDown className="h-3 w-3" /> Price Drops
              </span>
            </SelectItem>
            <SelectItem value="all_time_low">
              <span className="flex items-center gap-1.5">
                <Star className="h-3 w-3" /> All-Time Lows
              </span>
            </SelectItem>
            <SelectItem value="below_target">
              <span className="flex items-center gap-1.5">
                <Target className="h-3 w-3" /> Below Target
              </span>
            </SelectItem>
          </SelectContent>
        </Select>

        {totalItems > 0 && (
          <span className="text-sm text-muted-foreground ml-auto">
            {totalItems} item{totalItems !== 1 ? "s" : ""}
          </span>
        )}
      </div>

      {/* Items Table */}
      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 8 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Package className="h-12 w-12 text-muted-foreground mb-4" />
            <p className="text-muted-foreground">No items match your filters.</p>
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="gap-1 -ml-3 h-8"
                      onClick={() => toggleSort("name")}
                    >
                      Product
                      <ArrowUpDown className="h-3 w-3" />
                    </Button>
                  </TableHead>
                  <TableHead className="hidden sm:table-cell">List</TableHead>
                  <TableHead className="text-right">
                    <Button
                      variant="ghost"
                      size="sm"
                      className="gap-1 -mr-3 h-8"
                      onClick={() => toggleSort("price")}
                    >
                      Price
                      <ArrowUpDown className="h-3 w-3" />
                    </Button>
                  </TableHead>
                  <TableHead className="text-right hidden md:table-cell">Previous</TableHead>
                  <TableHead className="text-right hidden lg:table-cell">Lowest</TableHead>
                  <TableHead className="text-right hidden lg:table-cell">Target</TableHead>
                  <TableHead className="hidden sm:table-cell">Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <Link
                        href={`/items/${item.id}`}
                        className="font-medium hover:underline"
                      >
                        {item.product_name}
                      </Link>
                      {item.retailer && (
                        <p className="text-xs text-muted-foreground">
                          {item.retailer}
                        </p>
                      )}
                    </TableCell>
                    <TableCell className="hidden sm:table-cell">
                      {item.shopping_list ? (
                        <Link
                          href={`/lists/${item.shopping_list.id}`}
                          className="text-sm text-muted-foreground hover:underline"
                        >
                          {item.shopping_list.name}
                        </Link>
                      ) : (
                        "--"
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <PriceBadge
                        price={item.current_price ?? null}
                        previousPrice={item.previous_price}
                      />
                    </TableCell>
                    <TableCell className="text-right hidden md:table-cell">
                      {item.previous_price != null
                        ? `$${item.previous_price.toFixed(2)}`
                        : "--"}
                    </TableCell>
                    <TableCell className="text-right hidden lg:table-cell">
                      {item.lowest_price != null
                        ? `$${item.lowest_price.toFixed(2)}`
                        : "--"}
                    </TableCell>
                    <TableCell className="text-right hidden lg:table-cell">
                      {item.target_price != null
                        ? `$${item.target_price.toFixed(2)}`
                        : "--"}
                    </TableCell>
                    <TableCell className="hidden sm:table-cell">
                      {item.is_purchased ? (
                        <Badge variant="outline">Purchased</Badge>
                      ) : item.in_stock === false ? (
                        <Badge variant="destructive">Out of stock</Badge>
                      ) : (
                        <Badge variant="success">In stock</Badge>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
              >
                Previous
              </Button>
              <span className="text-sm text-muted-foreground">
                Page {page} of {totalPages}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => setPage(page + 1)}
              >
                Next
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
