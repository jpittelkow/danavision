"use client";

import { useState, useEffect, useCallback, useMemo } from "react";
import { toast } from "sonner";
import { getErrorMessage } from "@/lib/utils";
import {
  Store as StoreIcon,
  Star,
  StarOff,
  Plus,
  MoreVertical,
  Trash2,
  Edit,
  Ban,
  RefreshCw,
  MapPin,
} from "lucide-react";
import {
  type Store,
  fetchStores,
  createStore,
  deleteStore,
  suppressStore,
  restoreStore,
  toggleStoreFavorite,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

function StoreCardSkeleton() {
  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <Skeleton className="h-5 w-32" />
          <Skeleton className="h-8 w-8 rounded" />
        </div>
        <Skeleton className="h-4 w-48 mt-1" />
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between">
          <Skeleton className="h-5 w-16 rounded-full" />
          <Skeleton className="h-5 w-10" />
        </div>
      </CardContent>
    </Card>
  );
}

function StoreCard({
  store,
  onToggleFavorite,
  onSuppress,
  onDelete,
}: {
  store: Store;
  onToggleFavorite: (id: number) => void;
  onSuppress: (id: number) => void;
  onDelete: (id: number) => void;
}) {
  const [enabled, setEnabled] = useState(store.user_enabled ?? store.is_active);

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 min-w-0">
            <StoreIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
            <CardTitle className="text-base truncate">{store.name}</CardTitle>
          </div>
          <div className="flex items-center gap-1 shrink-0">
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={() => onToggleFavorite(store.id)}
              title={store.is_favorite ? "Remove from favorites" : "Add to favorites"}
            >
              {store.is_favorite ? (
                <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
              ) : (
                <StarOff className="h-4 w-4 text-muted-foreground" />
              )}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8">
                  <MoreVertical className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => onSuppress(store.id)}>
                  <Ban className="h-4 w-4 mr-2" />
                  Suppress
                </DropdownMenuItem>
                {!store.is_default && (
                  <DropdownMenuItem
                    onClick={() => onDelete(store.id)}
                    className="text-destructive"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        {store.domain && (
          <CardDescription className="truncate">{store.domain}</CardDescription>
        )}
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {store.category && (
              <Badge variant="secondary" className="text-xs">
                {store.category}
              </Badge>
            )}
            {store.is_local && (
              <Badge variant="outline" className="text-xs">
                <MapPin className="h-3 w-3 mr-1" />
                Local
              </Badge>
            )}
            {store.parent_store && (
              <Badge variant="outline" className="text-xs text-blue-600 border-blue-300">
                Part of {store.parent_store.name}
              </Badge>
            )}
          </div>
          <Switch
            checked={enabled}
            onCheckedChange={(checked) => setEnabled(checked)}
            aria-label={`Enable ${store.name}`}
          />
        </div>
        {store.user_priority != null && (
          <p className="text-xs text-muted-foreground mt-2">
            Priority: {store.user_priority}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

export default function StoresConfigurationPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [suppressedVendors, setSuppressedVendors] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [categoryFilter, setCategoryFilter] = useState<string>("all");
  const [addDialogOpen, setAddDialogOpen] = useState(false);
  const [newStore, setNewStore] = useState({
    name: "",
    domain: "",
    search_url_template: "",
    category: "",
  });

  const loadData = useCallback(async () => {
    setIsLoading(true);
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
    loadData();
  }, [loadData]);

  const categories = useMemo(() => {
    const cats = new Set<string>();
    stores.forEach((s) => {
      if (s.category) cats.add(s.category);
    });
    return Array.from(cats).sort();
  }, [stores]);

  const filteredStores = useMemo(() => {
    if (categoryFilter === "all") return stores;
    return stores.filter((s) => s.category === categoryFilter);
  }, [stores, categoryFilter]);

  const favoriteStores = useMemo(
    () => stores.filter((s) => s.is_favorite),
    [stores]
  );

  const handleToggleFavorite = async (id: number) => {
    try {
      await toggleStoreFavorite(id);
      setStores((prev) =>
        prev.map((s) =>
          s.id === id ? { ...s, is_favorite: !s.is_favorite } : s
        )
      );
      toast.success("Store favorite updated");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update favorite"));
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
      });
      setStores((prev) => [...prev, res.data.data]);
      setNewStore({ name: "", domain: "", search_url_template: "", category: "" });
      setAddDialogOpen(false);
      toast.success("Store created");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to create store"));
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-4 w-72 mt-2" />
        </div>
        <Skeleton className="h-10 w-64" />
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <StoreCardSkeleton key={i} />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Store Management</h1>
        <p className="text-muted-foreground mt-2">
          Manage your store preferences, favorites, and discovery settings.
        </p>
      </div>

      <Tabs defaultValue="all" className="space-y-6">
        <TabsList>
          <TabsTrigger value="all">All Stores</TabsTrigger>
          <TabsTrigger value="favorites">Favorites</TabsTrigger>
          <TabsTrigger value="suppressed">Suppressed</TabsTrigger>
        </TabsList>

        {/* All Stores Tab */}
        <TabsContent value="all">
          <div className="space-y-4">
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
              <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="Filter by category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Categories</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat} value={cat}>
                      {cat}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogTrigger asChild>
                  <Button>
                    <Plus className="h-4 w-4 mr-2" />
                    Add Custom Store
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Add Custom Store</DialogTitle>
                    <DialogDescription>
                      Add a new store for price tracking and comparison.
                    </DialogDescription>
                  </DialogHeader>
                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="store-name">Name *</Label>
                      <Input
                        id="store-name"
                        value={newStore.name}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, name: e.target.value }))
                        }
                        placeholder="Store name"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="store-domain">Domain</Label>
                      <Input
                        id="store-domain"
                        value={newStore.domain}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, domain: e.target.value }))
                        }
                        placeholder="example.com"
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
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="store-category">Category</Label>
                      <Input
                        id="store-category"
                        value={newStore.category}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, category: e.target.value }))
                        }
                        placeholder="e.g., Grocery, Electronics"
                      />
                    </div>
                  </div>
                  <DialogFooter>
                    <Button
                      variant="outline"
                      onClick={() => setAddDialogOpen(false)}
                    >
                      Cancel
                    </Button>
                    <Button onClick={handleAddStore} disabled={isSaving}>
                      {isSaving ? "Creating..." : "Create Store"}
                    </Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>

            {filteredStores.length === 0 ? (
              <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                  <StoreIcon className="h-12 w-12 text-muted-foreground mb-4" />
                  <p className="text-muted-foreground">No stores found.</p>
                </CardContent>
              </Card>
            ) : (
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {filteredStores.map((store) => (
                  <StoreCard
                    key={store.id}
                    store={store}
                    onToggleFavorite={handleToggleFavorite}
                    onSuppress={handleSuppress}
                    onDelete={handleDelete}
                  />
                ))}
              </div>
            )}
          </div>
        </TabsContent>

        {/* Favorites Tab */}
        <TabsContent value="favorites">
          {favoriteStores.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center py-12">
                <Star className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-muted-foreground">
                  No favorite stores yet. Star a store to add it here.
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {favoriteStores.map((store) => (
                <StoreCard
                  key={store.id}
                  store={store}
                  onToggleFavorite={handleToggleFavorite}
                  onSuppress={handleSuppress}
                  onDelete={handleDelete}
                />
              ))}
            </div>
          )}
        </TabsContent>

        {/* Suppressed Tab */}
        <TabsContent value="suppressed">
          {suppressedVendors.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center py-12">
                <Ban className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-muted-foreground">
                  No suppressed stores.
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-2">
              {stores
                .filter((s) => s.user_enabled === false)
                .map((store) => (
                  <Card key={store.id}>
                    <CardContent className="flex items-center justify-between py-4">
                      <div className="flex items-center gap-3">
                        <StoreIcon className="h-4 w-4 text-muted-foreground" />
                        <div>
                          <p className="font-medium">{store.name}</p>
                          {store.domain && (
                            <p className="text-sm text-muted-foreground">
                              {store.domain}
                            </p>
                          )}
                        </div>
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleRestore(store.id)}
                      >
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Restore
                      </Button>
                    </CardContent>
                  </Card>
                ))}
            </div>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
