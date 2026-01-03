import { FormEvent, useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageProps, ShoppingList, ListItem } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import { Switch } from '@/Components/ui/switch';
import {
  RefreshCw,
  Trash2,
  Check,
  ExternalLink,
  Clock,
  TrendingDown,
  Star,
  AlertTriangle,
  ShoppingCart,
  Loader2,
  ChevronDown,
  ChevronUp,
  Package,
  MapPin,
  Scale,
} from 'lucide-react';

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

interface VendorPrice {
  id: number;
  vendor: string;
  vendor_sku?: string;
  product_url?: string;
  current_price: number | null;
  previous_price?: number | null;
  lowest_price?: number | null;
  on_sale: boolean;
  sale_percent_off?: number | null;
  in_stock: boolean;
  last_checked_at?: string | null;
  is_at_all_time_low?: boolean;
}

interface ExtendedListItem extends Omit<ListItem, 'vendor_prices'> {
  vendor_prices?: VendorPrice[];
  is_generic?: boolean;
  unit_of_measure?: string | null;
}

interface ExtendedShoppingList extends Omit<ShoppingList, 'items'> {
  items?: ExtendedListItem[];
}

interface Props extends PageProps {
  list: ExtendedShoppingList;
  can_edit: boolean;
  can_share: boolean;
}

