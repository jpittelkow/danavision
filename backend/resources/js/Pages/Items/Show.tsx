import { FormEvent, useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { PageProps, ListItem, VendorPrice, SmartFillResult } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import { CompactImageInput } from '@/Components/ImageUpload';
import PriceChart from '@/Components/PriceChart';
import { Switch } from '@/Components/ui/switch';
import { PriceUpdateStatus } from '@/Components/PriceUpdateStatus';
import {
  RefreshCw,
  ExternalLink,
  Clock,
  TrendingDown,
  Star,
  Package,
  Loader2,
  Save,
  ChevronLeft,
  Tag,
  DollarSign,
  BarChart3,
  MapPin,
  Scale,
  Sparkles,
  X,
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

// Helper to safely format a number
const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

// Helper to format price with unit for generic items
const formatPriceWithUnit = (
  price: number | string | null | undefined,
  isGeneric: boolean | undefined,
  unit: string | null | undefined
): string => {
  const formattedPrice = '$' + formatPrice(price);
  if (isGeneric && unit) {
    return `${formattedPrice}/${unit}`;
  }
  return formattedPrice;
};

// Format relative time
const formatRelativeTime = (dateString: string | null | undefined): string => {
  if (!dateString) return 'Never';
  
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
};

interface PriceHistoryPoint {
  date: string;
  price: number;
  retailer: string;
  in_stock: boolean;
}

interface ExtendedListItem extends Omit<ListItem, 'vendor_prices'> {
  vendor_prices?: VendorPrice[];
  shop_local?: boolean | null;
}

interface Props extends PageProps {
  item: ExtendedListItem;
  list: {
    id: number;
    name: string;
  };
  price_history: Record<string, PriceHistoryPoint[]>;
  can_edit: boolean;
}

export default function ItemShow({ auth, item, list, price_history, can_edit, flash }: Props) {
  const [isEditing, setIsEditing] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isSmartFilling, setIsSmartFilling] = useState(false);
  const [smartFillMessage, setSmartFillMessage] = useState<string | null>(null);
  const [hiddenVendors, setHiddenVendors] = useState<string[]>([]);
  const [suppressingVendor, setSuppressingVendor] = useState<string | null>(null);

  const { data, setData, patch, processing, errors, reset } = useForm({
    product_name: item.product_name,
    product_url: item.product_url || '',
    product_image_url: item.product_image_url || '',
    sku: item.sku || '',
    upc: item.upc || '',
    target_price: item.target_price?.toString() || '',
    notes: item.notes || '',
    priority: item.priority,
    is_generic: item.is_generic || false,
    unit_of_measure: item.unit_of_measure || '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    patch(`/items/${item.id}`, {
      onSuccess: () => setIsEditing(false),
    });
  };

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.post(`/items/${item.id}/refresh`, {}, {
      onFinish: () => setIsRefreshing(false),
    });
  };

  const handleCancel = () => {
    reset();
    setIsEditing(false);
  };

  /**
   * Handle Smart Fill - uses AI to populate product information
   */
  const handleSmartFill = async () => {
    setIsSmartFilling(true);
    setSmartFillMessage(null);

    try {
      const { data: result } = await axios.post<SmartFillResult>(`/items/${item.id}/smart-fill`);

      if (!result.success) {
        setSmartFillMessage(result.error || 'Smart fill failed - no data returned');
        return;
      }

      // Open edit mode if not already open
      if (!isEditing) {
        setIsEditing(true);
      }

      // Populate form fields with discovered data
      if (result.product_image_url) {
        setData('product_image_url', result.product_image_url);
      }
      if (result.sku && !data.sku) {
        setData('sku', result.sku);
      }
      if (result.upc && !data.upc) {
        setData('upc', result.upc);
      }
      if (result.description && !data.notes) {
        setData('notes', result.description);
      }
      if (result.suggested_target_price && !data.target_price) {
        setData('target_price', result.suggested_target_price.toString());
      }
      if (result.is_generic !== undefined) {
        setData('is_generic', result.is_generic);
        if (result.is_generic && result.unit_of_measure) {
          setData('unit_of_measure', result.unit_of_measure);
        }
      }

      // Build a descriptive success message
      const foundItems: string[] = [];
      if (result.product_image_url) foundItems.push('image');
      if (result.sku) foundItems.push('SKU');
      if (result.upc) foundItems.push('UPC');
      if (result.description) foundItems.push('description');
      if (result.suggested_target_price) foundItems.push('price');

      const providers = result.providers_used.map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(', ');
      
      if (foundItems.length > 0) {
        setSmartFillMessage(`Found ${foundItems.join(', ')} using ${providers || 'AI'}!`);
      } else {
        setSmartFillMessage(`AI search completed but no additional info found for this product.`);
      }

    } catch (error: unknown) {
      console.error('Smart fill error:', error);
      if (axios.isAxiosError(error)) {
        const message = error.response?.data?.error || error.response?.data?.message || error.message;
        setSmartFillMessage(`Smart fill failed: ${message}`);
      } else {
        setSmartFillMessage('Failed to connect to AI service. Check console for details.');
      }
    } finally {
      setIsSmartFilling(false);
    }
  };

  /**
   * Handle vendor suppression (add to global blacklist)
   */
  const handleSuppressVendor = async (vendorName: string) => {
    setSuppressingVendor(vendorName);

    try {
      await axios.post('/api/settings/suppress-vendor', { vendor: vendorName });
      // Hide the vendor row immediately
      setHiddenVendors((prev) => [...prev, vendorName]);
    } catch (error) {
      console.error('Failed to suppress vendor:', error);
    } finally {
      setSuppressingVendor(null);
    }
  };

  const vendorPrices = (item.vendor_prices || []).filter(
    (vp) => !hiddenVendors.includes(vp.vendor)
  );
  const hasPriceHistory = Object.keys(price_history).length > 0;

  // Calculate best and worst prices (only from vendors with prices)
  const vendorsWithPrices = vendorPrices.filter((v) => v.current_price != null);
  const inStockVendors = vendorsWithPrices.filter((v) => v.in_stock);
  const sortedByPrice = [...vendorsWithPrices].sort((a, b) => 
    (a.current_price || 0) - (b.current_price || 0)
  );
  const bestVendor = inStockVendors.length > 0 
    ? inStockVendors.reduce((min, v) => 
        (v.current_price || Infinity) < (min.current_price || Infinity) ? v : min
      )
    : sortedByPrice[0];
  const worstVendor = sortedByPrice.length > 1 ? sortedByPrice[sortedByPrice.length - 1] : null;

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title={item.product_name} />
      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <Link
            href={`/lists/${list.id}`}
            className="text-primary hover:underline inline-flex items-center gap-1"
          >
            <ChevronLeft className="h-4 w-4" />
            Back to {list.name}
          </Link>
        </div>

        {/* Flash Messages */}
        {flash?.success && (
          <div className="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-xl mb-6">
            {flash.success}
          </div>
        )}

        {/* Smart Fill Message */}
        {smartFillMessage && (
          <div className={cn(
            'px-4 py-3 rounded-xl mb-6 flex items-center gap-2',
            smartFillMessage.includes('Found') || smartFillMessage.includes('success')
              ? 'bg-violet-100 dark:bg-violet-900/30 border border-violet-400 dark:border-violet-700 text-violet-700 dark:text-violet-300'
              : 'bg-amber-100 dark:bg-amber-900/30 border border-amber-400 dark:border-amber-700 text-amber-700 dark:text-amber-300'
          )}>
            <Sparkles className="h-4 w-4 flex-shrink-0" />
            <span>{smartFillMessage}</span>
            <button
              onClick={() => setSmartFillMessage(null)}
              className="ml-auto p-1 hover:opacity-70"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Item Header Card */}
        <Card className="mb-6">
          <CardContent className="p-6">
            <div className="flex flex-col md:flex-row gap-6">
              {/* Product Image - links to best vendor's product URL */}
              <div className="flex-shrink-0">
                {(() => {
                  const productUrl = bestVendor?.product_url || item.product_url;
                  const imageContent = item.product_image_url ? (
                    <img
                      src={item.product_image_url}
                      alt={item.product_name}
                      className="w-48 h-48 object-contain rounded-lg bg-muted border"
                    />
                  ) : (
                    <div className="w-48 h-48 rounded-lg bg-muted flex items-center justify-center border">
                      <Package className="h-16 w-16 text-muted-foreground" />
                    </div>
                  );

                  return productUrl ? (
                    <a
                      href={productUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block hover:opacity-80 transition-opacity cursor-pointer relative group"
                      title={`View at ${bestVendor?.vendor || 'retailer'}`}
                    >
                      {imageContent}
                      <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                        <ExternalLink className="h-8 w-8 text-white" />
                      </div>
                    </a>
                  ) : (
                    imageContent
                  );
                })()}
              </div>

              {/* Product Info */}
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h1 className="text-2xl font-bold text-foreground">{item.product_name}</h1>
                    
                    {/* Badges */}
                    <div className="flex flex-wrap gap-2 mt-2">
                      {item.is_at_all_time_low && (
                        <Badge variant="success" className="gap-1">
                          <Star className="h-3 w-3" />
                          All-Time Low!
                        </Badge>
                      )}
                      {item.is_generic && (
                        <Badge variant="secondary" className="gap-1">
                          <Scale className="h-3 w-3" />
                          Generic
                          {item.unit_of_measure && (
                            <span className="text-xs opacity-75">
                              (per {item.unit_of_measure})
                            </span>
                          )}
                        </Badge>
                      )}
                      {item.sku && !item.is_generic && (
                        <Badge variant="outline" className="gap-1">
                          <Tag className="h-3 w-3" />
                          SKU: {item.sku}
                        </Badge>
                      )}
                      {item.priority === 'high' && (
                        <Badge variant="destructive">High Priority</Badge>
                      )}
                    </div>

                    {/* Shop Local Toggle */}
                    {can_edit && (
                      <div className="flex items-center gap-2 mt-3">
                        <Switch
                          checked={item.shop_local ?? false}
                          onCheckedChange={(checked) => {
                            router.patch(`/items/${item.id}`, { 
                              shop_local: checked 
                            }, { preserveScroll: true });
                          }}
                          className="data-[state=checked]:bg-green-600"
                        />
                        <span className="text-sm text-muted-foreground flex items-center gap-1">
                          <MapPin className="h-3.5 w-3.5" />
                          Shop Local
                          {item.shop_local === null && (
                            <span className="text-xs text-muted-foreground/60">(inherits from list)</span>
                          )}
                        </span>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  {can_edit && (
                    <div className="flex gap-2 flex-shrink-0">
                      <Button
                        variant="outline"
                        onClick={handleSmartFill}
                        disabled={isSmartFilling}
                        title="Use AI to auto-fill product details"
                        className="gap-1"
                      >
                        {isSmartFilling ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Sparkles className="h-4 w-4" />
                        )}
                        <span className="hidden sm:inline">Smart Fill</span>
                      </Button>
                      <Button
                        variant="outline"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                        title="Refresh prices"
                      >
                        {isRefreshing ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <RefreshCw className="h-4 w-4" />
                        )}
                      </Button>
                      <Button
                        variant={isEditing ? 'outline' : 'default'}
                        onClick={() => setIsEditing(!isEditing)}
                      >
                        {isEditing ? 'Cancel' : 'Edit'}
                      </Button>
                    </div>
                  )}
                </div>

                {/* Price Info */}
                <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <div>
                    <p className="text-xs text-muted-foreground">Current Best</p>
                    <p className="text-xl font-bold text-primary">
                      {bestVendor?.current_price != null
                        ? formatPriceWithUnit(bestVendor.current_price, item.is_generic, item.unit_of_measure)
                        : '—'}
                    </p>
                    {bestVendor && (
                      <p className="text-xs text-muted-foreground">@ {bestVendor.vendor}</p>
                    )}
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Target Price</p>
                    <p className="text-xl font-bold text-foreground">
                      {item.target_price != null ? `$${formatPrice(item.target_price)}` : '—'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Lowest Ever</p>
                    <p className="text-xl font-bold text-green-600">
                      {item.lowest_price != null ? `$${formatPrice(item.lowest_price)}` : '—'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Price Status</p>
                    <div className="mt-1">
                      <PriceUpdateStatus
                        itemId={item.id}
                        lastCheckedAt={item.last_checked_at || null}
                        onRetry={handleRefresh}
                      />
                    </div>
                  </div>
                </div>

                {item.notes && !isEditing && (
                  <p className="mt-4 text-sm text-muted-foreground">{item.notes}</p>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Edit Form */}
        {isEditing && can_edit && (
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Edit Item</CardTitle>
              <CardDescription>Update the item details</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="product_name">Product Name</Label>
                    <Input
                      id="product_name"
                      value={data.product_name}
                      onChange={(e) => setData('product_name', e.target.value)}
                      className="mt-1"
                    />
                    {errors.product_name && (
                      <p className="text-destructive text-xs mt-1">{errors.product_name}</p>
                    )}
                  </div>
                  <div>
                    <Label htmlFor="sku">SKU / Model Number</Label>
                    <Input
                      id="sku"
                      value={data.sku}
                      onChange={(e) => setData('sku', e.target.value)}
                      placeholder="e.g., WH-1000XM5"
                      className="mt-1"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="upc">UPC / Barcode</Label>
                    <Input
                      id="upc"
                      value={data.upc}
                      onChange={(e) => setData('upc', e.target.value)}
                      placeholder="12-digit barcode (for packaged products)"
                      className="mt-1"
                      maxLength={14}
                    />
                    <p className="text-xs text-muted-foreground mt-1">
                      Optional - helps with precise price matching
                    </p>
                  </div>
                  <div>
                    <Label htmlFor="product_image_url">Image URL</Label>
                    <Input
                      id="product_image_url"
                      type="url"
                      value={data.product_image_url}
                      onChange={(e) => setData('product_image_url', e.target.value)}
                      placeholder="https://..."
                      className="mt-1"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="target_price">Target Price</Label>
                    <Input
                      id="target_price"
                      type="number"
                      step="0.01"
                      value={data.target_price}
                      onChange={(e) => setData('target_price', e.target.value)}
                      placeholder="299.99"
                      className="mt-1"
                    />
                  </div>
                  <div>
                    <Label htmlFor="priority">Priority</Label>
                    <select
                      id="priority"
                      value={data.priority}
                      onChange={(e) => setData('priority', e.target.value as 'low' | 'medium' | 'high')}
                      className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                </div>

                <div>
                  <Label htmlFor="product_url">Product URL</Label>
                  <Input
                    id="product_url"
                    type="url"
                    value={data.product_url}
                    onChange={(e) => setData('product_url', e.target.value)}
                    placeholder="https://..."
                    className="mt-1"
                  />
                </div>

                {/* Generic Item Settings */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="flex items-center gap-3">
                    <Switch
                      checked={data.is_generic}
                      onCheckedChange={(checked) => {
                        setData('is_generic', checked);
                        if (!checked) {
                          setData('unit_of_measure', '');
                        } else if (!data.unit_of_measure) {
                          setData('unit_of_measure', 'lb');
                        }
                      }}
                      className="data-[state=checked]:bg-violet-600"
                    />
                    <div>
                      <Label className="flex items-center gap-1">
                        <Scale className="h-3.5 w-3.5" />
                        Generic Item
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Sold by weight, volume, or count
                      </p>
                    </div>
                  </div>
                  {data.is_generic && (
                    <div>
                      <Label>Unit of Measure</Label>
                      <Select
                        value={data.unit_of_measure}
                        onValueChange={(value) => setData('unit_of_measure', value)}
                      >
                        <SelectTrigger className="w-full mt-1">
                          <SelectValue placeholder="Select unit" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="lb">Pound (lb)</SelectItem>
                          <SelectItem value="oz">Ounce (oz)</SelectItem>
                          <SelectItem value="kg">Kilogram (kg)</SelectItem>
                          <SelectItem value="g">Gram (g)</SelectItem>
                          <SelectItem value="gallon">Gallon</SelectItem>
                          <SelectItem value="liter">Liter</SelectItem>
                          <SelectItem value="quart">Quart</SelectItem>
                          <SelectItem value="pint">Pint</SelectItem>
                          <SelectItem value="fl_oz">Fluid Ounce</SelectItem>
                          <SelectItem value="each">Each</SelectItem>
                          <SelectItem value="dozen">Dozen</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                </div>

                <div>
                  <Label htmlFor="notes">Notes</Label>
                  <textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    placeholder="Any notes about this item..."
                    rows={3}
                    className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-none"
                  />
                </div>

                <div className="flex gap-2 pt-2">
                  <Button type="button" variant="outline" onClick={handleCancel}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {processing ? (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    ) : (
                      <Save className="h-4 w-4 mr-2" />
                    )}
                    Save Changes
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {/* Vendor Prices Table */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <DollarSign className="h-5 w-5" />
              Vendor Prices
            </CardTitle>
            <CardDescription>
              Current prices across {vendorPrices.length} vendor{vendorPrices.length !== 1 ? 's' : ''}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {vendorPrices.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left py-2 px-3 font-medium">Vendor</th>
                      <th className="text-right py-2 px-3 font-medium">Current</th>
                      <th className="text-right py-2 px-3 font-medium">Lowest</th>
                      <th className="text-center py-2 px-3 font-medium">Status</th>
                      <th className="text-right py-2 px-3 font-medium">Updated</th>
                      <th className="text-center py-2 px-3 font-medium"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {vendorPrices.map((vp) => {
                      const isBest = vp === bestVendor;
                      const isWorst = vp === worstVendor && vendorsWithPrices.length > 1;
                      return (
                        <tr
                          key={vp.id}
                          className={cn(
                            'group border-b last:border-0 transition-colors',
                            isBest && 'bg-green-500/5'
                          )}
                        >
                          <td className="py-3 px-3">
                            <div className="flex items-center gap-2">
                              {vp.product_url ? (
                                <a
                                  href={vp.product_url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="font-medium text-primary hover:underline"
                                >
                                  {vp.vendor}
                                </a>
                              ) : (
                                <span className="font-medium">{vp.vendor}</span>
                              )}
                              {isBest && (
                                <Badge className="text-xs bg-green-600 hover:bg-green-600">Best Price</Badge>
                              )}
                              {isWorst && (
                                <Badge variant="outline" className="text-xs text-red-500/70 border-red-500/30">Highest</Badge>
                              )}
                            </div>
                            {vp.vendor_sku && (
                              <p className="text-xs text-muted-foreground">SKU: {vp.vendor_sku}</p>
                            )}
                          </td>
                          <td className="py-3 px-3 text-right">
                            <span className={cn(
                              'font-semibold',
                              isBest && 'text-green-600 dark:text-green-400',
                              isWorst && 'text-red-500/70',
                              !isBest && !isWorst && 'text-foreground'
                            )}>
                              {vp.current_price != null ? `$${formatPrice(vp.current_price)}` : '—'}
                            </span>
                            {vp.on_sale && vp.sale_percent_off && (
                              <span className="ml-2 text-green-600 text-xs">
                                {Math.round(vp.sale_percent_off)}% off
                              </span>
                            )}
                          </td>
                          <td className="py-3 px-3 text-right text-muted-foreground">
                            {vp.lowest_price != null ? `$${formatPrice(vp.lowest_price)}` : '—'}
                          </td>
                          <td className="py-3 px-3 text-center">
                            <div className="flex items-center justify-center gap-1">
                              {vp.in_stock ? (
                                <Badge variant="outline" className="text-xs text-muted-foreground">In Stock</Badge>
                              ) : (
                                <Badge variant="outline" className="text-xs text-muted-foreground/60">Out</Badge>
                              )}
                              {vp.is_at_all_time_low && (
                                <Badge variant="success" className="text-xs gap-0.5">
                                  <Star className="h-2.5 w-2.5" />
                                  ATL
                                </Badge>
                              )}
                            </div>
                          </td>
                          <td className="py-3 px-3 text-right text-xs text-muted-foreground">
                            {formatRelativeTime(vp.last_checked_at)}
                          </td>
                          <td className="py-3 px-3 text-center relative">
                            <div className="flex items-center justify-center gap-1">
                              {vp.product_url && (
                                <a
                                  href={vp.product_url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="text-primary hover:underline p-1"
                                >
                                  <ExternalLink className="h-4 w-4" />
                                </a>
                              )}
                              {/* Blacklist button - appears on hover */}
                              <button
                                onClick={() => handleSuppressVendor(vp.vendor)}
                                disabled={suppressingVendor === vp.vendor}
                                className="p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all"
                                title={`Hide ${vp.vendor} from all results`}
                              >
                                {suppressingVendor === vp.vendor ? (
                                  <Loader2 className="h-4 w-4 animate-spin text-red-500" />
                                ) : (
                                  <X className="h-4 w-4 text-red-500" />
                                )}
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8">
                <DollarSign className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p className="text-muted-foreground">No vendor prices tracked yet</p>
                <p className="text-sm text-muted-foreground mt-1">
                  Click "Refresh" to search for prices
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Price History Chart */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Price History
            </CardTitle>
            <CardDescription>
              Track price changes over time
            </CardDescription>
          </CardHeader>
          <CardContent>
            {hasPriceHistory ? (
              <PriceChart data={price_history} />
            ) : (
              <div className="text-center py-8">
                <BarChart3 className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p className="text-muted-foreground">No price history yet</p>
                <p className="text-sm text-muted-foreground mt-1">
                  Price history will appear after the first price check
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
