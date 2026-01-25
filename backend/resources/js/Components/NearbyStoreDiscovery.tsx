import { useState, useEffect } from 'react';
import axios from 'axios';
import type {
  StoreCategory,
  NearbyStoreDiscoveryRequest,
  NearbyStoreResult,
  NearbyStoreAvailability,
} from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
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
} from 'lucide-react';

interface NearbyStoreDiscoveryProps {
  onStoresAdded?: () => void;
  hasLocation?: boolean;
}

interface CategoryOption {
  id: StoreCategory;
  label: string;
  icon: React.ReactNode;
}

const CATEGORIES: CategoryOption[] = [
  { id: 'grocery', label: 'Grocery Stores', icon: <ShoppingCart className="h-4 w-4" /> },
  { id: 'electronics', label: 'Electronics', icon: <Tv className="h-4 w-4" /> },
  { id: 'pet', label: 'Pet Stores', icon: <Dog className="h-4 w-4" /> },
  { id: 'pharmacy', label: 'Pharmacies', icon: <Pill className="h-4 w-4" /> },
  { id: 'home', label: 'Home & Hardware', icon: <Home className="h-4 w-4" /> },
  { id: 'clothing', label: 'Clothing & Apparel', icon: <Shirt className="h-4 w-4" /> },
  { id: 'warehouse', label: 'Warehouse Clubs', icon: <Package className="h-4 w-4" /> },
  { id: 'general', label: 'General Retail', icon: <Store className="h-4 w-4" /> },
  { id: 'specialty', label: 'Specialty Stores', icon: <Sparkles className="h-4 w-4" /> },
];

/**
 * NearbyStoreDiscovery Component
 *
 * A dialog component that allows users to discover and add nearby stores
 * to their Store Registry using Google Places API.
 */