function ItemCard({
  item,
  canEdit,
  onDelete,
  onMarkPurchased,
  onRefresh,
}: {
  item: ExtendedListItem;
  canEdit: boolean;
  onDelete: () => void;
  onMarkPurchased: () => void;
  onRefresh: () => void;
}) {
  const [showVendors, setShowVendors] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);

  const vendorPrices = item.vendor_prices || [];
  const hasSale = vendorPrices.some((v) => v.on_sale);
  const hasMultipleVendors = vendorPrices.length > 1;

  // Calculate best and worst prices
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

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.post(`/items/${item.id}/refresh`, {}, {
      onFinish: () => setIsRefreshing(false),
    });
  };

  return (
    <Card className={cn(item.is_purchased && 'opacity-60')}>
      <CardContent className="p-4">
        <div className="flex items-start gap-4">
          {/* Product Image */}
          {item.product_image_url && (
            <img
              src={item.product_image_url}
              alt={item.product_name}
              className="w-20 h-20 object-contain rounded-lg bg-muted flex-shrink-0"
            />
          )}
          {!item.product_image_url && (
            <div className="w-20 h-20 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
              <Package className="h-8 w-8 text-muted-foreground" />
            </div>
          )}

          {/* Item Details */}
          <div className="flex-1 min-w-0">
            <div className="flex items-start justify-between gap-2">
              <div className="flex-1 min-w-0">
                <Link
                  href={`/items/${item.id}`}
                  className={cn(
                    'font-semibold text-foreground hover:text-primary hover:underline',
                    item.is_purchased && 'line-through'
                  )}
                >
                  {item.product_name}
                </Link>
                
                {/* Badges */}
                <div className="flex flex-wrap gap-1 mt-1">
                  {item.is_at_all_time_low && (
                    <Badge variant="success" className="gap-1">
                      <Star className="h-3 w-3" />
                      All-Time Low!
                    </Badge>
                  )}
                  {hasSale && !item.is_at_all_time_low && (
                    <Badge variant="secondary" className="gap-1">
                      <TrendingDown className="h-3 w-3" />
                      On Sale
                    </Badge>
                  )}
                  {item.priority === 'high' && (
                    <Badge variant="destructive" className="gap-1">
                      <AlertTriangle className="h-3 w-3" />
                      High Priority
                    </Badge>
                  )}
                  {item.is_generic && (
                    <Badge variant="secondary" className="gap-1 text-xs">
                      <Scale className="h-2.5 w-2.5" />
                      {item.unit_of_measure ? `per ${item.unit_of_measure}` : 'Generic'}
                    </Badge>
                  )}
                  {item.sku && !item.is_generic && (
                    <Badge variant="outline" className="text-xs">
                      SKU: {item.sku}
                    </Badge>
                  )}
                </div>
              </div>

              {/* Actions */}
              {canEdit && !item.is_purchased && (
                <div className="flex items-center gap-1 flex-shrink-0">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={handleRefresh}
                    disabled={isRefreshing}
                    title="Refresh price"
                  >
                    <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={onMarkPurchased}
                    title="Mark as purchased"
                    className="text-green-600 hover:text-green-700"
                  >
                    <Check className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={onDelete}
                    title="Delete"
                    className="text-destructive hover:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              )}
            </div>

            {/* Price Info */}
            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
              {/* Best Price */}
              {bestVendor && (
                <div className="flex items-center gap-2">
                  <span className="text-lg font-bold text-primary">
                    {formatPriceWithUnit(bestVendor.current_price, item.is_generic, item.unit_of_measure)}
                  </span>
                  <span className="text-muted-foreground">@ {bestVendor.vendor}</span>
                  {bestVendor.product_url && (
                    <a
                      href={bestVendor.product_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary hover:underline"
                    >
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  )}
                </div>
              )}
              
              {/* Target Price */}
              {item.target_price != null && (
                <span className="text-muted-foreground">
                  Target: ${formatPrice(item.target_price)}
                </span>
              )}

              {/* Price Drop Indicator */}
              {item.previous_price != null && item.current_price != null && 
               Number(item.current_price) < Number(item.previous_price) && (
                <span className="text-green-600 dark:text-green-400 font-medium">
                  ↓ ${formatPrice(Number(item.previous_price) - Number(item.current_price))} off
                </span>
              )}
            </div>

            {/* Last Checked */}
            {item.last_checked_at && (
              <div className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                <Clock className="h-3 w-3" />
                Updated {formatRelativeTime(item.last_checked_at)}
              </div>
            )}

            {item.notes && (
              <p className="text-sm text-muted-foreground mt-2">{item.notes}</p>
            )}

            {/* Vendor Prices Expandable Section */}
            {hasMultipleVendors && (
              <div className="mt-3 pt-3 border-t border-border">
                <button
                  onClick={() => setShowVendors(!showVendors)}
                  className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                  {showVendors ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                  {vendorPrices.length} vendors
                </button>

                {showVendors && (
                  <div className="mt-3 space-y-2">
                    {vendorPrices.map((vp) => {
                      const isBest = vp === bestVendor;
                      const isWorst = vp === worstVendor && vendorsWithPrices.length > 1;
                      return (
                        <div
                          key={vp.id}
                          className={cn(
                            'flex items-center justify-between p-2 rounded-lg text-sm',
                            isBest ? 'bg-green-500/10 border border-green-500/20' : 'bg-muted'
                          )}
                        >
                          <div className="flex items-center gap-2">
                            <span className="font-medium">{vp.vendor}</span>
                            {isBest && (
                              <Badge className="text-xs bg-green-600 hover:bg-green-600">Best</Badge>
                            )}
                            {isWorst && (
                              <Badge variant="outline" className="text-xs text-red-500/70 border-red-500/30">High</Badge>
                            )}
                            {vp.on_sale && (
                              <Badge variant="secondary" className="text-xs">
                                {vp.sale_percent_off && `${Math.round(vp.sale_percent_off)}% off`}
                                {!vp.sale_percent_off && 'Sale'}
                              </Badge>
                            )}
                            {!vp.in_stock && (
                              <Badge variant="outline" className="text-xs text-muted-foreground/60">Out</Badge>
                            )}
                            {vp.is_at_all_time_low && (
                              <Badge variant="success" className="text-xs gap-1">
                                <Star className="h-2.5 w-2.5" />
                                ATL
                              </Badge>
                            )}
                          </div>
                          <div className="flex items-center gap-3">
                            {vp.current_price != null ? (
                              <span className={cn(
                                'font-semibold',
                                isBest && 'text-green-600 dark:text-green-400',
                                isWorst && 'text-red-500/70',
                                !isBest && !isWorst && 'text-foreground'
                              )}>
                                ${formatPrice(vp.current_price)}
                              </span>
                            ) : (
                              <span className="text-muted-foreground">N/A</span>
                            )}
                            {vp.product_url && (
                              <a
                                href={vp.product_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:underline"
                              >
                                <ExternalLink className="h-3.5 w-3.5" />
                              </a>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export default function ListsShow({ auth, list, can_edit, flash }: Props) {
  const [showAddItem, setShowAddItem] = useState(false);
  const [isRefreshingAll, setIsRefreshingAll] = useState(false);
  
  const { data, setData, post, processing, reset, errors } = useForm({
    product_name: '',
    product_url: '',
    target_price: '',
    notes: '',
    priority: 'medium',
  });

  const submitItem = (e: FormEvent) => {
    e.preventDefault();
    post(`/lists/${list.id}/items`, {
      onSuccess: () => {
        reset();
        setShowAddItem(false);
      },
    });
  };

  const deleteItem = (itemId: number) => {
    if (confirm('Delete this item?')) {
      router.delete(`/items/${itemId}`);
    }
  };

  const markPurchased = (itemId: number) => {
    router.post(`/items/${itemId}/purchased`);
  };

  const refreshAllPrices = () => {
    setIsRefreshingAll(true);
    router.post(`/lists/${list.id}/refresh`, {}, {
      onFinish: () => setIsRefreshingAll(false),
    });
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title={list.name} />
      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <Link href="/lists" className="text-primary hover:underline inline-flex items-center gap-1">
            ← Back to Lists
          </Link>
        </div>

        {/* Flash Messages */}
        {flash?.success && (
          <div className="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-xl mb-6">
            {flash.success}
          </div>
        )}

        {/* List Header */}
        <Card className="mb-6">
          <CardContent className="p-6">
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
              <div>
                <h1 className="text-2xl font-bold text-foreground">{list.name}</h1>
                {list.description && (
                  <p className="text-muted-foreground mt-1">{list.description}</p>
                )}
                <div className="flex items-center gap-4 mt-2">
                  <p className="text-sm text-muted-foreground">
                    {list.items?.length || 0} items
                  </p>
                  {can_edit && (
                    <div className="flex items-center gap-2">
                      <Switch
                        checked={list.shop_local}
                        onCheckedChange={(checked) => {
                          router.patch(`/lists/${list.id}`, { 
                            name: list.name,
                            shop_local: checked 
                          }, { preserveScroll: true });
                        }}
                        className="data-[state=checked]:bg-green-600"
                      />
                      <span className="text-sm text-muted-foreground flex items-center gap-1">
                        <MapPin className="h-3.5 w-3.5" />
                        Shop Local
                      </span>
                    </div>
                  )}
                </div>
              </div>
              <div className="flex gap-2">
                {can_edit && (
                  <>
                    <Button
                      variant="outline"
                      onClick={refreshAllPrices}
                      disabled={isRefreshingAll || !list.items?.length}
                    >
                      {isRefreshingAll ? (
                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      ) : (
                        <RefreshCw className="h-4 w-4 mr-2" />
                      )}
                      Refresh All
                    </Button>
                    <Button onClick={() => setShowAddItem(true)}>
                      + Add Item
                    </Button>
                  </>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Add Item Form */}
        {showAddItem && (
          <Card className="mb-6">
            <CardContent className="p-6">
              <h2 className="text-lg font-semibold text-foreground mb-4">Add New Item</h2>
              <form onSubmit={submitItem}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">
                      Product Name *
                    </label>
                    <Input
                      type="text"
                      value={data.product_name}
                      onChange={(e) => setData('product_name', e.target.value)}
                      placeholder="e.g., Sony WH-1000XM5"
                    />
                    {errors.product_name && (
                      <p className="text-destructive text-xs mt-1">{errors.product_name}</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">
                      Target Price
                    </label>
                    <Input
                      type="number"
                      value={data.target_price}
                      onChange={(e) => setData('target_price', e.target.value)}
                      placeholder="299.99"
                      step="0.01"
                    />
                  </div>
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-foreground mb-1">
                    Product URL
                  </label>
                  <Input
                    type="url"
                    value={data.product_url}
                    onChange={(e) => setData('product_url', e.target.value)}
                    placeholder="https://..."
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-foreground mb-1">
                    Notes
                  </label>
                  <Input
                    type="text"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    placeholder="Any notes..."
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setShowAddItem(false)}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Adding...' : 'Add Item'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {/* Items */}
        <div className="space-y-4">
          {list.items && list.items.length > 0 ? (
            list.items.map((item) => (
              <ItemCard
                key={item.id}
                item={item}
                canEdit={can_edit}
                onDelete={() => deleteItem(item.id)}
                onMarkPurchased={() => markPurchased(item.id)}
                onRefresh={() => {}}
              />
            ))
          ) : (
            <Card>
              <CardContent className="py-12 text-center">
                <ShoppingCart className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p className="text-muted-foreground">No items in this list yet</p>
                {can_edit && (
                  <Button
                    variant="link"
                    onClick={() => setShowAddItem(true)}
                    className="mt-4"
                  >
                    Add your first item
                  </Button>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
