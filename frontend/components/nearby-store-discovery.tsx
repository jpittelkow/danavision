"use client";

import { useState, useEffect } from "react";
import { toast } from "sonner";
import { getErrorMessage } from "@/lib/utils";
import {
  MapPin,
  Loader2,
  Store,
  AlertCircle,
  CheckCircle2,
  Search,
  ShoppingCart,
  Tv,
  Package,
  Home,
  Shirt,
  Pill,
  Sparkles,
  Dog,
  X,
  Plus,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { api } from "@/lib/api";
import {
  type NearbyPlace,
  fetchNearbyAvailability,
  previewNearbyStores,
  addNearbyStores,
} from "@/lib/api/shopping";

interface NearbyStoreDiscoveryProps {
  onStoresAdded?: () => void;
}

interface CategoryOption {
  id: string;
  label: string;
  icon: React.ReactNode;
}

const CATEGORIES: CategoryOption[] = [
  { id: "grocery", label: "Grocery Stores", icon: <ShoppingCart className="h-4 w-4" /> },
  { id: "electronics", label: "Electronics", icon: <Tv className="h-4 w-4" /> },
  { id: "pet", label: "Pet Stores", icon: <Dog className="h-4 w-4" /> },
  { id: "pharmacy", label: "Pharmacies", icon: <Pill className="h-4 w-4" /> },
  { id: "home", label: "Home & Hardware", icon: <Home className="h-4 w-4" /> },
  { id: "clothing", label: "Clothing & Apparel", icon: <Shirt className="h-4 w-4" /> },
  { id: "warehouse", label: "Warehouse Clubs", icon: <Package className="h-4 w-4" /> },
  { id: "general", label: "General Retail", icon: <Store className="h-4 w-4" /> },
  { id: "specialty", label: "Specialty Stores", icon: <Sparkles className="h-4 w-4" /> },
];

export function NearbyStoreDiscovery({
  onStoresAdded,
}: NearbyStoreDiscoveryProps) {
  const [open, setOpen] = useState(false);
  const [available, setAvailable] = useState<boolean | null>(null);
  const [checkingAvailability, setCheckingAvailability] = useState(false);
  const [userLat, setUserLat] = useState<number | null>(null);
  const [userLng, setUserLng] = useState<number | null>(null);

  // Config
  const [radiusMiles, setRadiusMiles] = useState(10);
  const [selectedCategories, setSelectedCategories] = useState<string[]>([
    "grocery",
    "electronics",
    "pharmacy",
  ]);

  // Preview
  const [previewStores, setPreviewStores] = useState<NearbyPlace[]>([]);
  const [previewing, setPreviewing] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [previewComplete, setPreviewComplete] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");

  // Selection
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [adding, setAdding] = useState(false);

  // Result
  const [addResult, setAddResult] = useState<{ added: number; skipped: number } | null>(null);

  const filteredPreview = previewStores.filter(
    (s) => !searchQuery || s.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  useEffect(() => {
    if (open && available === null) {
      setCheckingAvailability(true);
      Promise.all([
        fetchNearbyAvailability()
          .then((res) => setAvailable(res.data.data.available))
          .catch(() => setAvailable(false)),
        api.get("/user/settings")
          .then((res) => {
            setUserLat(res.data.home_latitude ?? null);
            setUserLng(res.data.home_longitude ?? null);
          })
          .catch(() => {}),
      ]).finally(() => setCheckingAvailability(false));
    }
  }, [open, available]);

  const toggleCategory = (id: string) => {
    setSelectedCategories((prev) =>
      prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]
    );
  };

  const handlePreview = async () => {
    if (!userLat || !userLng) {
      toast.error("Set your home address in Shopping settings first.");
      return;
    }
    setPreviewing(true);
    setPreviewError(null);
    setPreviewStores([]);
    setPreviewComplete(false);
    setSelectedIds(new Set());
    setAddResult(null);

    try {
      const type = selectedCategories.length === 1 ? selectedCategories[0] : undefined;
      const res = await previewNearbyStores(userLat, userLng, radiusMiles, type);
      const stores = res.data.data || [];
      setPreviewStores(stores);
      setPreviewComplete(true);
      setSelectedIds(new Set(stores.map((s: NearbyPlace) => s.place_id)));
    } catch (error: unknown) {
      setPreviewError(getErrorMessage(error, "Failed to preview nearby stores"));
    } finally {
      setPreviewing(false);
    }
  };

  const handleAddSelected = async () => {
    if (selectedIds.size === 0) return;
    setAdding(true);

    try {
      const placesToAdd = previewStores.filter((s) => selectedIds.has(s.place_id));
      const res = await addNearbyStores(placesToAdd);
      const returnedStores = res.data.data || [];
      setAddResult({ added: returnedStores.length, skipped: 0 });
      onStoresAdded?.();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to add stores"));
    } finally {
      setAdding(false);
    }
  };

  const resetDialog = () => {
    setPreviewStores([]);
    setPreviewError(null);
    setPreviewComplete(false);
    setSelectedIds(new Set());
    setAdding(false);
    setAddResult(null);
    setSearchQuery("");
  };

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) resetDialog();
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <MapPin className="h-4 w-4" />
          Find Nearby Stores
        </Button>
      </DialogTrigger>

      <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <MapPin className="h-5 w-5" />
            Find Nearby Stores
          </DialogTitle>
          <DialogDescription>
            Discover stores near you and add them to your registry for price tracking.
          </DialogDescription>
        </DialogHeader>

        {checkingAvailability ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : !available ? (
          <div className="space-y-4 py-4">
            <div className="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-900">
              <AlertCircle className="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="font-medium text-amber-900 dark:text-amber-100">
                  Configuration Required
                </p>
                <p className="text-sm text-amber-800 dark:text-amber-200">
                  Add your Google Places API key in Configuration and set your home address in Shopping settings.
                </p>
              </div>
            </div>
          </div>
        ) : addResult ? (
          <div className="space-y-4 py-4">
            <div className="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-950/20 rounded-lg border border-green-200 dark:border-green-900">
              <CheckCircle2 className="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="font-medium text-green-900 dark:text-green-100">
                  Done!
                </p>
                <p className="text-sm text-green-800 dark:text-green-200">
                  {addResult.added} store{addResult.added !== 1 ? "s are" : " is"} now in your registry.
                </p>
              </div>
            </div>
            <DialogFooter>
              <Button onClick={() => handleOpenChange(false)}>Done</Button>
            </DialogFooter>
          </div>
        ) : adding ? (
          <div className="flex items-center gap-3 py-8">
            <Loader2 className="h-5 w-5 animate-spin text-primary" />
            <p className="font-medium">Adding {selectedIds.size} stores to your registry...</p>
          </div>
        ) : (
          <div className="space-y-6 py-4">
            {previewError && (
              <div className="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-950/20 rounded-lg border border-red-200 dark:border-red-900">
                <AlertCircle className="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="font-medium text-red-900 dark:text-red-100">Discovery Failed</p>
                  <p className="text-sm text-red-800 dark:text-red-200">{previewError}</p>
                </div>
              </div>
            )}

            {/* Radius */}
            <div className="space-y-3">
              <Label htmlFor="radius">Search Radius</Label>
              <div className="flex items-center gap-4">
                <input
                  id="radius"
                  type="range"
                  min="1"
                  max="50"
                  value={radiusMiles}
                  onChange={(e) => setRadiusMiles(Number(e.target.value))}
                  className="flex-1"
                />
                <span className="text-sm font-medium w-16 text-right">{radiusMiles} miles</span>
              </div>
            </div>

            {/* Categories */}
            <div className="space-y-3">
              <Label>Store Categories</Label>
              <div className="grid grid-cols-2 gap-2">
                {CATEGORIES.map((cat) => (
                  <button
                    key={cat.id}
                    type="button"
                    onClick={() => toggleCategory(cat.id)}
                    className={`flex items-center gap-2 px-3 py-2 rounded-lg border text-sm transition-colors text-left ${
                      selectedCategories.includes(cat.id)
                        ? "border-primary bg-primary/10 text-primary"
                        : "border-border hover:border-primary/50"
                    }`}
                  >
                    {cat.icon}
                    <span className="truncate">{cat.label}</span>
                  </button>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                {selectedCategories.length === 1
                  ? `Filtering by ${selectedCategories[0]}`
                  : "All store types will be searched. Select exactly one category to filter."}
              </p>
            </div>

            {/* Preview Results */}
            {previewComplete && (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <Label>
                    {previewStores.length} stores found
                    {selectedIds.size > 0 && (
                      <span className="ml-1 text-primary">({selectedIds.size} selected)</span>
                    )}
                  </Label>
                  <div className="flex items-center gap-1">
                    {previewStores.length > 0 && (
                      <>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setSelectedIds(new Set(previewStores.map((s) => s.place_id)))}
                          className="h-6 px-2 text-xs"
                          disabled={selectedIds.size === previewStores.length}
                        >
                          Select All
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setSelectedIds(new Set())}
                          className="h-6 px-2 text-xs"
                          disabled={selectedIds.size === 0}
                        >
                          Clear
                        </Button>
                      </>
                    )}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setPreviewStores([]);
                        setPreviewComplete(false);
                        setSelectedIds(new Set());
                        setSearchQuery("");
                      }}
                      className="h-6 px-2"
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  </div>
                </div>

                {previewStores.length > 0 && (
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      placeholder="Search stores..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="pl-9"
                    />
                  </div>
                )}

                {previewStores.length > 0 ? (
                  filteredPreview.length > 0 ? (
                    <div className="max-h-64 overflow-y-auto space-y-1 border rounded-lg p-2">
                      {filteredPreview.map((store) => (
                        <label
                          key={store.place_id}
                          className={`flex items-center gap-3 p-2 rounded cursor-pointer transition-colors ${
                            selectedIds.has(store.place_id)
                              ? "bg-primary/10 border border-primary/30"
                              : "bg-muted/50 hover:bg-muted"
                          }`}
                        >
                          <Checkbox
                            checked={selectedIds.has(store.place_id)}
                            onCheckedChange={() => {
                              setSelectedIds((prev) => {
                                const next = new Set(prev);
                                if (next.has(store.place_id)) {
                                  next.delete(store.place_id);
                                } else {
                                  next.add(store.place_id);
                                }
                                return next;
                              });
                            }}
                            className="flex-shrink-0"
                          />
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-sm truncate">{store.name}</p>
                            <p className="text-xs text-muted-foreground truncate">{store.address}</p>
                          </div>
                          {store.distance_miles != null && (
                            <Badge variant="outline" className="text-xs flex-shrink-0">
                              {store.distance_miles} mi
                            </Badge>
                          )}
                        </label>
                      ))}
                    </div>
                  ) : (
                    <div className="flex items-start gap-2 p-3 bg-muted/50 rounded-lg border">
                      <Search className="h-4 w-4 text-muted-foreground flex-shrink-0 mt-0.5" />
                      <p className="text-sm text-muted-foreground">
                        No stores match &quot;{searchQuery}&quot;.
                      </p>
                    </div>
                  )
                ) : (
                  <div className="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-900">
                    <AlertCircle className="h-4 w-4 text-amber-500 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-amber-800 dark:text-amber-200">
                      No stores found within {radiusMiles} miles. Try increasing the radius or selecting different categories.
                    </p>
                  </div>
                )}
              </div>
            )}

            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={handlePreview} disabled={previewing || adding}>
                {previewing ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Search className="h-4 w-4 mr-2" />
                )}
                {previewComplete ? "Refresh" : "Preview"}
              </Button>
              <Button onClick={handleAddSelected} disabled={adding || selectedIds.size === 0}>
                {adding ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Plus className="h-4 w-4 mr-2" />
                )}
                {previewComplete && selectedIds.size > 0
                  ? `Add Selected (${selectedIds.size})`
                  : "Add Selected"}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

export default NearbyStoreDiscovery;
