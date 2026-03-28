"use client";

import { useState, useEffect, useCallback, useMemo, type ReactNode } from "react";
import { toast } from "sonner";
import { getErrorMessage } from "@/lib/utils";
import {
  Store as StoreIcon,
  Star,
  Plus,
  Loader2,
  GripVertical,
  Globe,
  ShoppingCart,
  Tv,
  Home,
  Shirt,
  Pill,
  Package,
  Sparkles,
  RotateCcw,
  Search,
  MapPin,
  Dog,
  Pencil,
  Trash2,
  Ban,
  Link,
} from "lucide-react";
import {
  type Store,
  fetchStores,
  createStore,
  updateStore as updateStoreApi,
  deleteStore,
  suppressStore,
  restoreStore,
  toggleStoreFavorite,
  updateStorePreferences,
  fetchSuppressedVendors,
} from "@/lib/api/shopping";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
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
import { NearbyStoreDiscovery } from "@/components/nearby-store-discovery";

// ---------------------------------------------------------------------------
// Category icons
// ---------------------------------------------------------------------------

const categoryIcons: Record<string, ReactNode> = {
  general: <ShoppingCart className="h-4 w-4" />,
  General: <ShoppingCart className="h-4 w-4" />,
  electronics: <Tv className="h-4 w-4" />,
  Electronics: <Tv className="h-4 w-4" />,
  grocery: <Package className="h-4 w-4" />,
  Grocery: <Package className="h-4 w-4" />,
  home: <Home className="h-4 w-4" />,
  Home: <Home className="h-4 w-4" />,
  clothing: <Shirt className="h-4 w-4" />,
  Clothing: <Shirt className="h-4 w-4" />,
  pharmacy: <Pill className="h-4 w-4" />,
  Pharmacy: <Pill className="h-4 w-4" />,
  warehouse: <Package className="h-4 w-4" />,
  Warehouse: <Package className="h-4 w-4" />,
  pet: <Dog className="h-4 w-4" />,
  Pet: <Dog className="h-4 w-4" />,
  specialty: <Sparkles className="h-4 w-4" />,
  Specialty: <Sparkles className="h-4 w-4" />,
};

// ---------------------------------------------------------------------------
// StoreRow
// ---------------------------------------------------------------------------

interface StoreRowProps {
  store: Store;
  loading: boolean;
  onToggleEnabled: (id: number, enabled: boolean) => void;
  onToggleFavorite: (id: number) => void;
  onEdit: (store: Store) => void;
  onSuppress: (id: number) => void;
  onDelete: (id: number) => void;
}

