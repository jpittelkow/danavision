"use client";

import { useState, useCallback, useRef } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  Upload,
  TicketPercent,
  Check,
  X,
  Camera,
  Loader2,
  Plus,
  Image as ImageIcon,
  CheckCheck,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { getErrorMessage } from "@/lib/utils";
import { useIsMobile } from "@/lib/use-mobile";
import { DealCard } from "@/components/shopping/deal-card";
import {
  uploadDealScan,
  createDeal,
  fetchDealQueue,
  fetchDeals,
  acceptDeal,
  acceptAllDeals,
  dismissDeal,
  fetchAIJobs,
  fetchStores,
  type DealScan,
  type ScannedDeal,
  type AIJob,
  type Store,
  type CreateDealData,
} from "@/lib/api/shopping";

export default function DealsPage() {
  usePageTitle("Deals & Coupons");
  const queryClient = useQueryClient();
  const isMobile = useIsMobile();
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const [isDragOver, setIsDragOver] = useState(false);
  const [manualOpen, setManualOpen] = useState(false);
  const [libraryTab, setLibraryTab] = useState("active");
  const [storeFilter, setStoreFilter] = useState<string>("all");

  // Manual form state
  const [manualForm, setManualForm] = useState<CreateDealData>({
    product_name: "",
    discount_type: "amount_off",
  });

  // Data queries
  const { data: queueResponse, isLoading: queueLoading } = useQuery({
    queryKey: ["deal-queue"],
    queryFn: fetchDealQueue,
    refetchInterval: 5000,
  });

  const { data: jobsResponse } = useQuery({
    queryKey: ["ai-jobs"],
    queryFn: fetchAIJobs,
    refetchInterval: 5000,
  });

  const { data: dealsResponse, isLoading: dealsLoading } = useQuery({
    queryKey: ["deals", libraryTab, storeFilter],
    queryFn: () =>
      fetchDeals({
        status: libraryTab !== "all" ? libraryTab : undefined,
        store_id: storeFilter !== "all" ? Number(storeFilter) : undefined,
      }),
  });

  const { data: storesResponse } = useQuery({
    queryKey: ["stores"],
    queryFn: fetchStores,
  });

  const scans: DealScan[] = queueResponse?.data?.data ?? [];
  const pendingDeals = scans.flatMap((s) => s.deals ?? []);
  const activeJobs: AIJob[] = (jobsResponse?.data?.data ?? []).filter(
    (j: AIJob) =>
      j.type === "deal_scan" &&
      (j.status === "pending" || j.status === "processing")
  );
  const libraryDeals: ScannedDeal[] = dealsResponse?.data?.data ?? [];
  const stores: Store[] = storesResponse?.data?.data ?? [];

  // Mutations
  const scanMutation = useMutation({
    mutationFn: (formData: FormData) => uploadDealScan(formData),
    onSuccess: () => {
      toast.success("Scan received. Extracting deals...");
      queryClient.invalidateQueries({ queryKey: ["deal-queue"] });
      queryClient.invalidateQueries({ queryKey: ["ai-jobs"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to upload scan"));
    },
  });

  const acceptMutation = useMutation({
    mutationFn: (id: number) => acceptDeal(id),
    onSuccess: () => {
      toast.success("Deal accepted");
      queryClient.invalidateQueries({ queryKey: ["deal-queue"] });
      queryClient.invalidateQueries({ queryKey: ["deals"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to accept deal"));
    },
  });

  const acceptAllMutation = useMutation({
    mutationFn: (scanId: number) => acceptAllDeals(scanId),
    onSuccess: (_, scanId) => {
      toast.success("All deals accepted");
      queryClient.invalidateQueries({ queryKey: ["deal-queue"] });
      queryClient.invalidateQueries({ queryKey: ["deals"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to accept deals"));
    },
  });

  const dismissMutation = useMutation({
    mutationFn: (id: number) => dismissDeal(id),
    onSuccess: () => {
      toast.success("Deal dismissed");
      queryClient.invalidateQueries({ queryKey: ["deal-queue"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to dismiss deal"));
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: CreateDealData) => createDeal(data),
    onSuccess: () => {
      toast.success("Deal created");
      setManualOpen(false);
      setManualForm({ product_name: "", discount_type: "amount_off" });
      queryClient.invalidateQueries({ queryKey: ["deals"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to create deal"));
    },
  });

  // Upload handlers
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback(() => {
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setIsDragOver(false);
      const files = Array.from(e.dataTransfer.files);
      if (files.length > 0) {
        const formData = new FormData();
        files.forEach((file) => formData.append("files[]", file));
        scanMutation.mutate(formData);
      }
    },
    [scanMutation]
  );

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? []);
    if (files.length > 0) {
      const formData = new FormData();
      files.forEach((file) => formData.append("files[]", file));
      scanMutation.mutate(formData);
    }
    // Reset the input so the same file can be re-selected
    e.target.value = "";
  }

  function handleManualSubmit() {
    if (!manualForm.product_name.trim() || !manualForm.discount_type) return;
    createMutation.mutate(manualForm);
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <TicketPercent className="h-6 w-6" />
            Deals & Coupons
          </h1>
          <p className="text-muted-foreground mt-1">
            Scan coupons, weekly ads, and flyers to apply deals to your shopping.
          </p>
        </div>
        <Dialog open={manualOpen} onOpenChange={setManualOpen}>
          <DialogTrigger asChild>
            <Button variant="outline" size="sm" className="gap-1.5">
              <Plus className="h-4 w-4" />
              Add Manually
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Add Deal Manually</DialogTitle>
              <DialogDescription>
                Enter a deal you saw online or in-store.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-2">
              <div className="space-y-2">
                <Label htmlFor="manual-product">Product Name</Label>
                <Input
                  id="manual-product"
                  placeholder="e.g., Cheerios Original 12oz"
                  value={manualForm.product_name}
                  onChange={(e) =>
                    setManualForm((f) => ({ ...f, product_name: e.target.value }))
                  }
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Store</Label>
                  <Select
                    value={manualForm.store_id?.toString() ?? ""}
                    onValueChange={(v) =>
                      setManualForm((f) => ({ ...f, store_id: v ? Number(v) : undefined }))
                    }
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select store" />
                    </SelectTrigger>
                    <SelectContent>
                      {stores.map((s) => (
                        <SelectItem key={s.id} value={s.id.toString()}>
                          {s.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>Discount Type</Label>
                  <Select
                    value={manualForm.discount_type}
                    onValueChange={(v) =>
                      setManualForm((f) => ({ ...f, discount_type: v }))
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="amount_off">$ Off</SelectItem>
                      <SelectItem value="percent_off">% Off</SelectItem>
                      <SelectItem value="fixed_price">Sale Price</SelectItem>
                      <SelectItem value="bogo">BOGO</SelectItem>
                      <SelectItem value="buy_x_get_y">Buy X Get Y</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                {manualForm.discount_type === "fixed_price" ? (
                  <div className="space-y-2">
                    <Label>Sale Price</Label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      value={manualForm.sale_price ?? ""}
                      onChange={(e) =>
                        setManualForm((f) => ({
                          ...f,
                          sale_price: e.target.value ? Number(e.target.value) : undefined,
                        }))
                      }
                    />
                  </div>
                ) : manualForm.discount_type !== "bogo" ? (
                  <div className="space-y-2">
                    <Label>
                      {manualForm.discount_type === "percent_off" ? "Percent Off" : "Amount Off"}
                    </Label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      value={manualForm.discount_value ?? ""}
                      onChange={(e) =>
                        setManualForm((f) => ({
                          ...f,
                          discount_value: e.target.value ? Number(e.target.value) : undefined,
                        }))
                      }
                    />
                  </div>
                ) : (
                  <div />
                )}
                <div className="space-y-2">
                  <Label>Original Price (optional)</Label>
                  <Input
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    value={manualForm.original_price ?? ""}
                    onChange={(e) =>
                      setManualForm((f) => ({
                        ...f,
                        original_price: e.target.value ? Number(e.target.value) : undefined,
                      }))
                    }
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Valid From</Label>
                  <Input
                    type="date"
                    value={manualForm.valid_from ?? ""}
                    onChange={(e) =>
                      setManualForm((f) => ({ ...f, valid_from: e.target.value || undefined }))
                    }
                  />
                </div>
                <div className="space-y-2">
                  <Label>Valid To</Label>
                  <Input
                    type="date"
                    value={manualForm.valid_to ?? ""}
                    onChange={(e) =>
                      setManualForm((f) => ({ ...f, valid_to: e.target.value || undefined }))
                    }
                  />
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setManualOpen(false)}>
                Cancel
              </Button>
              <Button
                onClick={handleManualSubmit}
                disabled={createMutation.isPending || !manualForm.product_name.trim()}
              >
                {createMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-1" />
                ) : null}
                Create Deal
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Scan Zone */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Scan Coupons & Ads</CardTitle>
          <CardDescription>
            Photograph coupons, weekly circulars, or store flyers to extract deals.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {/* Hidden camera input */}
          <input
            ref={cameraInputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            onChange={handleFileSelect}
            className="hidden"
          />

          <div className="flex flex-col sm:flex-row gap-4">
            {isMobile && (
              <button
                type="button"
                onClick={() => cameraInputRef.current?.click()}
                className="flex flex-col items-center justify-center gap-3 py-8 px-8 border-2 border-dashed rounded-lg transition-all border-primary/50 bg-primary/5 hover:border-primary hover:bg-primary/10"
              >
                <div className="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                  <Camera className="h-7 w-7 text-primary-foreground" />
                </div>
                <span className="text-base font-medium text-primary">
                  Take Photo
                </span>
              </button>
            )}

            <div
              className={`flex-1 flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors cursor-pointer ${
                isDragOver
                  ? "border-primary bg-primary/5"
                  : "border-muted-foreground/25 hover:border-muted-foreground/50"
              }`}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
            >
              <Upload className="h-10 w-10 text-muted-foreground mb-3" />
              <p className="text-sm font-medium">
                {isMobile ? "Select from gallery" : "Drop image here or click to upload"}
              </p>
              <p className="text-xs text-muted-foreground mt-1">
                Supports JPG, PNG, WebP up to 10MB
              </p>
              <label className="mt-3">
                <input
                  type="file"
                  multiple
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={handleFileSelect}
                />
                <Button variant="outline" size="sm" className="gap-2 cursor-pointer" asChild>
                  <span>
                    <ImageIcon className="h-4 w-4" />
                    Browse files
                  </span>
                </Button>
              </label>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Processing */}
      {activeJobs.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Processing</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {activeJobs.map((job) => (
              <div key={job.id} className="flex items-center gap-3 rounded-md border p-3">
                <Loader2 className="h-4 w-4 animate-spin text-primary shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium">Extracting deals...</p>
                  <p className="text-xs text-muted-foreground capitalize">{job.status}</p>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* Review Queue */}
      {(pendingDeals.length > 0 || queueLoading) && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold">
            Review Queue
            {pendingDeals.length > 0 && (
              <Badge variant="default" className="ml-2">
                {pendingDeals.length}
              </Badge>
            )}
          </h2>

          {queueLoading ? (
            <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-32 rounded-lg" />
              ))}
            </div>
          ) : (
            <>
              {scans.map((scan) => (
                <div key={scan.id} className="space-y-3">
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                      Scan #{scan.id} — {scan.deals_extracted} deal{scan.deals_extracted !== 1 ? "s" : ""} found
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1 h-7"
                      onClick={() => acceptAllMutation.mutate(scan.id)}
                      disabled={acceptAllMutation.isPending}
                    >
                      <CheckCheck className="h-3.5 w-3.5" />
                      Accept All
                    </Button>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    {(scan.deals ?? [])
                      .filter((d) => d.status === "pending")
                      .map((deal) => (
                        <DealCard
                          key={deal.id}
                          deal={deal}
                          mode="review"
                          onAccept={(id) => acceptMutation.mutate(id)}
                          onDismiss={(id) => dismissMutation.mutate(id)}
                          isPending={acceptMutation.isPending || dismissMutation.isPending}
                        />
                      ))}
                  </div>
                </div>
              ))}
            </>
          )}
        </div>
      )}

      {/* Deal Library */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Deal Library</h2>
          <Select value={storeFilter} onValueChange={setStoreFilter}>
            <SelectTrigger className="w-40 h-8 text-xs">
              <SelectValue placeholder="All stores" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All stores</SelectItem>
              {stores.map((s) => (
                <SelectItem key={s.id} value={s.id.toString()}>
                  {s.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Tabs value={libraryTab} onValueChange={setLibraryTab}>
          <TabsList>
            <TabsTrigger value="active">Active</TabsTrigger>
            <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
            <TabsTrigger value="expired">Expired</TabsTrigger>
          </TabsList>

          <TabsContent value={libraryTab} className="mt-4">
            {dealsLoading ? (
              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className="h-28 rounded-lg" />
                ))}
              </div>
            ) : libraryDeals.length === 0 ? (
              <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
                <TicketPercent className="h-8 w-8 text-muted-foreground mb-3" />
                <p className="text-sm text-muted-foreground">
                  {libraryTab === "active"
                    ? "No active deals. Scan a coupon or add one manually to get started."
                    : libraryTab === "upcoming"
                    ? "No upcoming deals."
                    : "No expired deals."}
                </p>
              </div>
            ) : (
              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                {libraryDeals.map((deal) => (
                  <DealCard key={deal.id} deal={deal} mode="library" />
                ))}
              </div>
            )}
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
}