export function NearbyStoreDiscovery({ onStoresAdded, hasLocation = false }: NearbyStoreDiscoveryProps) {
  const [open, setOpen] = useState(false);
  const [availability, setAvailability] = useState<NearbyStoreAvailability | null>(null);
  const [checkingAvailability, setCheckingAvailability] = useState(false);

  // Form state
  const [radiusMiles, setRadiusMiles] = useState(10);
  const [selectedCategories, setSelectedCategories] = useState<StoreCategory[]>([
    'grocery',
    'electronics',
    'pharmacy',
  ]);

  // Preview state
  const [previewStores, setPreviewStores] = useState<NearbyStoreResult[]>([]);
  const [previewing, setPreviewing] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [previewComplete, setPreviewComplete] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Filter preview stores by search query
  const filteredPreviewStores = previewStores.filter((store) =>
    searchQuery === '' || store.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // Store selection state
  const [selectedStoreIds, setSelectedStoreIds] = useState<Set<string>>(new Set());
  const [addingStores, setAddingStores] = useState(false);
  const [addResult, setAddResult] = useState<{
    added: number;
    skipped: number;
    errors: number;
  } | null>(null);

  // Result state
  const [discoveryResult, setDiscoveryResult] = useState<{
    stores_found: number;
    stores_added: number;
    stores_configured: number;
  } | null>(null);
  const [discoveryError, setDiscoveryError] = useState<string | null>(null);

  // Check availability when dialog opens
  useEffect(() => {
    if (open && !availability) {
      checkAvailability();
    }
  }, [open]);

  const checkAvailability = async () => {
    setCheckingAvailability(true);
    try {
      const response = await axios.get('/api/stores/nearby/availability');
      setAvailability(response.data);
    } catch {
      setAvailability({
        available: false,
        has_google_places_key: false,
        has_location: hasLocation,
        can_auto_configure: false,
      });
    } finally {
      setCheckingAvailability(false);
    }
  };

  const toggleCategory = (category: StoreCategory) => {
    setSelectedCategories((prev) =>
      prev.includes(category) ? prev.filter((c) => c !== category) : [...prev, category]
    );
  };

  const handlePreview = async () => {
    setPreviewing(true);
    setPreviewError(null);
    setPreviewStores([]);
    setPreviewComplete(false);
    setSelectedStoreIds(new Set());
    setAddResult(null);

    try {
      const payload: NearbyStoreDiscoveryRequest = {
        radius_miles: radiusMiles,
        categories: selectedCategories.length > 0 ? selectedCategories : undefined,
      };

      const response = await axios.post('/api/stores/nearby/preview', payload);

      if (response.data.success) {
        const stores = response.data.stores || [];
        setPreviewStores(stores);
        setPreviewComplete(true);
        // Select all stores by default
        setSelectedStoreIds(new Set(stores.map((s: NearbyStoreResult) => s.place_id)));
      } else {
        setPreviewError(response.data.error || 'Preview failed');
      }
    } catch (err) {
      const error = err as { response?: { data?: { error?: string } } };
      setPreviewError(error.response?.data?.error || 'Failed to preview nearby stores');
    } finally {
      setPreviewing(false);
    }
  };

  const toggleStoreSelection = (placeId: string) => {
    setSelectedStoreIds((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(placeId)) {
        newSet.delete(placeId);
      } else {
        newSet.add(placeId);
      }
      return newSet;
    });
  };

  const selectAllStores = () => {
    setSelectedStoreIds(new Set(previewStores.map((s) => s.place_id)));
  };

  const deselectAllStores = () => {
    setSelectedStoreIds(new Set());
  };

  const handleAddSelected = async () => {
    if (selectedStoreIds.size === 0) return;

    setAddingStores(true);
    setDiscoveryError(null);
    setAddResult(null);

    try {
      // Get the selected stores' full data
      const storesToAdd = previewStores
        .filter((store) => selectedStoreIds.has(store.place_id))
        .map((store) => ({
          place_id: store.place_id,
          name: store.name,
          address: store.address,
          category: store.category,
          latitude: store.latitude,
          longitude: store.longitude,
          website: store.website,
          phone: store.phone,
        }));

      const response = await axios.post('/api/stores/nearby/add-selected', {
        stores: storesToAdd,
      });

      if (response.data.success) {
        const summary = response.data.summary;
        setAddResult({
          added: summary.added,
          skipped: summary.skipped,
          errors: summary.errors,
        });
        // Show result and trigger refresh if stores were added
        setDiscoveryResult({
          stores_found: summary.total_requested,
          stores_added: summary.added,
          stores_configured: 0,
        });
        if (summary.added > 0) {
          onStoresAdded?.();
        }
      } else {
        setDiscoveryError(response.data.error || 'Failed to add stores');
      }
    } catch (err) {
      const error = err as { response?: { data?: { error?: string } } };
      setDiscoveryError(error.response?.data?.error || 'Failed to add selected stores');
    } finally {
      setAddingStores(false);
    }
  };

  const resetDialog = () => {
    setPreviewStores([]);
    setPreviewError(null);
    setPreviewComplete(false);
    setSelectedStoreIds(new Set());
    setAddingStores(false);
    setAddResult(null);
    setDiscoveryResult(null);
    setDiscoveryError(null);
    setSearchQuery('');
  };

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) {
      resetDialog();
    }
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
            Discover stores near you and automatically add them to your registry for price tracking.
          </DialogDescription>
        </DialogHeader>

        {checkingAvailability ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : !availability?.available ? (
          <div className="space-y-4 py-4">
            <div className="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-900">
              <AlertCircle className="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
              <div className="space-y-2">
                <p className="font-medium text-amber-900 dark:text-amber-100">
                  Configuration Required
                </p>
                <ul className="text-sm text-amber-800 dark:text-amber-200 space-y-1">
                  {!availability?.has_google_places_key && (
                    <li>• Add your Google Places API key in Settings → Config</li>
                  )}
                  {!availability?.has_location && (
                    <li>• Set your home address in Settings → General</li>
                  )}
                </ul>
              </div>
            </div>
          </div>
        ) : discoveryResult ? (
          // Add complete state
          <div className="space-y-4 py-4">
            {discoveryResult.stores_added === 0 && addResult?.skipped === 0 ? (
              // No stores were added (shouldn't happen with current flow but kept for safety)
              <div className="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-900">
                <AlertCircle className="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-medium text-amber-900 dark:text-amber-100">No Stores Added</p>
                  <p className="text-sm text-amber-800 dark:text-amber-200">
                    No stores were added to your registry.
                  </p>
                </div>
              </div>
            ) : discoveryResult.stores_added === 0 ? (
              // All selected stores already exist
              <div className="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg border border-blue-200 dark:border-blue-900">
                <CheckCircle2 className="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-medium text-blue-900 dark:text-blue-100">Stores Already in Registry</p>
                  <p className="text-sm text-blue-800 dark:text-blue-200">
                    All {addResult?.skipped || discoveryResult.stores_found} selected stores are already in your registry.
                  </p>
                </div>
              </div>
            ) : (
              // Stores were added successfully
              <div className="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-950/20 rounded-lg border border-green-200 dark:border-green-900">
                <CheckCircle2 className="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-medium text-green-900 dark:text-green-100">Stores Added!</p>
                  <p className="text-sm text-green-800 dark:text-green-200">
                    Added {discoveryResult.stores_added} new stores to your registry.
                    {addResult && addResult.skipped > 0 && (
                      <> {addResult.skipped} were already in your registry.</>
                    )}
                  </p>
                </div>
              </div>
            )}

            <DialogFooter>
              <Button onClick={() => handleOpenChange(false)}>Done</Button>
            </DialogFooter>
          </div>
        ) : addingStores ? (
          // Adding stores in progress
          <div className="space-y-4 py-4">
            <div className="flex items-center gap-3">
              <Loader2 className="h-5 w-5 animate-spin text-primary" />
              <div className="flex-1">
                <p className="font-medium">Adding {selectedStoreIds.size} stores to your registry...</p>
              </div>
            </div>
          </div>
        ) : (
          // Configuration form
          <div className="space-y-6 py-4">
            {discoveryError && (
              <div className="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-950/20 rounded-lg border border-red-200 dark:border-red-900">
                <AlertCircle className="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="font-medium text-red-900 dark:text-red-100">Discovery Failed</p>
                  <p className="text-sm text-red-800 dark:text-red-200">{discoveryError}</p>
                </div>
              </div>
            )}

            {/* Radius Selection */}
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

            {/* Category Selection */}
            <div className="space-y-3">
              <Label>Store Categories</Label>
              <div className="grid grid-cols-2 gap-2">
                {CATEGORIES.map((category) => (
                  <button
                    key={category.id}
                    type="button"
                    onClick={() => toggleCategory(category.id)}
                    className={`flex items-center gap-2 px-3 py-2 rounded-lg border text-sm transition-colors text-left ${
                      selectedCategories.includes(category.id)
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-border hover:border-primary/50'
                    }`}
                  >
                    {category.icon}
                    <span className="truncate">{category.label}</span>
                  </button>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                {selectedCategories.length === 0
                  ? 'All categories will be searched'
                  : `${selectedCategories.length} categor${selectedCategories.length === 1 ? 'y' : 'ies'} selected`}
              </p>
            </div>

            {/* Preview Results */}
            {previewComplete && (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <Label>
                    {previewStores.length} stores found
                    {selectedStoreIds.size > 0 && (
                      <span className="ml-1 text-primary">
                        ({selectedStoreIds.size} selected)
                      </span>
                    )}
                  </Label>
                  <div className="flex items-center gap-1">
                    {previewStores.length > 0 && (
                      <>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={selectAllStores}
                          className="h-6 px-2 text-xs"
                          disabled={selectedStoreIds.size === previewStores.length}
                        >
                          Select All
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={deselectAllStores}
                          className="h-6 px-2 text-xs"
                          disabled={selectedStoreIds.size === 0}
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
                        setSelectedStoreIds(new Set());
                        setSearchQuery('');
                      }}
                      className="h-6 px-2"
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  </div>
                </div>

                {/* Search filter */}
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
                  filteredPreviewStores.length > 0 ? (
                    <div className="max-h-64 overflow-y-auto space-y-1 border rounded-lg p-2">
                      {filteredPreviewStores.map((store) => (
                        <label
                          key={store.place_id}
                          className={`flex items-center gap-3 p-2 rounded cursor-pointer transition-colors ${
                            selectedStoreIds.has(store.place_id)
                              ? 'bg-primary/10 border border-primary/30'
                              : 'bg-muted/50 hover:bg-muted'
                          }`}
                        >
                          <Checkbox
                            checked={selectedStoreIds.has(store.place_id)}
                            onCheckedChange={() => toggleStoreSelection(store.place_id)}
                            className="flex-shrink-0"
                          />
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-sm truncate">{store.name}</p>
                            <p className="text-xs text-muted-foreground truncate">{store.address}</p>
                          </div>
                          <div className="flex items-center gap-2 flex-shrink-0">
                            <Badge variant="outline" className="text-xs">
                              {store.distance_miles} mi
                            </Badge>
                            {store.website && (
                              <CheckCircle2 className="h-3 w-3 text-green-500" title="Has website" />
                            )}
                          </div>
                        </label>
                      ))}
                    </div>
                  ) : (
                    <div className="flex items-start gap-2 p-3 bg-muted/50 rounded-lg border">
                      <Search className="h-4 w-4 text-muted-foreground flex-shrink-0 mt-0.5" />
                      <p className="text-sm text-muted-foreground">
                        No stores match "{searchQuery}". Try a different search term.
                      </p>
                    </div>
                  )
                ) : (
                  <div className="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-900">
                    <AlertCircle className="h-4 w-4 text-amber-500 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-amber-800 dark:text-amber-200">
                      No stores found within {radiusMiles} miles for the selected categories.
                      Try increasing the radius or selecting different categories.
                    </p>
                  </div>
                )}
              </div>
            )}

            {previewError && (
              <div className="flex items-start gap-2 p-3 bg-red-50 dark:bg-red-950/20 rounded-lg border border-red-200 dark:border-red-900">
                <AlertCircle className="h-4 w-4 text-red-500 flex-shrink-0 mt-0.5" />
                <p className="text-sm text-red-700 dark:text-red-300">{previewError}</p>
              </div>
            )}

            {/* Auto-configure notice */}
            {availability?.can_auto_configure && (
              <div className="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-950/20 rounded-lg text-sm">
                <Sparkles className="h-4 w-4 text-blue-500 flex-shrink-0 mt-0.5" />
                <p className="text-blue-800 dark:text-blue-200">
                  Stores with websites will be automatically configured for price search using
                  Firecrawl.
                </p>
              </div>
            )}

            <DialogFooter className="gap-2">
              <Button
                variant="outline"
                onClick={handlePreview}
                disabled={previewing || addingStores}
              >
                {previewing ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Search className="h-4 w-4 mr-2" />
                )}
                {previewComplete ? 'Refresh' : 'Preview'}
              </Button>
              <Button 
                onClick={handleAddSelected} 
                disabled={addingStores || selectedStoreIds.size === 0}
              >
                {addingStores ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Plus className="h-4 w-4 mr-2" />
                )}
                {previewComplete && selectedStoreIds.size > 0
                  ? `Add Selected (${selectedStoreIds.size})`
                  : 'Add Selected'}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

export default NearbyStoreDiscovery;
