import { useState, useCallback, type ReactNode } from 'react';
import axios from 'axios';
import type { Store, StoreCategory } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
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
} from 'lucide-react';
import { NearbyStoreDiscovery } from '@/Components/NearbyStoreDiscovery';

interface StorePreferencesProps {
  stores: Store[];
  storeCategories: Record<StoreCategory, string>;
  onUpdate?: () => void;
}

const categoryIcons: Record<string, ReactNode> = {
  general: <ShoppingCart className="h-4 w-4" />,
  electronics: <Tv className="h-4 w-4" />,
  grocery: <Package className="h-4 w-4" />,
  home: <Home className="h-4 w-4" />,
  clothing: <Shirt className="h-4 w-4" />,
  pharmacy: <Pill className="h-4 w-4" />,
  warehouse: <Package className="h-4 w-4" />,
  pet: <Dog className="h-4 w-4" />,
  specialty: <Sparkles className="h-4 w-4" />,
};

/**
 * StorePreferences Component
 *
 * Allows users to manage their store preferences for the Store Registry system.
 * Users can enable/disable stores, mark favorites, reorder priorities, and add custom stores.
 */
export function StorePreferences({ stores: initialStores, storeCategories, onUpdate }: StorePreferencesProps) {
  const [stores, setStores] = useState<Store[]>(initialStores);
  const [loading, setLoading] = useState<Record<number, boolean>>({});
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [localOnly, setLocalOnly] = useState(false);
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [isResetting, setIsResetting] = useState(false);

  // Add custom store form state
  const [newStore, setNewStore] = useState({
    name: '',
    domain: '',
    search_url_template: '',
    category: 'specialty' as StoreCategory,
    is_local: false,
  });
  const [addingStore, setAddingStore] = useState(false);

  // Filter and sort stores
  const filteredStores = stores
    .filter((store) => {
      // Search filter
      if (searchQuery) {
        const query = searchQuery.toLowerCase();
        if (
          !store.name.toLowerCase().includes(query) &&
          !store.domain.toLowerCase().includes(query)
        ) {
          return false;
        }
      }
      // Category filter
      if (categoryFilter !== 'all' && store.category !== categoryFilter) {
        return false;
      }
      // Local only filter
      if (localOnly && !store.is_local) {
        return false;
      }
      return true;
    })
    .sort((a, b) => {
      // Favorites first
      if (a.is_favorite && !b.is_favorite) return -1;
      if (!a.is_favorite && b.is_favorite) return 1;
      // Then by priority
      return b.priority - a.priority;
    });

  const enabledCount = stores.filter((s) => s.enabled).length;
  const favoriteCount = stores.filter((s) => s.is_favorite).length;
  const localCount = stores.filter((s) => s.is_local).length;

  // Toggle store enabled state
  const handleToggleEnabled = useCallback(async (storeId: number, enabled: boolean) => {
    setLoading((prev) => ({ ...prev, [storeId]: true }));
    try {
      await axios.patch(`/api/stores/${storeId}/preference`, { enabled });
      setStores((prev) =>
        prev.map((s) => (s.id === storeId ? { ...s, enabled } : s))
      );
      onUpdate?.();
    } catch (err) {
      console.error('Failed to update store preference:', err);
    } finally {
      setLoading((prev) => ({ ...prev, [storeId]: false }));
    }
  }, [onUpdate]);

  // Toggle store favorite state
  const handleToggleFavorite = useCallback(async (storeId: number) => {
    setLoading((prev) => ({ ...prev, [storeId]: true }));
    try {
      const response = await axios.post(`/api/stores/${storeId}/favorite`);
      setStores((prev) =>
        prev.map((s) =>
          s.id === storeId ? { ...s, is_favorite: response.data.is_favorite } : s
        )
      );
      onUpdate?.();
    } catch (err) {
      console.error('Failed to toggle favorite:', err);
    } finally {
      setLoading((prev) => ({ ...prev, [storeId]: false }));
    }
  }, [onUpdate]);

  // Toggle store local state
  const handleToggleLocal = useCallback(async (storeId: number) => {
    setLoading((prev) => ({ ...prev, [storeId]: true }));
    try {
      const response = await axios.post(`/api/stores/${storeId}/local`);
      setStores((prev) =>
        prev.map((s) =>
          s.id === storeId ? { ...s, is_local: response.data.is_local } : s
        )
      );
      onUpdate?.();
    } catch (err) {
      console.error('Failed to toggle local:', err);
    } finally {
      setLoading((prev) => ({ ...prev, [storeId]: false }));
    }
  }, [onUpdate]);

  // Add custom store
  const handleAddStore = async () => {
    if (!newStore.name || !newStore.domain) {
      return;
    }

    setAddingStore(true);
    try {
      const response = await axios.post('/api/stores', newStore);
      const addedStore = response.data.store;

      // Add to local state
      setStores((prev) => [
        ...prev,
        {
          ...addedStore,
          enabled: true,
          is_favorite: true,
          priority: 100,
          default_priority: 50,
        },
      ]);

      // Reset form
      setNewStore({
        name: '',
        domain: '',
        search_url_template: '',
        category: 'specialty',
        is_local: false,
      });
      setShowAddDialog(false);
      onUpdate?.();
    } catch (err) {
      console.error('Failed to add store:', err);
    } finally {
      setAddingStore(false);
    }
  };

  // Reset all preferences
  const handleResetPreferences = async () => {
    if (!confirm('Are you sure you want to reset all store preferences to defaults?')) {
      return;
    }

    setIsResetting(true);
    try {
      await axios.post('/api/stores/reset');
      // Reload page to get fresh data
      window.location.reload();
    } catch (err) {
      console.error('Failed to reset preferences:', err);
    } finally {
      setIsResetting(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header with stats */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-semibold text-foreground">Store Registry</h2>
          <p className="text-sm text-muted-foreground">
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
            <div className="flex flex-1 gap-2">
              <div className="relative flex-1 max-w-xs">
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
                  {Object.entries(storeCategories).map(([key, label]) => (
                    <SelectItem key={key} value={key}>
                      <span className="flex items-center gap-2">
                        {categoryIcons[key]}
                        {label}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                variant={localOnly ? 'default' : 'outline'}
                size="sm"
                onClick={() => setLocalOnly(!localOnly)}
                className="gap-1"
              >
                <MapPin className="h-4 w-4" />
                <span className="hidden sm:inline">Local Only</span>
              </Button>
            </div>

            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleResetPreferences}
                disabled={isResetting}
              >
                {isResetting ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <RotateCcw className="h-4 w-4" />
                )}
                <span className="ml-2 hidden sm:inline">Reset</span>
              </Button>

              <NearbyStoreDiscovery
                onStoresAdded={() => {
                  // Refresh the stores list
                  window.location.reload();
                }}
              />

              <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
                <DialogTrigger asChild>
                  <Button size="sm">
                    <Plus className="h-4 w-4" />
                    <span className="ml-2">Add Store</span>
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Add Custom Store</DialogTitle>
                    <DialogDescription>
                      Add a store that isn't in our registry. You can add local shops, specialty retailers, or any online store.
                    </DialogDescription>
                  </DialogHeader>

                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="store-name">Store Name</Label>
                      <Input
                        id="store-name"
                        placeholder="e.g., My Local Shop"
                        value={newStore.name}
                        onChange={(e) => setNewStore((prev) => ({ ...prev, name: e.target.value }))}
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="store-domain">Website Domain</Label>
                      <Input
                        id="store-domain"
                        placeholder="e.g., mylocalshop.com"
                        value={newStore.domain}
                        onChange={(e) => setNewStore((prev) => ({ ...prev, domain: e.target.value }))}
                      />
                      <p className="text-xs text-muted-foreground">
                        Just the domain name, no https:// needed
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="store-category">Category</Label>
                      <Select
                        value={newStore.category}
                        onValueChange={(v) => setNewStore((prev) => ({ ...prev, category: v as StoreCategory }))}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {Object.entries(storeCategories).map(([key, label]) => (
                            <SelectItem key={key} value={key}>
                              <span className="flex items-center gap-2">
                                {categoryIcons[key]}
                                {label}
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
                      <Label htmlFor="store-template">Search URL Template (Optional)</Label>
                      <Input
                        id="store-template"
                        placeholder="e.g., https://shop.com/search?q={query}"
                        value={newStore.search_url_template}
                        onChange={(e) =>
                          setNewStore((prev) => ({ ...prev, search_url_template: e.target.value }))
                        }
                      />
                      <p className="text-xs text-muted-foreground">
                        Use {'{query}'} where the search term should go. Leave blank to discover automatically.
                      </p>
                    </div>
                  </div>

                  <DialogFooter>
                    <Button variant="outline" onClick={() => setShowAddDialog(false)}>
                      Cancel
                    </Button>
                    <Button onClick={handleAddStore} disabled={addingStore || !newStore.name || !newStore.domain}>
                      {addingStore && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
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
            {filteredStores.length} store{filteredStores.length !== 1 ? 's' : ''} shown
            {searchQuery && ` matching "${searchQuery}"`}
            {categoryFilter !== 'all' && ` in ${storeCategories[categoryFilter as StoreCategory]}`}
            {localOnly && ' (local only)'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {filteredStores.length === 0 ? (
              <div className="py-8 text-center text-muted-foreground">
                {searchQuery || categoryFilter !== 'all' || localOnly ? (
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
                  loading={loading[store.id] || false}
                  categoryLabel={storeCategories[store.category as StoreCategory]}
                  onToggleEnabled={handleToggleEnabled}
                  onToggleFavorite={handleToggleFavorite}
                  onToggleLocal={handleToggleLocal}
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
                  Stores with <Badge variant="outline" className="ml-1 text-xs">URL template</Badge> are searched much faster and cheaper
                </li>
                <li>
                  Custom stores you add will be discovered using our AI search
                </li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

interface StoreRowProps {
  store: Store;
  loading: boolean;
  categoryLabel?: string;
  onToggleEnabled: (storeId: number, enabled: boolean) => void;
  onToggleFavorite: (storeId: number) => void;
  onToggleLocal: (storeId: number) => void;
}

function StoreRow({ store, loading, categoryLabel, onToggleEnabled, onToggleFavorite, onToggleLocal }: StoreRowProps) {
  return (
    <div
      className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
        store.enabled
          ? 'border-border bg-background'
          : 'border-border/50 bg-muted/30 opacity-60'
      }`}
    >
      {/* Drag handle (for future drag-and-drop) */}
      <div className="cursor-grab text-muted-foreground/50">
        <GripVertical className="h-4 w-4" />
      </div>

      {/* Store info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="font-medium text-foreground truncate">{store.name}</span>
          {store.is_favorite && (
            <Star className="h-4 w-4 fill-yellow-500 text-yellow-500 flex-shrink-0" />
          )}
          {store.is_local && (
            <Badge variant="outline" className="text-xs flex-shrink-0">
              <MapPin className="mr-1 h-3 w-3" />
              Local
            </Badge>
          )}
          {store.has_search_template && (
            <Badge variant="secondary" className="text-xs flex-shrink-0">
              Fast
            </Badge>
          )}
        </div>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span className="truncate">{store.domain}</span>
          {categoryLabel && (
            <>
              <span>•</span>
              <span className="flex items-center gap-1">
                {categoryIcons[store.category || 'general']}
                {categoryLabel}
              </span>
            </>
          )}
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2">
        {/* Local button */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onToggleLocal(store.id)}
          disabled={loading}
          className={store.is_local ? 'text-green-600 hover:text-green-700' : 'text-muted-foreground'}
          title={store.is_local ? 'Mark as non-local' : 'Mark as local'}
        >
          <MapPin className={`h-4 w-4 ${store.is_local ? 'fill-current' : ''}`} />
        </Button>

        {/* Favorite button */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onToggleFavorite(store.id)}
          disabled={loading}
          className={store.is_favorite ? 'text-yellow-500 hover:text-yellow-600' : 'text-muted-foreground'}
          title={store.is_favorite ? 'Remove from favorites' : 'Add to favorites'}
        >
          <Star className={`h-4 w-4 ${store.is_favorite ? 'fill-current' : ''}`} />
        </Button>

        {/* Enable/disable switch */}
        <Switch
          checked={store.enabled}
          onCheckedChange={(checked) => onToggleEnabled(store.id, checked)}
          disabled={loading}
        />

        {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
      </div>
    </div>
  );
}

export default StorePreferences;
