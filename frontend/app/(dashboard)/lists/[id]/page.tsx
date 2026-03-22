"use client";

import { useState } from "react";
import { useParams } from "next/navigation";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  RefreshCw,
  Share2,
  Plus,
  Trash2,
  ChevronDown,
  ChevronRight,
  ShoppingCart,
  BarChart3,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { PriceBadge } from "@/components/shopping/price-badge";
import { ShareDialog } from "@/components/shopping/share-dialog";
import { getErrorMessage } from "@/lib/utils";
import Link from "next/link";
import {
  fetchList,
  refreshList,
  addItem,
  deleteItem,
  analyzeList,
  fetchListAnalysis,
  type ShoppingList,
  type ShoppingItem,
  type CreateItemData,
  type StoreAnalysis,
} from "@/lib/api/shopping";
import { StoreComparison } from "@/components/shopping/store-comparison";
import { DealSavingsBanner } from "@/components/shopping/deal-savings-banner";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";

export default function ListDetailPage() {
  const params = useParams();
  const listId = Number(params.id);
  const queryClient = useQueryClient();

  const [shareOpen, setShareOpen] = useState(false);
  const [addItemOpen, setAddItemOpen] = useState(false);
  const [purchasedExpanded, setPurchasedExpanded] = useState(false);
  const [compareOpen, setCompareOpen] = useState(false);

  // Add item form state
  const [newItemName, setNewItemName] = useState("");
  const [newItemUpc, setNewItemUpc] = useState("");
  const [newItemRetailer, setNewItemRetailer] = useState("");
  const [newItemUrl, setNewItemUrl] = useState("");
  const [newItemTargetPrice, setNewItemTargetPrice] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["shopping-list", listId],
    queryFn: () => fetchList(listId),
    enabled: !isNaN(listId),
  });

  const list: ShoppingList | undefined = data?.data?.data;

  usePageTitle(list?.name ?? "Shopping List");

  const refreshMutation = useMutation({
    mutationFn: () => refreshList(listId),
    onSuccess: () => {
      toast.success("List refreshed");
      queryClient.invalidateQueries({ queryKey: ["shopping-list", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to refresh list"));
    },
  });

  const addItemMutation = useMutation({
    mutationFn: (data: CreateItemData) => addItem(listId, data),
    onSuccess: () => {
      toast.success("Item added");
      setAddItemOpen(false);
      resetAddItemForm();
      queryClient.invalidateQueries({ queryKey: ["shopping-list", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to add item"));
    },
  });

  const deleteItemMutation = useMutation({
    mutationFn: (itemId: number) => deleteItem(itemId),
    onSuccess: () => {
      toast.success("Item removed");
      queryClient.invalidateQueries({ queryKey: ["shopping-list", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to remove item"));
    },
  });

  const analyzeMutation = useMutation({
    mutationFn: () => analyzeList(listId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["list-analysis", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to analyze list"));
    },
  });

  const { data: analysisData, isLoading: analysisLoading } = useQuery({
    queryKey: ["list-analysis", listId],
    queryFn: () => fetchListAnalysis(listId),
    enabled: compareOpen && !isNaN(listId),
  });

  const analysis: StoreAnalysis | null | undefined = analysisData?.data?.data;

  function resetAddItemForm() {
    setNewItemName("");
    setNewItemUpc("");
    setNewItemRetailer("");
    setNewItemUrl("");
    setNewItemTargetPrice("");
  }

  function handleAddItem(e: React.FormEvent) {
    e.preventDefault();
    if (!newItemName.trim()) return;
    addItemMutation.mutate({
      product_name: newItemName.trim(),
      upc: newItemUpc.trim() || undefined,
      retailer: newItemRetailer.trim() || undefined,
      url: newItemUrl.trim() || undefined,
      target_price: newItemTargetPrice
        ? parseFloat(newItemTargetPrice)
        : undefined,
    });
  }

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-6 w-96" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!list) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-center">
        <ShoppingCart className="h-12 w-12 text-muted-foreground mb-4" />
        <h2 className="text-lg font-semibold">List not found</h2>
        <p className="text-sm text-muted-foreground mt-1">
          This shopping list may have been deleted.
        </p>
      </div>
    );
  }

  const items: ShoppingItem[] = list.items ?? [];
  const activeItems = items.filter((item) => !item.is_purchased);
  const purchasedItems = items.filter((item) => item.is_purchased);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{list.name}</h1>
          {list.description && (
            <p className="text-muted-foreground mt-1">{list.description}</p>
          )}
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
            Refresh
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="gap-2"
            onClick={() => setCompareOpen(true)}
          >
            <BarChart3 className="h-4 w-4" />
            Compare Stores
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="gap-2"
            onClick={() => setShareOpen(true)}
          >
            <Share2 className="h-4 w-4" />
            Share
          </Button>
          <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
            <DialogTrigger asChild>
              <Button size="sm" className="gap-2">
                <Plus className="h-4 w-4" />
                Add Item
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add Item</DialogTitle>
                <DialogDescription>
                  Add a product to track in this list.
                </DialogDescription>
              </DialogHeader>
              <form onSubmit={handleAddItem} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="item-name">Product name</Label>
                  <Input
                    id="item-name"
                    value={newItemName}
                    onChange={(e) => setNewItemName(e.target.value)}
                    placeholder="e.g. Organic Milk 1 Gallon"
                    required
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="item-upc">UPC (optional)</Label>
                    <Input
                      id="item-upc"
                      value={newItemUpc}
                      onChange={(e) => setNewItemUpc(e.target.value)}
                      placeholder="012345678901"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="item-retailer">Retailer (optional)</Label>
                    <Input
                      id="item-retailer"
                      value={newItemRetailer}
                      onChange={(e) => setNewItemRetailer(e.target.value)}
                      placeholder="e.g. Walmart"
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="item-url">Product URL (optional)</Label>
                  <Input
                    id="item-url"
                    type="url"
                    value={newItemUrl}
                    onChange={(e) => setNewItemUrl(e.target.value)}
                    placeholder="https://..."
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="item-target">Target price (optional)</Label>
                  <Input
                    id="item-target"
                    type="number"
                    step="0.01"
                    min="0"
                    value={newItemTargetPrice}
                    onChange={(e) => setNewItemTargetPrice(e.target.value)}
                    placeholder="0.00"
                    className="max-w-32"
                  />
                </div>
                <DialogFooter>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setAddItemOpen(false);
                      resetAddItemForm();
                    }}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    disabled={addItemMutation.isPending || !newItemName.trim()}
                  >
                    {addItemMutation.isPending ? "Adding..." : "Add Item"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* Deal Savings Banner */}
      <DealSavingsBanner listId={listId} />

      {/* Active Items Table */}
      {activeItems.length === 0 && purchasedItems.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
          <ShoppingCart className="h-10 w-10 text-muted-foreground mb-3" />
          <h3 className="text-base font-semibold">No items yet</h3>
          <p className="text-sm text-muted-foreground mt-1">
            Add items to start tracking prices.
          </p>
        </div>
      ) : (
        <>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead className="text-right">Current</TableHead>
                  <TableHead className="text-right hidden sm:table-cell">
                    Previous
                  </TableHead>
                  <TableHead className="text-right hidden md:table-cell">
                    Lowest
                  </TableHead>
                  <TableHead className="hidden sm:table-cell">Status</TableHead>
                  <TableHead className="w-16" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {activeItems.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={6}
                      className="text-center text-muted-foreground py-8"
                    >
                      All items have been purchased.
                    </TableCell>
                  </TableRow>
                ) : (
                  activeItems.map((item) => (
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
                      <TableCell className="text-right">
                        <PriceBadge
                          price={item.current_price ?? null}
                          previousPrice={item.previous_price}
                        />
                      </TableCell>
                      <TableCell className="text-right hidden sm:table-cell">
                        {item.previous_price != null
                          ? `$${item.previous_price.toFixed(2)}`
                          : "--"}
                      </TableCell>
                      <TableCell className="text-right hidden md:table-cell">
                        {item.lowest_price != null
                          ? `$${item.lowest_price.toFixed(2)}`
                          : "--"}
                      </TableCell>
                      <TableCell className="hidden sm:table-cell">
                        {item.in_stock === false ? (
                          <Badge variant="destructive">Out of stock</Badge>
                        ) : (
                          <Badge variant="success">In stock</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-destructive hover:text-destructive"
                          onClick={() => deleteItemMutation.mutate(item.id)}
                          disabled={deleteItemMutation.isPending}
                          title="Remove item"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          {/* Purchased Items */}
          {purchasedItems.length > 0 && (
            <Collapsible
              open={purchasedExpanded}
              onOpenChange={setPurchasedExpanded}
            >
              <CollapsibleTrigger asChild>
                <Button
                  variant="ghost"
                  className="gap-2 text-muted-foreground"
                >
                  {purchasedExpanded ? (
                    <ChevronDown className="h-4 w-4" />
                  ) : (
                    <ChevronRight className="h-4 w-4" />
                  )}
                  Purchased ({purchasedItems.length})
                </Button>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <div className="rounded-md border mt-2">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Product</TableHead>
                        <TableHead className="text-right">
                          Purchase Price
                        </TableHead>
                        <TableHead className="hidden sm:table-cell">
                          Purchased
                        </TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {purchasedItems.map((item) => (
                        <TableRow key={item.id} className="opacity-60">
                          <TableCell>
                            <Link
                              href={`/items/${item.id}`}
                              className="font-medium hover:underline"
                            >
                              {item.product_name}
                            </Link>
                          </TableCell>
                          <TableCell className="text-right">
                            {item.purchased_price != null
                              ? `$${item.purchased_price.toFixed(2)}`
                              : "--"}
                          </TableCell>
                          <TableCell className="hidden sm:table-cell text-sm text-muted-foreground">
                            {item.purchased_at
                              ? new Date(item.purchased_at).toLocaleDateString()
                              : "--"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CollapsibleContent>
            </Collapsible>
          )}
        </>
      )}

      {/* Share Dialog */}
      <ShareDialog
        listId={listId}
        listName={list.name}
        open={shareOpen}
        onOpenChange={setShareOpen}
      />

      {/* Compare Stores Sheet */}
      <Sheet open={compareOpen} onOpenChange={setCompareOpen}>
        <SheetContent side="bottom" className="h-[85vh] overflow-y-auto">
          <SheetHeader className="mb-4">
            <SheetTitle>Compare Stores</SheetTitle>
            <SheetDescription>
              See which store has the best prices for your full list.
            </SheetDescription>
          </SheetHeader>
          <div className="mb-4">
            <Button
              size="sm"
              variant="outline"
              className="gap-2"
              onClick={() => analyzeMutation.mutate()}
              disabled={analyzeMutation.isPending}
            >
              <RefreshCw className={`h-4 w-4 ${analyzeMutation.isPending ? "animate-spin" : ""}`} />
              {analyzeMutation.isPending ? "Analyzing..." : "Run Analysis"}
            </Button>
          </div>
          <StoreComparison
            analysis={analysis as StoreAnalysis}
            loading={analysisLoading || analyzeMutation.isPending}
          />
        </SheetContent>
      </Sheet>
    </div>
  );
}
