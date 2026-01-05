import { useState, useCallback, useEffect, useRef, type ReactNode } from 'react';
import axios from 'axios';
import type { Store, StoreCategory, AIJob } from '@/types';
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
  Pencil,
  Trash2,
  Wand2,
  Ban,
  X,
  Link,
} from 'lucide-react';
import { NearbyStoreDiscovery } from '@/Components/NearbyStoreDiscovery';

interface SuppressedStore {
  id: number;
  name: string;
  domain: string;
  category: string;
}

interface StorePreferencesProps {
  stores: Store[];
  storeCategories: Record<StoreCategory, string>;
  suppressedVendors?: string[];
  onSuppressedVendorsChange?: (vendors: string[]) => void;
  suppressedStores?: SuppressedStore[];
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
export function StorePreferences({ 
  stores: initialStores, 
  storeCategories, 
  suppressedVendors = [],
  onSuppressedVendorsChange,
  suppressedStores: initialSuppressedStores = [],
  onUpdate 
}: StorePreferencesProps) {
  const [stores, setStores] = useState<Store[]>(initialStores);
  const [suppressedStoresList, setSuppressedStoresList] = useState<SuppressedStore[]>(initialSuppressedStores);
  const [loading, setLoading] = useState<Record<number, boolean>>({});
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [localOnly, setLocalOnly] = useState(false);
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [isResetting, setIsResetting] = useState(false);
  const [newVendor, setNewVendor] = useState('');
  const [restoringStoreId, setRestoringStoreId] = useState<number | null>(null);

  // Add custom store form state
  const [newStore, setNewStore] = useState({
    name: '',
    domain: '',
    search_url_template: '',
    category: 'specialty' as StoreCategory,
    is_local: false,
  });
  const [addingStore, setAddingStore] = useState(false);

  // Edit store state
  const [editingStore, setEditingStore] = useState<Store | null>(null);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [editForm, setEditForm] = useState({
    name: '',
    domain: '',
    search_url_template: '',
    category: 'specialty' as StoreCategory,
    is_local: false,
    location_id: '',
  });
  const [savingEdit, setSavingEdit] = useState(false);
  const [deletingStoreId, setDeletingStoreId] = useState<number | null>(null);
  const [suppressingStoreId, setSuppressingStoreId] = useState<number | null>(null);
  const [savingLocationId, setSavingLocationId] = useState(false);

  // URL discovery state
  const [findingUrl, setFindingUrl] = useState<Record<number, boolean>>({});
  const [urlJobIds, setUrlJobIds] = useState<Record<number, number>>({});
  const pollIntervalRef = useRef<Record<number, NodeJS.Timeout>>({});
  // Track stores where agent detection is available (after standard detection failed)
  const [agentAvailable, setAgentAvailable] = useState<Record<number, { available: boolean; costEstimate?: string }>>({});

  // Cleanup polling intervals on unmount
  useEffect(() => {
    return () => {
      Object.values(pollIntervalRef.current).forEach(clearInterval);
    };
  }, []);

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

  // Open edit dialog
  const handleOpenEditDialog = useCallback((store: Store) => {
    setEditingStore(store);
    setEditForm({
      name: store.name,
      domain: store.domain,
      search_url_template: store.search_url_template || '',
      category: (store.category || 'specialty') as StoreCategory,
      is_local: store.is_local,
      location_id: store.location_id || '',
    });
    setShowEditDialog(true);
  }, []);

  // Save edited store
  const handleSaveEdit = async () => {
    if (!editingStore || !editForm.name || !editForm.domain) {
      return;
    }

    setSavingEdit(true);
    try {
      const response = await axios.put(`/api/stores/${editingStore.id}`, editForm);
      const updatedStore = response.data.store;

      // Update local state
      setStores((prev) =>
        prev.map((s) =>
          s.id === editingStore.id
            ? {
                ...s,
                name: updatedStore.name,
                domain: updatedStore.domain,
                category: updatedStore.category,
                is_local: updatedStore.is_local,
                has_search_template: updatedStore.has_search_template,
              }
            : s
        )
      );

      // Also save location_id if it changed
      if (editForm.location_id !== (editingStore.location_id || '')) {
        await axios.post(`/api/stores/${editingStore.id}/location`, {
          location_id: editForm.location_id || null,
        });
        // Update local state with location_id
        setStores((prev) =>
          prev.map((s) =>
            s.id === editingStore.id
              ? { ...s, location_id: editForm.location_id || undefined }
              : s
          )
        );
      }

      setShowEditDialog(false);
      setEditingStore(null);
      onUpdate?.();
    } catch (err) {
      console.error('Failed to update store:', err);
    } finally {
      setSavingEdit(false);
    }
  };

  // Delete store
  const handleDeleteStore = useCallback(async (storeId: number, storeName: string, isDefault: boolean) => {
    if (isDefault) {
      alert('Default stores cannot be deleted.');
      return;
    }

    if (!confirm(`Are you sure you want to delete "${storeName}"? This action cannot be undone.`)) {
      return;
    }

    setDeletingStoreId(storeId);
    try {
      await axios.delete(`/api/stores/${storeId}`);
      // Remove from local state
      setStores((prev) => prev.filter((s) => s.id !== storeId));
      onUpdate?.();
    } catch (err) {
      console.error('Failed to delete store:', err);
      alert('Failed to delete store. It may be a default store that cannot be deleted.');
    } finally {
      setDeletingStoreId(null);
    }
  }, [onUpdate]);

  // Suppress a store (hide from list)
  const handleSuppressStore = useCallback(async (storeId: number, storeName: string) => {
    if (!confirm(`Are you sure you want to suppress "${storeName}"? It will be hidden from your store list.`)) {
      return;
    }

    setSuppressingStoreId(storeId);
    try {
      await axios.post(`/api/stores/${storeId}/suppress`);
      // Remove from local state
      setStores((prev) => prev.filter((s) => s.id !== storeId));
      // Close edit dialog if open for this store
      if (editingStore?.id === storeId) {
        setShowEditDialog(false);
        setEditingStore(null);
      }
      onUpdate?.();
    } catch (err) {
      console.error('Failed to suppress store:', err);
      alert('Failed to suppress store.');
    } finally {
      setSuppressingStoreId(null);
    }
  }, [onUpdate, editingStore]);

  // Restore a suppressed store
  const handleRestoreStore = useCallback(async (storeId: number, storeName: string) => {
    setRestoringStoreId(storeId);
    try {
      await axios.post(`/api/stores/${storeId}/restore`);
      // Remove from local suppressed list
      setSuppressedStoresList((prev) => prev.filter((s) => s.id !== storeId));
      onUpdate?.();
    } catch (err) {
      console.error('Failed to restore store:', err);
      alert('Failed to restore store.');
    } finally {
      setRestoringStoreId(null);
    }
  }, [onUpdate]);

  // Start URL discovery for a store
  const handleFindSearchUrl = useCallback(async (storeId: number, useAgent: boolean = false) => {
    setFindingUrl((prev) => ({ ...prev, [storeId]: true }));
    // Clear any previous agent availability when starting a new search
    if (!useAgent) {
      setAgentAvailable((prev) => {
        const newState = { ...prev };
        delete newState[storeId];
        return newState;
      });
    }

    try {
      const endpoint = useAgent
        ? `/api/stores/${storeId}/find-search-url-agent`
        : `/api/stores/${storeId}/find-search-url`;
      const response = await axios.post(endpoint);
      const { ai_job_id, already_running } = response.data;

      if (ai_job_id) {
        setUrlJobIds((prev) => ({ ...prev, [storeId]: ai_job_id }));

        // Start polling for job status
        const pollInterval = setInterval(async () => {
          try {
            const jobResponse = await axios.get(`/api/ai-jobs/${ai_job_id}`);
            const job: AIJob = jobResponse.data;

            if (job.status === 'completed') {
              clearInterval(pollInterval);
              delete pollIntervalRef.current[storeId];
              setFindingUrl((prev) => ({ ...prev, [storeId]: false }));
              setUrlJobIds((prev) => {
                const newState = { ...prev };
                delete newState[storeId];
                return newState;
              });

              // Check if URL was found
              const outputData = job.output_data as { 
                template?: string; 
                success?: boolean; 
                error?: string;
                agent_available?: boolean;
                agent_cost_estimate?: string;
              } | undefined;
              
              if (outputData?.template) {
                // Update local store state with the new template
                setStores((prev) =>
                  prev.map((s) =>
                    s.id === storeId
                      ? { ...s, has_search_template: true, search_url_template: outputData.template }
                      : s
                  )
                );
                // If edit dialog is open for this store, update the form too
                if (editingStore?.id === storeId) {
                  setEditForm((prev) => ({ ...prev, search_url_template: outputData.template || '' }));
                }
                // Clear agent availability on success
                setAgentAvailable((prev) => {
                  const newState = { ...prev };
                  delete newState[storeId];
                  return newState;
                });
                onUpdate?.();
              } else {
                // Detection failed - check if agent is available
                if (outputData?.agent_available) {
                  setAgentAvailable((prev) => ({
                    ...prev,
                    [storeId]: {
                      available: true,
                      costEstimate: outputData.agent_cost_estimate,
                    },
                  }));
                }
                // Only show alert if not using agent (agent will show inline option)
                if (!outputData?.agent_available || useAgent) {
                  alert(outputData?.error || 'Could not find search URL for this store.');
                }
              }
            } else if (job.status === 'failed' || job.status === 'cancelled') {
              clearInterval(pollInterval);
              delete pollIntervalRef.current[storeId];
              setFindingUrl((prev) => ({ ...prev, [storeId]: false }));
              setUrlJobIds((prev) => {
                const newState = { ...prev };
                delete newState[storeId];
                return newState;
              });
              alert(job.error_message || 'URL discovery failed.');
            }
          } catch (pollErr) {
            console.error('Failed to poll job status:', pollErr);
          }
        }, 2000); // Poll every 2 seconds

        pollIntervalRef.current[storeId] = pollInterval;
      }
    } catch (err) {
      console.error('Failed to start URL discovery:', err);
      setFindingUrl((prev) => ({ ...prev, [storeId]: false }));
      const errorMessage = axios.isAxiosError(err) && err.response?.data?.error
        ? err.response.data.error
        : 'Failed to start URL discovery.';
      alert(errorMessage);
    }
  }, [onUpdate, editingStore]);

  // Handler for trying advanced (agent) detection
  const handleTryAgentDetection = useCallback((storeId: number) => {
    handleFindSearchUrl(storeId, true);
  }, [handleFindSearchUrl]);

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
                  // Refresh the stores list while staying on Stores tab
                  window.location.hash = 'stores';
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

              {/* Edit Store Dialog */}
              <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Edit Store</DialogTitle>
                    <DialogDescription>
                      Update the store details. Changes will be saved for all users.
                    </DialogDescription>
                  </DialogHeader>

                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="edit-store-name">Store Name</Label>
                      <Input
                        id="edit-store-name"
                        placeholder="e.g., My Local Shop"
                        value={editForm.name}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, name: e.target.value }))}
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="edit-store-domain">Website Domain</Label>
                      <Input
                        id="edit-store-domain"
                        placeholder="e.g., mylocalshop.com"
                        value={editForm.domain}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, domain: e.target.value }))}
                      />
                      <p className="text-xs text-muted-foreground">
                        Just the domain name, no https:// needed
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="edit-store-category">Category</Label>
                      <Select
                        value={editForm.category}
                        onValueChange={(v) => setEditForm((prev) => ({ ...prev, category: v as StoreCategory }))}
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
                        checked={editForm.is_local}
                        onCheckedChange={(checked) =>
                          setEditForm((prev) => ({ ...prev, is_local: checked }))
                        }
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="edit-store-template">Search URL Template (Optional)</Label>
                      <div className="flex gap-2">
                        <Input
                          id="edit-store-template"
                          placeholder="e.g., https://shop.com/search?q={query}"
                          value={editForm.search_url_template}
                          onChange={(e) =>
                            setEditForm((prev) => ({ ...prev, search_url_template: e.target.value }))
                          }
                          className="flex-1"
                        />
                        <Button
                          type="button"
                          variant="outline"
                          size="icon"
                          onClick={() => editingStore && handleFindSearchUrl(editingStore.id)}
                          disabled={!editingStore || !editForm.domain || findingUrl[editingStore?.id ?? 0]}
                          title="Auto-detect search URL"
                        >
                          {findingUrl[editingStore?.id ?? 0] ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Wand2 className="h-4 w-4" />
                          )}
                        </Button>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        Use {'{query}'} where the search term should go, or click the wand to auto-detect.
                      </p>
                      
                      {/* Agent Detection Option */}
                      {editingStore && agentAvailable[editingStore.id]?.available && !editForm.search_url_template && (
                        <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/30">
                          <div className="flex items-start gap-3">
                            <Wand2 className="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                            <div className="flex-1 space-y-2">
                              <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                Standard detection couldn&apos;t find the URL
                              </p>
                              <p className="text-xs text-amber-800 dark:text-amber-200">
                                Try advanced detection using Firecrawl Agent? This interacts with the page directly to find the search URL.
                                {agentAvailable[editingStore.id].costEstimate && (
                                  <span className="block mt-1 font-medium">
                                    Estimated cost: {agentAvailable[editingStore.id].costEstimate}
                                  </span>
                                )}
                              </p>
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => handleTryAgentDetection(editingStore.id)}
                                disabled={findingUrl[editingStore.id]}
                                className="mt-1 border-amber-300 hover:bg-amber-100 dark:border-amber-700 dark:hover:bg-amber-900/50"
                              >
                                {findingUrl[editingStore.id] ? (
                                  <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Detecting...
                                  </>
                                ) : (
                                  <>
                                    <Wand2 className="mr-2 h-4 w-4" />
                                    Try Advanced Detection
                                  </>
                                )}
                              </Button>
                            </div>
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Parent Store Info */}
                    {editingStore?.parent_store_id && (
                      <div className="rounded-lg border bg-muted/50 p-3">
                        <div className="flex items-center gap-2 text-sm">
                          <Link className="h-4 w-4 text-blue-600" />
                          <span className="font-medium">Linked to {editingStore.parent_store_name}</span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                          This store uses {editingStore.parent_store_name}'s search functionality for price discovery.
                        </p>
                      </div>
                    )}

                    {/* Location ID for stores that need it */}
                    <div className="space-y-2">
                      <Label htmlFor="edit-store-location">Location/Store ID (Optional)</Label>
                      <Input
                        id="edit-store-location"
                        placeholder="e.g., store number or zip code"
                        value={editForm.location_id}
                        onChange={(e) =>
                          setEditForm((prev) => ({ ...prev, location_id: e.target.value }))
                        }
                      />
                      <p className="text-xs text-muted-foreground">
                        For stores like Kroger that show local pricing, enter your preferred store ID or location code.
                        Use {'{store_id}'} in the URL template to include it in searches.
                      </p>
                    </div>
                  </div>

                  <DialogFooter className="flex-col sm:flex-row gap-2">
                    <Button
                      variant="destructive"
                      onClick={() => editingStore && handleSuppressStore(editingStore.id, editingStore.name)}
                      disabled={suppressingStoreId === editingStore?.id || savingEdit}
                      className="sm:mr-auto"
                    >
                      {suppressingStoreId === editingStore?.id ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      ) : (
                        <Ban className="mr-2 h-4 w-4" />
                      )}
                      Suppress
                    </Button>
                    <Button variant="outline" onClick={() => setShowEditDialog(false)}>
                      Cancel
                    </Button>
                    <Button onClick={handleSaveEdit} disabled={savingEdit || !editForm.name || !editForm.domain}>
                      {savingEdit && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                      Save Changes
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
                  deleting={deletingStoreId === store.id}
                  suppressing={suppressingStoreId === store.id}
                  findingUrl={findingUrl[store.id] || false}
                  categoryLabel={storeCategories[store.category as StoreCategory]}
                  onToggleEnabled={handleToggleEnabled}
                  onToggleFavorite={handleToggleFavorite}
                  onToggleLocal={handleToggleLocal}
                  onEdit={handleOpenEditDialog}
                  onDelete={handleDeleteStore}
                  onSuppress={handleSuppressStore}
                  onFindUrl={handleFindSearchUrl}
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

      {/* Suppressed Vendors */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Ban className="h-5 w-5" />
            Suppressed Vendors
          </CardTitle>
          <CardDescription>
            Hide specific vendors from price results and comparisons
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex gap-2">
            <input
              type="text"
              value={newVendor}
              onChange={(e) => setNewVendor(e.target.value)}
              placeholder="Enter vendor name (e.g., Amazon, eBay)"
              className="flex-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  if (newVendor.trim() && !suppressedVendors.includes(newVendor.trim())) {
                    onSuppressedVendorsChange?.([...suppressedVendors, newVendor.trim()]);
                    setNewVendor('');
                  }
                }
              }}
            />
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                if (newVendor.trim() && !suppressedVendors.includes(newVendor.trim())) {
                  onSuppressedVendorsChange?.([...suppressedVendors, newVendor.trim()]);
                  setNewVendor('');
                }
              }}
            >
              <Plus className="h-4 w-4" />
            </Button>
          </div>
          
          {suppressedVendors.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {suppressedVendors.map((vendor, index) => (
                <Badge 
                  key={index} 
                  variant="secondary" 
                  className="flex items-center gap-1 px-3 py-1"
                >
                  <StoreIcon className="h-3 w-3" />
                  {vendor}
                  <button
                    type="button"
                    className="ml-1 hover:text-destructive"
                    onClick={() => {
                      onSuppressedVendorsChange?.(suppressedVendors.filter((_, i) => i !== index));
                    }}
                  >
                    <X className="h-3 w-3" />
                  </button>
                </Badge>
              ))}
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">
              No vendors suppressed. Add vendor names above to hide them from results.
            </p>
          )}
          
          <p className="text-xs text-muted-foreground">
            Suppressed vendors will be hidden from all price comparisons, search results, and price history.
            This is useful for excluding vendors you don't want to purchase from.
          </p>
        </CardContent>
      </Card>

      {/* Suppressed Stores */}
      {suppressedStoresList.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Ban className="h-5 w-5" />
              Suppressed Stores
            </CardTitle>
            <CardDescription>
              These stores have been hidden from your store registry. Click restore to add them back.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {suppressedStoresList.map((store) => (
                <Badge 
                  key={store.id} 
                  variant="secondary" 
                  className="flex items-center gap-2 px-3 py-1.5"
                >
                  <StoreIcon className="h-3 w-3" />
                  <span>{store.name}</span>
                  <span className="text-xs text-muted-foreground">({store.domain})</span>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-5 w-5 p-0 ml-1 hover:text-green-600 hover:bg-green-100"
                    onClick={() => handleRestoreStore(store.id, store.name)}
                    disabled={restoringStoreId === store.id}
                    title="Restore store"
                  >
                    {restoringStoreId === store.id ? (
                      <Loader2 className="h-3 w-3 animate-spin" />
                    ) : (
                      <RotateCcw className="h-3 w-3" />
                    )}
                  </Button>
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

interface StoreRowProps {
  store: Store;
  loading: boolean;
  deleting: boolean;
  suppressing: boolean;
  findingUrl: boolean;
  categoryLabel?: string;
  onToggleEnabled: (storeId: number, enabled: boolean) => void;
  onToggleFavorite: (storeId: number) => void;
  onToggleLocal: (storeId: number) => void;
  onEdit: (store: Store) => void;
  onDelete: (storeId: number, storeName: string, isDefault: boolean) => void;
  onSuppress: (storeId: number, storeName: string) => void;
  onFindUrl: (storeId: number) => void;
}

function StoreRow({ 
  store, 
  loading, 
  deleting,
  suppressing,
  findingUrl,
  categoryLabel, 
  onToggleEnabled, 
  onToggleFavorite, 
  onToggleLocal,
  onEdit,
  onDelete,
  onSuppress,
  onFindUrl,
}: StoreRowProps) {
  const isDisabled = loading || deleting || suppressing || findingUrl;

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
          {findingUrl && (
            <Badge variant="outline" className="text-xs flex-shrink-0 gap-1">
              <Loader2 className="h-3 w-3 animate-spin" />
              Finding URL...
            </Badge>
          )}
          {!findingUrl && store.has_search_template && (
            <Badge variant="secondary" className="text-xs flex-shrink-0">
              Fast
            </Badge>
          )}
          {store.is_default && (
            <Badge variant="outline" className="text-xs flex-shrink-0 text-muted-foreground">
              Default
            </Badge>
          )}
          {store.parent_store_name && (
            <Badge variant="outline" className="text-xs flex-shrink-0 text-blue-600 border-blue-200">
              <Link className="mr-1 h-3 w-3" />
              {store.parent_store_name}
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
      <div className="flex items-center gap-1">
        {/* Find URL button - show for stores without templates */}
        {!store.has_search_template && store.domain && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onFindUrl(store.id)}
            disabled={isDisabled}
            className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
            title="Auto-detect search URL"
          >
            {findingUrl ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Wand2 className="h-4 w-4" />
            )}
          </Button>
        )}

        {/* Local button */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onToggleLocal(store.id)}
          disabled={isDisabled}
          className={`h-8 w-8 p-0 ${store.is_local ? 'text-green-600 hover:text-green-700' : 'text-muted-foreground'}`}
          title={store.is_local ? 'Mark as non-local' : 'Mark as local'}
        >
          <MapPin className={`h-4 w-4 ${store.is_local ? 'fill-current' : ''}`} />
        </Button>

        {/* Favorite button */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onToggleFavorite(store.id)}
          disabled={isDisabled}
          className={`h-8 w-8 p-0 ${store.is_favorite ? 'text-yellow-500 hover:text-yellow-600' : 'text-muted-foreground'}`}
          title={store.is_favorite ? 'Remove from favorites' : 'Add to favorites'}
        >
          <Star className={`h-4 w-4 ${store.is_favorite ? 'fill-current' : ''}`} />
        </Button>

        {/* Edit button */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onEdit(store)}
          disabled={isDisabled}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
          title="Edit store"
        >
          <Pencil className="h-4 w-4" />
        </Button>

        {/* Suppress button - hide store from list */}
        <Button
          variant="ghost"
          size="sm"
          onClick={() => onSuppress(store.id, store.name)}
          disabled={isDisabled}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-red-600"
          title="Suppress store (hide from list)"
        >
          {suppressing ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Ban className="h-4 w-4" />
          )}
        </Button>

        {/* Delete button - only for non-default stores */}
        {!store.is_default && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onDelete(store.id, store.name, store.is_default)}
            disabled={isDisabled}
            className="h-8 w-8 p-0 text-muted-foreground hover:text-red-600"
            title="Delete store"
          >
            {deleting ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Trash2 className="h-4 w-4" />
            )}
          </Button>
        )}

        {/* Enable/disable switch */}
        <Switch
          checked={store.enabled}
          onCheckedChange={(checked) => onToggleEnabled(store.id, checked)}
          disabled={isDisabled}
          className="ml-1"
        />

        {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground ml-1" />}
      </div>
    </div>
  );
}

export default StorePreferences;
