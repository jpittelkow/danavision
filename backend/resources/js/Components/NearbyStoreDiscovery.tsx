import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import type {
  StoreCategory,
  NearbyStoreDiscoveryRequest,
  NearbyStoreResult,
  NearbyStoreAvailability,
  AIJobStatus,
} from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
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
  Navigation,
  ShoppingCart,
  Tv,
  Package,
  Home,
  Shirt,
  Pill,
  Sparkles,
  Dog,
  X,
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
  const [useCurrentLocation, setUseCurrentLocation] = useState(false);
  const [currentLocation, setCurrentLocation] = useState<{ lat: number; lng: number } | null>(null);
  const [gettingLocation, setGettingLocation] = useState(false);

  // Preview state
  const [previewStores, setPreviewStores] = useState<NearbyStoreResult[]>([]);
  const [previewing, setPreviewing] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);

  // Discovery state
  const [discovering, setDiscovering] = useState(false);
  const [discoveryJobId, setDiscoveryJobId] = useState<number | null>(null);
  const [discoveryProgress, setDiscoveryProgress] = useState(0);
  const [discoveryStatus, setDiscoveryStatus] = useState<AIJobStatus | null>(null);
  const [discoveryLogs, setDiscoveryLogs] = useState<string[]>([]);
  const [discoveryResult, setDiscoveryResult] = useState<{
    stores_added: number;
    stores_configured: number;
  } | null>(null);
  const [discoveryError, setDiscoveryError] = useState<string | null>(null);
  const pollingCleanupRef = React.useRef<(() => void) | null>(null);

  // Check availability when dialog opens
  useEffect(() => {
    if (open && !availability) {
      checkAvailability();
    }
  }, [open]);

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      if (pollingCleanupRef.current) {
        pollingCleanupRef.current();
      }
    };
  }, []);

  const checkAvailability = async () => {
    setCheckingAvailability(true);
    try {
      const response = await axios.get('/api/stores/nearby/availability');
      setAvailability(response.data);
    } catch {
      setAvailability({
        available: false,
        has_google_places_key: false,
        has_firecrawl_key: false,
        has_location: hasLocation,
        can_auto_configure: false,
      });
    } finally {
      setCheckingAvailability(false);
    }
  };

  const getCurrentLocation = useCallback(() => {
    if (!navigator.geolocation) {
      return;
    }

    setGettingLocation(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setCurrentLocation({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        });
        setGettingLocation(false);
      },
      () => {
        setGettingLocation(false);
        setUseCurrentLocation(false);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  }, []);

  useEffect(() => {
    if (useCurrentLocation && !currentLocation) {
      getCurrentLocation();
    }
  }, [useCurrentLocation, currentLocation, getCurrentLocation]);

  const toggleCategory = (category: StoreCategory) => {
    setSelectedCategories((prev) =>
      prev.includes(category) ? prev.filter((c) => c !== category) : [...prev, category]
    );
  };

  const handlePreview = async () => {
    setPreviewing(true);
    setPreviewError(null);
    setPreviewStores([]);

    try {
      const payload: NearbyStoreDiscoveryRequest = {
        radius_miles: radiusMiles,
        categories: selectedCategories.length > 0 ? selectedCategories : undefined,
      };

      if (useCurrentLocation && currentLocation) {
        payload.latitude = currentLocation.lat;
        payload.longitude = currentLocation.lng;
      }

      const response = await axios.post('/api/stores/nearby/preview', payload);

      if (response.data.success) {
        setPreviewStores(response.data.stores || []);
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

  const handleDiscover = async () => {
    setDiscovering(true);
    setDiscoveryError(null);
    setDiscoveryResult(null);
    setDiscoveryLogs([]);
    setDiscoveryProgress(0);

    try {
      const payload: NearbyStoreDiscoveryRequest = {
        radius_miles: radiusMiles,
        categories: selectedCategories.length > 0 ? selectedCategories : undefined,
      };

      if (useCurrentLocation && currentLocation) {
        payload.latitude = currentLocation.lat;
        payload.longitude = currentLocation.lng;
      }

      const response = await axios.post('/api/stores/nearby/discover', payload);

      if (response.data.success) {
        setDiscoveryJobId(response.data.job_id);
        // Start polling for status and store cleanup function
        pollingCleanupRef.current = pollDiscoveryStatus(response.data.job_id);
      } else {
        setDiscoveryError(response.data.error || 'Discovery failed');
        setDiscovering(false);
      }
    } catch (err) {
      const error = err as { response?: { data?: { error?: string } } };
      setDiscoveryError(error.response?.data?.error || 'Failed to start discovery');
      setDiscovering(false);
    }
  };

  const pollDiscoveryStatus = useCallback((jobId: number) => {
    let cancelled = false;
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const poll = async () => {
      if (cancelled) return;
      
      try {
        const response = await axios.get(`/api/stores/nearby/${jobId}`);
        if (cancelled) return;
        
        const data = response.data;

        setDiscoveryStatus(data.status);
        setDiscoveryProgress(data.progress || 0);

        if (data.progress_logs) {
          setDiscoveryLogs(data.progress_logs);
        }

        if (data.status === 'completed') {
          setDiscoveryResult({
            stores_added: data.result?.stores_added || 0,
            stores_configured: data.result?.stores_configured || 0,
          });
          setDiscovering(false);
          onStoresAdded?.();
        } else if (data.status === 'failed') {
          setDiscoveryError(data.error || 'Discovery job failed');
          setDiscovering(false);
        } else if (data.status === 'cancelled') {
          setDiscoveryError('Discovery was cancelled');
          setDiscovering(false);
        } else if (!cancelled) {
          // Still processing, poll again
          timeoutId = setTimeout(poll, 2000);
        }
      } catch {
        if (!cancelled) {
          setDiscoveryError('Failed to get discovery status');
          setDiscovering(false);
        }
      }
    };

    poll();

    // Return cleanup function
    return () => {
      cancelled = true;
      if (timeoutId) clearTimeout(timeoutId);
    };
  }, [onStoresAdded]);

  const handleCancel = async () => {
    // Stop polling
    if (pollingCleanupRef.current) {
      pollingCleanupRef.current();
      pollingCleanupRef.current = null;
    }
    
    if (discoveryJobId) {
      try {
        await axios.post(`/api/stores/nearby/${discoveryJobId}/cancel`);
      } catch {
        // Ignore cancel errors
      }
    }
    setDiscovering(false);
  };

  const resetDialog = () => {
    setPreviewStores([]);
    setPreviewError(null);
    setDiscoveryResult(null);
    setDiscoveryError(null);
    setDiscoveryLogs([]);
    setDiscoveryProgress(0);
    setDiscoveryJobId(null);
    setDiscoveryStatus(null);
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
          // Success state
          <div className="space-y-4 py-4">
            <div className="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-950/20 rounded-lg border border-green-200 dark:border-green-900">
              <CheckCircle2 className="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="font-medium text-green-900 dark:text-green-100">Discovery Complete!</p>
                <p className="text-sm text-green-800 dark:text-green-200">
                  Added {discoveryResult.stores_added} new stores to your registry.
                  {discoveryResult.stores_configured > 0 && (
                    <> {discoveryResult.stores_configured} were auto-configured for price search.</>
                  )}
                </p>
              </div>
            </div>

            <DialogFooter>
              <Button onClick={() => handleOpenChange(false)}>Done</Button>
            </DialogFooter>
          </div>
        ) : discovering ? (
          // Discovery in progress
          <div className="space-y-4 py-4">
            <div className="flex items-center gap-3">
              <Loader2 className="h-5 w-5 animate-spin text-primary" />
              <div className="flex-1">
                <p className="font-medium">
                  {discoveryStatus === 'pending' ? 'Starting discovery...' : 'Discovering stores...'}
                </p>
                <div className="mt-2 h-2 bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-primary transition-all duration-300"
                    style={{ width: `${discoveryProgress}%` }}
                  />
                </div>
              </div>
            </div>

            {discoveryLogs.length > 0 && (
              <div className="bg-muted rounded-lg p-3 max-h-40 overflow-y-auto">
                <div className="space-y-1 font-mono text-xs">
                  {discoveryLogs.slice(-10).map((log, idx) => (
                    <p key={idx} className="text-muted-foreground">
                      {log}
                    </p>
                  ))}
                </div>
              </div>
            )}

            <DialogFooter>
              <Button variant="outline" onClick={handleCancel}>
                Cancel
              </Button>
            </DialogFooter>
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

            {/* Location Toggle */}
            {navigator.geolocation && (
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>Use Current Location</Label>
                  <p className="text-xs text-muted-foreground">
                    {useCurrentLocation && currentLocation
                      ? 'Using your current location'
                      : 'Use your home address from Settings'}
                  </p>
                </div>
                <Switch
                  checked={useCurrentLocation}
                  onCheckedChange={setUseCurrentLocation}
                  disabled={gettingLocation}
                />
              </div>
            )}

            {gettingLocation && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Navigation className="h-4 w-4 animate-pulse" />
                Getting your location...
              </div>
            )}

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
            {previewStores.length > 0 && (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <Label>Preview ({previewStores.length} stores found)</Label>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPreviewStores([])}
                    className="h-6 px-2"
                  >
                    <X className="h-3 w-3" />
                  </Button>
                </div>
                <div className="max-h-48 overflow-y-auto space-y-2 border rounded-lg p-2">
                  {previewStores.slice(0, 20).map((store) => (
                    <div
                      key={store.place_id}
                      className="flex items-center justify-between p-2 bg-muted/50 rounded"
                    >
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
                    </div>
                  ))}
                  {previewStores.length > 20 && (
                    <p className="text-xs text-center text-muted-foreground py-2">
                      +{previewStores.length - 20} more stores
                    </p>
                  )}
                </div>
              </div>
            )}

            {previewError && (
              <p className="text-sm text-red-500">{previewError}</p>
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
                disabled={previewing}
              >
                {previewing ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Search className="h-4 w-4 mr-2" />
                )}
                Preview
              </Button>
              <Button onClick={handleDiscover} disabled={discovering || (useCurrentLocation && !currentLocation)}>
                <Store className="h-4 w-4 mr-2" />
                Discover & Add Stores
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

export default NearbyStoreDiscovery;
