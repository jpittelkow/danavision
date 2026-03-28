"use client";

import { useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  ArrowLeft,
  RefreshCw,
  ShoppingBag,
  Package,
  Store,
  Barcode,
  Target,
  ExternalLink,
} from "lucide-react";
import Link from "next/link";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Card,
  CardContent,
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { PriceBadge } from "@/components/shopping/price-badge";
import { PriceChart } from "@/components/shopping/price-chart";
import { getErrorMessage } from "@/lib/utils";
import { api } from "@/lib/api";
import {
  fetchItemHistory,
  refreshItem,
  markPurchased,
  updateItem,
  type ShoppingItem,
  type PriceHistoryEntry,
  type ItemVendorPrice,
} from "@/lib/api/shopping";

export default function ItemDetailPage() {
  const params = useParams();
  const router = useRouter();
  const itemId = Number(params.id);
  const queryClient = useQueryClient();

  const [purchaseDialogOpen, setPurchaseDialogOpen] = useState(false);
  const [purchasePrice, setPurchasePrice] = useState("");
  const [targetPrice, setTargetPrice] = useState("");
  const [isEditingTarget, setIsEditingTarget] = useState(false);

  // Fetch item details
  const { data: itemResponse, isLoading: itemLoading } = useQuery({
    queryKey: ["shopping-item", itemId],
    queryFn: () => api.get<{ data: ShoppingItem }>(`/items/${itemId}`),
    enabled: !isNaN(itemId),
  });

  const item: ShoppingItem | undefined = itemResponse?.data?.data;

  // Fetch price history
  const { data: historyResponse } = useQuery({
    queryKey: ["item-history", itemId],
    queryFn: () => fetchItemHistory(itemId),
    enabled: !isNaN(itemId),
  });

  const history: PriceHistoryEntry[] = historyResponse?.data?.data ?? [];
  const vendorPrices: ItemVendorPrice[] = (item?.vendor_prices ?? [])
    .filter((vp) => vp.current_price != null)
    .sort((a, b) => Number(a.current_price) - Number(b.current_price));

  usePageTitle(item?.product_name ?? "Item Details");

  const refreshMutation = useMutation({
    mutationFn: () => refreshItem(itemId),
    onSuccess: () => {
      toast.success("Item price refreshed");
      queryClient.invalidateQueries({ queryKey: ["shopping-item", itemId] });
      queryClient.invalidateQueries({ queryKey: ["item-history", itemId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to refresh item"));
    },
  });

  const purchaseMutation = useMutation({
    mutationFn: (price?: number) => markPurchased(itemId, price),
    onSuccess: () => {
      toast.success("Item marked as purchased");
      setPurchaseDialogOpen(false);
      queryClient.invalidateQueries({ queryKey: ["shopping-item", itemId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to mark as purchased"));
    },
  });

  const updateTargetMutation = useMutation({
    mutationFn: (newTarget: number) =>
      updateItem(itemId, { target_price: newTarget }),
    onSuccess: () => {
      toast.success("Target price updated");
      setIsEditingTarget(false);
      queryClient.invalidateQueries({ queryKey: ["shopping-item", itemId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to update target price"));
    },
  });

  if (itemLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-64" />
        <div className="grid gap-6 md:grid-cols-2">
          <Skeleton className="h-48" />
          <Skeleton className="h-48" />
        </div>
        <Skeleton className="h-[300px]" />
      </div>
    );
  }

  if (!item) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-center">
        <Package className="h-12 w-12 text-muted-foreground mb-4" />
        <h2 className="text-lg font-semibold">Item not found</h2>
        <p className="text-sm text-muted-foreground mt-1">
          This item may have been removed from the list.
        </p>
        <Button
          variant="outline"
          className="mt-4 gap-2"
          onClick={() => router.back()}
        >
          <ArrowLeft className="h-4 w-4" />
          Go back
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Back link */}
      <Button
        variant="ghost"
        size="sm"
        className="gap-2 -ml-2"
        asChild
      >
        <Link href={`/lists/${item.shopping_list_id}`}>
          <ArrowLeft className="h-4 w-4" />
          Back to list
        </Link>
      </Button>

      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-4">
          {item.image_url ? (
            <img
              src={item.image_url}
              alt={item.product_name}
              className="h-20 w-20 rounded-lg object-cover border"
            />
          ) : (
            <div className="h-20 w-20 rounded-lg bg-muted flex items-center justify-center">
              <Package className="h-8 w-8 text-muted-foreground" />
            </div>
          )}
          <div>
            <h1 className="text-2xl font-bold tracking-tight">
              {item.product_name}
            </h1>
            <div className="flex flex-wrap items-center gap-2 mt-1">
              {item.retailer && (
                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                  <Store className="h-3.5 w-3.5" />
                  {item.retailer}
                </span>
              )}
              {item.upc && (
                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                  <Barcode className="h-3.5 w-3.5" />
                  {item.upc}
                </span>
              )}
              {item.in_stock === false ? (
                <Badge variant="destructive">Out of stock</Badge>
              ) : (
                <Badge variant="success">In stock</Badge>
              )}
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            className="gap-2"
            onClick={() => refreshMutation.mutate()}
            disabled={refreshMutation.isPending}
          >
            <RefreshCw
              className={`h-4 w-4 ${refreshMutation.isPending ? "animate-spin" : ""}`}
            />
            Refresh Price
          </Button>
          {item.url && (
            <Button variant="outline" size="sm" className="gap-2" asChild>
              <a href={item.url} target="_blank" rel="noopener noreferrer">
                <ExternalLink className="h-4 w-4" />
                View Product
              </a>
            </Button>
          )}
          <Button
            size="sm"
            className="gap-2"
            onClick={() => {
              setPurchasePrice(
                item.current_price != null
                  ? item.current_price.toString()
                  : ""
              );
              setPurchaseDialogOpen(true);
            }}
          >
            <ShoppingBag className="h-4 w-4" />
            Mark Purchased
          </Button>
        </div>
      </div>

      {/* Price summary cards */}
      <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-muted-foreground font-normal">
              Current Price
            </CardTitle>
          </CardHeader>
          <CardContent>
            <PriceBadge
              price={item.current_price ?? null}
              previousPrice={item.previous_price}
              className="text-2xl"
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-muted-foreground font-normal">
              Previous Price
            </CardTitle>
          </CardHeader>
          <CardContent>
            <span className="text-2xl font-medium">
              {item.previous_price != null
                ? `$${Number(item.previous_price).toFixed(2)}`
                : "--"}
            </span>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-muted-foreground font-normal">
              Lowest Price
            </CardTitle>
          </CardHeader>
          <CardContent>
            <span className="text-2xl font-medium text-green-600 dark:text-green-400">
              {item.lowest_price != null
                ? `$${Number(item.lowest_price).toFixed(2)}`
                : "--"}
            </span>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-muted-foreground font-normal flex items-center gap-1.5">
              <Target className="h-3.5 w-3.5" />
              Target Price
            </CardTitle>
          </CardHeader>
          <CardContent>
            {isEditingTarget ? (
              <div className="flex items-center gap-2">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={targetPrice}
                  onChange={(e) => setTargetPrice(e.target.value)}
                  className="h-8 w-24 text-sm"
                />
                <Button
                  size="sm"
                  variant="outline"
                  className="h-8"
                  onClick={() => {
                    if (targetPrice) {
                      updateTargetMutation.mutate(parseFloat(targetPrice));
                    }
                  }}
                  disabled={updateTargetMutation.isPending}
                >
                  Save
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  className="h-8"
                  onClick={() => setIsEditingTarget(false)}
                >
                  Cancel
                </Button>
              </div>
            ) : (
              <button
                className="text-2xl font-medium hover:underline cursor-pointer"
                onClick={() => {
                  setTargetPrice(
                    item.target_price != null
                      ? item.target_price.toString()
                      : ""
                  );
                  setIsEditingTarget(true);
                }}
                title="Click to edit target price"
              >
                {item.target_price != null
                  ? `$${Number(item.target_price).toFixed(2)}`
                  : "Set target"}
              </button>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Price History Chart */}
      <PriceChart
        data={history.map((h) => ({
          date: h.date ?? h.captured_at ?? "",
          price: h.price,
          retailer: h.retailer,
        }))}
        targetPrice={item.target_price}
      />

      {/* Vendor Comparison */}
      {vendorPrices.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              Vendor Price Comparison
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Retailer</TableHead>
                  <TableHead className="text-right">Price</TableHead>
                  <TableHead className="text-right">In Stock</TableHead>
                  <TableHead className="text-right">Last Checked</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {vendorPrices.map((vp) => (
                  <TableRow key={vp.id}>
                    <TableCell className="font-medium">
                      {vp.product_url ? (
                        <a
                          href={vp.product_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="hover:underline inline-flex items-center gap-1"
                        >
                          {vp.vendor}
                          <ExternalLink className="h-3 w-3" />
                        </a>
                      ) : (
                        vp.vendor
                      )}
                      {vp.on_sale && (
                        <Badge variant="destructive" className="ml-2 text-xs">
                          Sale{vp.sale_percent_off ? ` ${vp.sale_percent_off}% off` : ""}
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      {vp.current_price != null ? `$${Number(vp.current_price).toFixed(2)}` : "—"}
                      {vp.unit_price != null && vp.unit_type && (
                        <span className="block text-xs text-muted-foreground">
                          ${Number(vp.unit_price).toFixed(2)}/{vp.unit_type}
                        </span>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      {vp.in_stock === false ? (
                        <Badge variant="outline" className="text-xs">Out of stock</Badge>
                      ) : vp.in_stock === true ? (
                        <Badge variant="secondary" className="text-xs">In stock</Badge>
                      ) : "—"}
                    </TableCell>
                    <TableCell className="text-right text-muted-foreground">
                      {vp.last_checked_at
                        ? new Date(vp.last_checked_at).toLocaleDateString()
                        : "—"}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {/* Purchase Dialog */}
      <Dialog open={purchaseDialogOpen} onOpenChange={setPurchaseDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark as Purchased</DialogTitle>
            <DialogDescription>
              Record the purchase price for &quot;{item.product_name}&quot;.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="purchase-price">
                Purchase price (optional)
              </Label>
              <Input
                id="purchase-price"
                type="number"
                step="0.01"
                min="0"
                value={purchasePrice}
                onChange={(e) => setPurchasePrice(e.target.value)}
                placeholder="0.00"
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setPurchaseDialogOpen(false)}
            >
              Cancel
            </Button>
            <Button
              onClick={() =>
                purchaseMutation.mutate(
                  purchasePrice ? parseFloat(purchasePrice) : undefined
                )
              }
              disabled={purchaseMutation.isPending}
            >
              {purchaseMutation.isPending
                ? "Saving..."
                : "Confirm Purchase"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