function StoreRow({
  store,
  loading,
  onToggleEnabled,
  onToggleFavorite,
  onEdit,
  onSuppress,
  onDelete,
}: StoreRowProps) {
  const enabled = store.user_enabled ?? store.is_active;

  return (
    <div
      className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
        enabled
          ? "border-border bg-background"
          : "border-border/50 bg-muted/30 opacity-60"
      }`}
    >
      {/* Drag handle placeholder */}
      <div className="cursor-grab text-muted-foreground/50 hidden sm:block">
        <GripVertical className="h-4 w-4" />
      </div>

      {/* Store info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="font-medium text-foreground truncate">{store.name}</span>
          {store.is_favorite && (
            <Star className="h-4 w-4 fill-yellow-500 text-yellow-500 flex-shrink-0" />
          )}
          {store.is_local && (
            <Badge variant="outline" className="text-xs flex-shrink-0 text-green-600 border-green-300">
              <MapPin className="mr-1 h-3 w-3" />
              Local
            </Badge>
          )}
          {store.search_url_template && (
            <Badge variant="secondary" className="text-xs flex-shrink-0">
              Fast
            </Badge>
          )}
          {store.is_default && (
            <Badge variant="outline" className="text-xs flex-shrink-0 text-muted-foreground">
              Default
            </Badge>
          )}
          {store.parent_store && (
            <Badge variant="outline" className="text-xs flex-shrink-0 text-blue-600 border-blue-200">
              <Link className="mr-1 h-3 w-3" />
              {store.parent_store.name}
            </Badge>
          )}
        </div>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          {store.domain && <span className="truncate">{store.domain}</span>}
          {store.category && (
            <>
              {store.domain && <span>·</span>}
              <span className="flex items-center gap-1">
                {categoryIcons[store.category]}
                {store.category}
              </span>
            </>
          )}
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-1">
        {/* Favorite */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onToggleFavorite(store.id)}
          disabled={loading}
          className={`h-8 w-8 p-0 ${store.is_favorite ? "text-yellow-500 hover:text-yellow-600" : "text-muted-foreground"}`}
          title={store.is_favorite ? "Remove from favorites" : "Add to favorites"}
        >
          <Star className={`h-4 w-4 ${store.is_favorite ? "fill-current" : ""}`} />
        </Button>

        {/* Edit */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onEdit(store)}
          disabled={loading}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
          title="Edit store"
        >
          <Pencil className="h-4 w-4" />
        </Button>

        {/* Suppress */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onSuppress(store.id)}
          disabled={loading}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-red-600"
          title="Suppress store"
        >
          <Ban className="h-4 w-4" />
        </Button>

        {/* Delete (non-default only) */}
        {!store.is_default && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onDelete(store.id)}
            disabled={loading}
            className="h-8 w-8 p-0 text-muted-foreground hover:text-red-600"
            title="Delete store"
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        )}

        {/* Enable/disable */}
        <Switch
          checked={enabled}
          onCheckedChange={(checked) => onToggleEnabled(store.id, checked)}
          disabled={loading}
          className="ml-1"
          aria-label={`Enable ${store.name}`}
        />

        {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground ml-1" />}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function StoresConfigurationPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [suppressedVendors, setSuppressedVendors] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadingStores, setLoadingStores] = useState<Record<number, boolean>>({});

  // Filters
  const [searchQuery, setSearchQuery] = useState("");
  const [categoryFilter, setCategoryFilter] = useState<string>("all");
  const [localOnly, setLocalOnly] = useState(false);

  // Add dialog
  const [addDialogOpen, setAddDialogOpen] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [newStore, setNewStore] = useState({
    name: "",
    domain: "",
    search_url_template: "",
    category: "",
    is_local: false,
  });

  // Edit dialog
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [editingStore, setEditingStore] = useState<Store | null>(null);
  const [editForm, setEditForm] = useState({
    name: "",
    domain: "",
    search_url_template: "",
    category: "",
    is_local: false,
  });
  const [savingEdit, setSavingEdit] = useState(false);

  // ---------------------------------------------------------------------------
  // Data loading
  // ---------------------------------------------------------------------------

  const loadData = useCallback(async (showSkeleton = false) => {
    if (showSkeleton) setIsLoading(true);
    try {
      const [storesRes, suppressedRes] = await Promise.all([
        fetchStores(),
        fetchSuppressedVendors(),
      ]);
      setStores(storesRes.data.data);
      setSuppressedVendors(suppressedRes.data.data);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to load stores"));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData(true);
  }, [loadData]);

  // ---------------------------------------------------------------------------
  // Derived data
  // ---------------------------------------------------------------------------

  const categories = useMemo(() => {
    const cats = new Set<string>();
    stores.forEach((s) => {
      if (s.category) cats.add(s.category);
    });
    return Array.from(cats).sort();
  }, [stores]);

  const filteredStores = useMemo(() => {
    return stores
      .filter((s) => {
        if (searchQuery) {
          const q = searchQuery.toLowerCase();
          if (
            !s.name.toLowerCase().includes(q) &&
            !(s.domain && s.domain.toLowerCase().includes(q))
          ) {
            return false;
          }
        }
        if (categoryFilter !== "all" && s.category !== categoryFilter) return false;
        if (localOnly && !s.is_local) return false;
        return true;
      })
      .sort((a, b) => {
        // Favorites first
        if (a.is_favorite && !b.is_favorite) return -1;
        if (!a.is_favorite && b.is_favorite) return 1;
        // Then by priority
        return (a.user_priority ?? 99) - (b.user_priority ?? 99);
      });
  }, [stores, searchQuery, categoryFilter, localOnly]);

  const enabledCount = useMemo(
    () => stores.filter((s) => (s.user_enabled ?? s.is_active)).length,
    [stores]
  );
  const favoriteCount = useMemo(
    () => stores.filter((s) => s.is_favorite).length,
    [stores]
  );
  const localCount = useMemo(
    () => stores.filter((s) => s.is_local).length,
    [stores]
  );

  const suppressedStores = useMemo(
    () => stores.filter((s) => s.user_enabled === false),
    [stores]
  );

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleToggleEnabled = async (id: number, enabled: boolean) => {
    setStores((prev) =>
      prev.map((s) => (s.id === id ? { ...s, user_enabled: enabled } : s))
    );
    try {
      await updateStorePreferences([{ store_id: id, enabled }]);
    } catch (error: unknown) {
      setStores((prev) =>
        prev.map((s) => (s.id === id ? { ...s, user_enabled: !enabled } : s))
      );
      toast.error(getErrorMessage(error, "Failed to update store"));
    }
  };

  const handleToggleFavorite = async (id: number) => {
    setLoadingStores((prev) => ({ ...prev, [id]: true }));
    try {
      await toggleStoreFavorite(id);
      setStores((prev) =>
        prev.map((s) =>
          s.id === id ? { ...s, is_favorite: !s.is_favorite } : s
        )
      );
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update favorite"));
    } finally {
      setLoadingStores((prev) => ({ ...prev, [id]: false }));
    }
  };

  const handleSuppress = async (id: number) => {
    try {
      await suppressStore(id);
      toast.success("Store suppressed");
      await loadData();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to suppress store"));
    }
  };

  const handleRestore = async (id: number) => {
    try {
      await restoreStore(id);
      toast.success("Store restored");
      await loadData();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to restore store"));
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await deleteStore(id);
      toast.success("Store deleted");
      setStores((prev) => prev.filter((s) => s.id !== id));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to delete store"));
    }
  };

  const handleAddStore = async () => {
    if (!newStore.name.trim()) {
      toast.error("Store name is required");
      return;
    }
    setIsSaving(true);
    try {
      const res = await createStore({
        name: newStore.name.trim(),
        domain: newStore.domain.trim() || undefined,
        search_url_template: newStore.search_url_template.trim() || undefined,
        category: newStore.category.trim() || undefined,
        is_local: newStore.is_local || undefined,
      });
      setStores((prev) => [...prev, res.data.data]);
      setNewStore({ name: "", domain: "", search_url_template: "", category: "", is_local: false });
      setAddDialogOpen(false);
      toast.success("Store created");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to create store"));
    } finally {
      setIsSaving(false);
    }
  };

  const handleOpenEdit = (store: Store) => {
    setEditingStore(store);
    setEditForm({
      name: store.name,
      domain: store.domain || "",
      search_url_template: store.search_url_template || "",
      category: store.category || "",
      is_local: store.is_local,
    });
    setEditDialogOpen(true);
  };

  const handleSaveEdit = async () => {
    if (!editingStore || !editForm.name.trim()) return;
    setSavingEdit(true);
    try {
      const res = await updateStoreApi(editingStore.id, {
        name: editForm.name.trim(),
        domain: editForm.domain.trim() || undefined,
        search_url_template: editForm.search_url_template.trim() || undefined,
        category: editForm.category.trim() || undefined,
        is_local: editForm.is_local,
      } as Partial<Store>);
      const updated = res.data.data;
      setStores((prev) =>
        prev.map((s) =>
          s.id === editingStore.id
            ? { ...s, ...updated }
            : s
        )
      );
      setEditDialogOpen(false);
      setEditingStore(null);
      toast.success("Store updated");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update store"));
    } finally {
      setSavingEdit(false);
    }
  };

  const handleSuppressFromEdit = async () => {
    if (!editingStore) return;
    setEditDialogOpen(false);
    await handleSuppress(editingStore.id);
    setEditingStore(null);
  };

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-4 w-72 mt-2" />
        </div>
        <Skeleton className="h-10 w-full" />
        <div className="space-y-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full rounded-lg" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header with stats */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Store Registry</h1>
          <p className="text-muted-foreground mt-1">
            Manage which stores to search for prices. Enabled stores are searched during price discovery.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="gap-1">
            <StoreIcon className="h-3 w-3" />
            {enabledCount} enabled
          </Badge>
          <Badge variant="outline" className="gap-1">
            <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
            {favoriteCount} favorites
          </Badge>
          <Badge variant="outline" className="gap-1">
            <MapPin className="h-3 w-3 text-green-600" />
            {localCount} local
          </Badge>
        </div>
      </div>

      {/* Filters and Actions */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-1 gap-2 flex-wrap">
              <div className="relative flex-1 min-w-[160px] max-w-xs">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Search stores..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-9"
                />
              </div>
              <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                <SelectTrigger className="w-40">
                  <SelectValue placeholder="All categories" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Categories</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat} value={cat}>
                      <span className="flex items-center gap-2">
                        {categoryIcons[cat]}
                        {cat}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                variant={localOnly ? "default" : "outline"}
                size="sm"
                onClick={() => setLocalOnly(!localOnly)}
                className="gap-1"
              >
                <MapPin className="h-4 w-4" />
                <span className="hidden sm:inline">Local Only</span>
              </Button>
            </div>

            <div className="flex gap-2">
              <NearbyStoreDiscovery onStoresAdded={loadData} />

              <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogTrigger asChild>
                  <Button size="sm">
                    <Plus className="h-4 w-4 mr-2" />
                    Add Store
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Add Custom Store</DialogTitle>
                    <DialogDescription>
                      Add a store that isn&apos;t in the registry. You can add local shops, specialty retailers, or any online store.
                    </DialogDescription>
                  </DialogHeader>
                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="store-name">Store Name *</Label>
                      <Input
                        id="store-name"
                        value={newStore.name}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, name: e.target.value }))
                        }
                        placeholder="e.g., My Local Shop"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="store-domain">Website Domain</Label>
                      <Input
                        id="store-domain"
                        value={newStore.domain}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, domain: e.target.value }))
                        }
                        placeholder="e.g., mylocalshop.com"
                      />
                      <p className="text-xs text-muted-foreground">
                        Just the domain name, no https:// needed
                      </p>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="store-category">Category</Label>
                      <Select
                        value={newStore.category || "none"}
                        onValueChange={(v) =>
                          setNewStore((prev) => ({ ...prev, category: v === "none" ? "" : v }))
                        }
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select category" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="none">None</SelectItem>
                          {categories.map((cat) => (
                            <SelectItem key={cat} value={cat}>
                              <span className="flex items-center gap-2">
                                {categoryIcons[cat]}
                                {cat}
                              </span>
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex items-center justify-between">
                      <div className="space-y-0.5">
                        <Label>Local Store</Label>
                        <p className="text-xs text-muted-foreground">
                          Is this a local or regional store?
                        </p>
                      </div>
                      <Switch
                        checked={newStore.is_local}
                        onCheckedChange={(checked) =>
                          setNewStore((prev) => ({ ...prev, is_local: checked }))
                        }
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="store-url-template">Search URL Template</Label>
                      <Input
                        id="store-url-template"
                        value={newStore.search_url_template}
                        onChange={(e) =>
                          setNewStore((prev) => ({
                            ...prev,
                            search_url_template: e.target.value,
                          }))
                        }
                        placeholder="https://example.com/search?q={query}"
                      />
                      <p className="text-xs text-muted-foreground">
                        Use {"{query}"} where the search term should go. Leave blank to discover automatically.
                      </p>
                    </div>
                  </div>
                  <DialogFooter>
                    <Button variant="outline" onClick={() => setAddDialogOpen(false)}>
                      Cancel
                    </Button>
                    <Button onClick={handleAddStore} disabled={isSaving || !newStore.name.trim()}>
                      {isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                      Add Store
                    </Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Store List */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Available Stores</CardTitle>
          <CardDescription>
            {filteredStores.length} store{filteredStores.length !== 1 ? "s" : ""} shown
            {searchQuery && ` matching "${searchQuery}"`}
            {categoryFilter !== "all" && ` in ${categoryFilter}`}
            {localOnly && " (local only)"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {filteredStores.length === 0 ? (
              <div className="py-8 text-center text-muted-foreground">
                {searchQuery || categoryFilter !== "all" || localOnly ? (
                  <p>No stores match your filters</p>
                ) : (
                  <p>No stores available. Add a custom store to get started.</p>
                )}
              </div>
            ) : (
              filteredStores.map((store) => (
                <StoreRow
                  key={store.id}
                  store={store}
                  loading={loadingStores[store.id] || false}
                  onToggleEnabled={handleToggleEnabled}
                  onToggleFavorite={handleToggleFavorite}
                  onEdit={handleOpenEdit}
                  onSuppress={handleSuppress}
                  onDelete={handleDelete}
                />
              ))
            )}
          </div>
        </CardContent>
      </Card>

      {/* Info Card */}
      <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/20">
        <CardContent className="pt-6">
          <div className="flex gap-4">
            <div className="flex-shrink-0">
              <Globe className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div className="space-y-1 text-sm">
              <p className="font-medium text-blue-900 dark:text-blue-100">
                How Store Registry Works
              </p>
              <ul className="list-inside list-disc space-y-1 text-blue-800 dark:text-blue-200">
                <li>
                  <strong>Enabled stores</strong> are searched when you add a product
                </li>
                <li>
                  <strong>Favorite stores</strong> are searched first and shown at the top
                </li>
                <li>
                  Stores with a <Badge variant="outline" className="mx-1 text-xs">Fast</Badge> badge have URL templates for faster, cheaper searches
                </li>
                <li>
                  Custom stores without templates will be discovered using AI search
                </li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Suppressed Vendors */}
      {suppressedVendors.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Ban className="h-5 w-5" />
              Suppressed Vendors
            </CardTitle>
            <CardDescription>
              These vendors are hidden from price results and comparisons. Suppress or restore stores above to manage this list.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {suppressedVendors.map((vendor, index) => (
                <Badge
                  key={index}
                  variant="secondary"
                  className="flex items-center gap-1 px-3 py-1"
                >
                  <StoreIcon className="h-3 w-3" />
                  {vendor}
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Suppressed Stores */}
      {suppressedStores.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Ban className="h-5 w-5" />
              Suppressed Stores
            </CardTitle>
            <CardDescription>
              These stores have been hidden from your registry. Click restore to add them back.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {suppressedStores.map((store) => (
                <Badge
                  key={store.id}
                  variant="secondary"
                  className="flex items-center gap-2 px-3 py-1.5"
                >
                  <StoreIcon className="h-3 w-3" />
                  <span>{store.name}</span>
                  {store.domain && (
                    <span className="text-xs text-muted-foreground">({store.domain})</span>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-5 w-5 p-0 ml-1 hover:text-green-600 hover:bg-green-100"
                    onClick={() => handleRestore(store.id)}
                    title="Restore store"
                  >
                    <RotateCcw className="h-3 w-3" />
                  </Button>
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Edit Store Dialog */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Edit Store</DialogTitle>
            <DialogDescription>
              Update the store details.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="edit-store-name">Store Name</Label>
              <Input
                id="edit-store-name"
                value={editForm.name}
                onChange={(e) => setEditForm((prev) => ({ ...prev, name: e.target.value }))}
                placeholder="Store name"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-store-domain">Website Domain</Label>
              <Input
                id="edit-store-domain"
                value={editForm.domain}
                onChange={(e) => setEditForm((prev) => ({ ...prev, domain: e.target.value }))}
                placeholder="e.g., mylocalshop.com"
              />
              <p className="text-xs text-muted-foreground">
                Just the domain name, no https:// needed
              </p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-store-category">Category</Label>
              <Select
                value={editForm.category || "none"}
                onValueChange={(v) =>
                  setEditForm((prev) => ({ ...prev, category: v === "none" ? "" : v }))
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat} value={cat}>
                      <span className="flex items-center gap-2">
                        {categoryIcons[cat]}
                        {cat}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label>Local Store</Label>
                <p className="text-xs text-muted-foreground">
                  Is this a local or regional store?
                </p>
              </div>
              <Switch
                checked={editForm.is_local}
                onCheckedChange={(checked) =>
                  setEditForm((prev) => ({ ...prev, is_local: checked }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-store-template">Search URL Template</Label>
              <Input
                id="edit-store-template"
                value={editForm.search_url_template}
                onChange={(e) =>
                  setEditForm((prev) => ({ ...prev, search_url_template: e.target.value }))
                }
                placeholder="https://example.com/search?q={query}"
              />
              <p className="text-xs text-muted-foreground">
                Use {"{query}"} where the search term should go.
              </p>
            </div>

            {/* Parent Store Info */}
            {editingStore?.parent_store && (
              <div className="rounded-lg border bg-muted/50 p-3">
                <div className="flex items-center gap-2 text-sm">
                  <Link className="h-4 w-4 text-blue-600" />
                  <span className="font-medium">Linked to {editingStore.parent_store.name}</span>
                </div>
                <p className="mt-1 text-xs text-muted-foreground">
                  This store uses {editingStore.parent_store.name}&apos;s search functionality for price discovery.
                </p>
              </div>
            )}
          </div>
          <DialogFooter className="flex-col sm:flex-row gap-2">
            <Button
              variant="destructive"
              onClick={handleSuppressFromEdit}
              disabled={savingEdit}
              className="sm:mr-auto"
            >
              <Ban className="mr-2 h-4 w-4" />
              Suppress
            </Button>
            <Button variant="outline" onClick={() => setEditDialogOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSaveEdit} disabled={savingEdit || !editForm.name.trim()}>
              {savingEdit && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
