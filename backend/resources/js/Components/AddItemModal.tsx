import { useState, useEffect, FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { Switch } from '@/Components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { cn } from '@/lib/utils';
import {
  Loader2,
  Plus,
  ImageIcon,
  ExternalLink,
  CheckCircle2,
  AlertCircle,
  Scale,
  Barcode,
  ShoppingCart,
} from 'lucide-react';

/**
 * Product data passed from search results
 */
interface ProductData {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
  upc?: string;
  in_stock?: boolean;
  other_prices?: {
    retailer: string;
    price: number;
    url: string;
  }[];
}

/**
 * Price result from the price-details API
 */
interface PriceResult {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
  upc?: string;
  in_stock?: boolean;
}

interface AddItemModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: ProductData | null;
  lists: { id: number; name: string }[];
  uploadedImage?: string;
  isGeneric?: boolean;
  unitOfMeasure?: string;
}

const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

/**
 * Get proxy URL for external images to avoid CORS issues
 */
const getProxiedImageUrl = (url: string | undefined) => {
  if (!url) return undefined;
  return `/api/proxy-image?url=${encodeURIComponent(url)}`;
};

/**
 * Modal for adding an item to a shopping list.
 * Opens with pre-filled data from search results and fetches detailed pricing.
 */
export function AddItemModal({
  isOpen,
  onClose,
  product,
  lists,
  uploadedImage,
  isGeneric = false,
  unitOfMeasure,
}: AddItemModalProps) {
  const [isLoadingPrices, setIsLoadingPrices] = useState(false);
  const [priceResults, setPriceResults] = useState<PriceResult[]>([]);
  const [priceError, setPriceError] = useState<string | null>(null);
  const [selectedPriceIndex, setSelectedPriceIndex] = useState<number>(0);

  const [selectedListId, setSelectedListId] = useState<string>(
    lists.length > 0 ? String(lists[0].id) : ''
  );

  const addForm = useForm({
    list_id: selectedListId,
    product_name: '',
    product_query: '',
    product_url: '',
    product_image_url: '',
    uploaded_image: uploadedImage || '',
    sku: '',
    upc: '',
    current_price: '',
    current_retailer: '',
    target_price: '',
    notes: '',
    priority: 'medium',
    is_generic: isGeneric,
    unit_of_measure: unitOfMeasure || '',
  });

  // Pre-fill form when modal opens with a product
  // We only want to reset the form when a new product is selected or modal opens
  useEffect(() => {
    if (product && isOpen) {
      // Reset state for new product
      setPriceResults([]);
      setPriceError(null);
      setSelectedPriceIndex(0);
      
      // Pre-fill form with product data
      addForm.setData({
        list_id: lists.length > 0 ? String(lists[0].id) : '',
        product_name: product.title,
        product_query: product.title,
        product_url: product.url,
        product_image_url: product.image_url || '',
        uploaded_image: uploadedImage || '',
        sku: '',
        upc: product.upc || '',
        current_price: String(product.price),
        current_retailer: product.retailer,
        target_price: '',
        notes: '',
        priority: 'medium',
        is_generic: isGeneric,
        unit_of_measure: unitOfMeasure || '',
      });
      
      // Update list selection to match form
      if (lists.length > 0) {
        setSelectedListId(String(lists[0].id));
      }

      // Fetch detailed pricing
      fetchPriceDetails(product.title, product.upc);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [product?.title, isOpen]);

  // Sync list selection with form data
  useEffect(() => {
    if (selectedListId && addForm.data.list_id !== selectedListId) {
      addForm.setData('list_id', selectedListId);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedListId]);

  /**
   * Fetch detailed price information from all retailers
   */
  const fetchPriceDetails = async (productName: string, upc?: string) => {
    setIsLoadingPrices(true);
    setPriceError(null);
    setPriceResults([]);

    try {
      const response = await fetch('/smart-add/price-details', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          product_name: productName,
          upc: upc || null,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to fetch price details');
      }

      const data = await response.json();
      
      if (data.error) {
        setPriceError(data.error);
      } else {
        setPriceResults(data.results || []);
      }
    } catch (error) {
      setPriceError('Failed to load price details. You can still add the item.');
    } finally {
      setIsLoadingPrices(false);
    }
  };

  /**
   * Select a price option and update the form
   */
  const handleSelectPrice = (index: number) => {
    setSelectedPriceIndex(index);
    const selected = priceResults[index];
    if (selected) {
      addForm.setData({
        ...addForm.data,
        current_price: String(selected.price),
        current_retailer: selected.retailer,
        product_url: selected.url,
        product_image_url: selected.image_url || addForm.data.product_image_url,
        upc: selected.upc || addForm.data.upc,
      });
    }
  };

  /**
   * Submit the form to add item to list
   */
  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    addForm.post('/smart-add/add', {
      onSuccess: () => {
        onClose();
      },
    });
  };

  if (!product) return null;

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plus className="h-5 w-5" />
            Add to Shopping List
          </DialogTitle>
          <DialogDescription>
            Review the product details and select a list to add it to.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Product Preview */}
          <div className="flex gap-4 p-4 bg-muted/50 rounded-lg">
            {(product.image_url || uploadedImage) ? (
              <img
                src={uploadedImage || getProxiedImageUrl(product.image_url)}
                alt={product.title}
                className="w-20 h-20 object-contain rounded-lg bg-background flex-shrink-0"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  if (product.image_url && !target.src.includes(product.image_url)) {
                    target.src = product.image_url;
                  }
                }}
              />
            ) : (
              <div className="w-20 h-20 rounded-lg bg-background flex items-center justify-center flex-shrink-0">
                <ImageIcon className="h-8 w-8 text-muted-foreground" />
              </div>
            )}
            <div className="flex-1 min-w-0">
              <h3 className="font-medium text-foreground line-clamp-2">{product.title}</h3>
              <div className="flex items-center gap-2 mt-1">
                <span className="text-xl font-bold text-primary">
                  ${formatPrice(addForm.data.current_price)}
                </span>
                <Badge variant="secondary">{addForm.data.current_retailer}</Badge>
              </div>
              {addForm.data.upc && (
                <div className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                  <Barcode className="h-3 w-3" />
                  UPC: {addForm.data.upc}
                </div>
              )}
            </div>
          </div>

          {/* Price Options */}
          {(isLoadingPrices || priceResults.length > 0) && (
            <div className="space-y-3">
              <h4 className="text-sm font-medium text-foreground">
                {isLoadingPrices ? 'Loading prices...' : `${priceResults.length} Retailer Options`}
              </h4>

              {isLoadingPrices ? (
                <div className="flex items-center justify-center py-6">
                  <Loader2 className="h-6 w-6 animate-spin text-violet-500" />
                  <span className="ml-2 text-sm text-muted-foreground">
                    Finding best prices...
                  </span>
                </div>
              ) : (
                <div className="grid gap-2 max-h-48 overflow-y-auto">
                  {priceResults.map((result, index) => (
                    <Card
                      key={`${result.retailer}-${index}`}
                      className={cn(
                        'cursor-pointer transition-all',
                        selectedPriceIndex === index
                          ? 'border-violet-500 bg-violet-500/5 ring-1 ring-violet-500/20'
                          : 'hover:border-violet-400/50'
                      )}
                      onClick={() => handleSelectPrice(index)}
                    >
                      <CardContent className="p-3 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <Badge variant="outline">{result.retailer}</Badge>
                          <span className="font-semibold">${formatPrice(result.price)}</span>
                          {result.in_stock === false && (
                            <Badge variant="secondary" className="text-xs">Out of stock</Badge>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          {result.url && (
                            <a
                              href={result.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              onClick={(e) => e.stopPropagation()}
                              className="text-xs text-primary hover:underline flex items-center gap-1"
                            >
                              View <ExternalLink className="h-3 w-3" />
                            </a>
                          )}
                          {selectedPriceIndex === index && (
                            <CheckCircle2 className="h-5 w-5 text-violet-500" />
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}

              {priceError && (
                <div className="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                  <AlertCircle className="h-4 w-4" />
                  {priceError}
                </div>
              )}
            </div>
          )}

          {/* Form Fields */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">
                Shopping List *
              </label>
              <Select value={selectedListId} onValueChange={setSelectedListId}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select a list" />
                </SelectTrigger>
                <SelectContent>
                  {lists.map((list) => (
                    <SelectItem key={list.id} value={String(list.id)}>
                      {list.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">
                Product Name *
              </label>
              <Input
                type="text"
                value={addForm.data.product_name}
                onChange={(e) => addForm.setData('product_name', e.target.value)}
                placeholder="Product name"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">
                Target Price (optional)
              </label>
              <Input
                type="number"
                value={addForm.data.target_price}
                onChange={(e) => addForm.setData('target_price', e.target.value)}
                placeholder="Notify when price drops to..."
                step="0.01"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">
                Priority
              </label>
              <Select
                value={addForm.data.priority}
                onValueChange={(value) => addForm.setData('priority', value)}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Low</SelectItem>
                  <SelectItem value="medium">Medium</SelectItem>
                  <SelectItem value="high">High</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Generic Item Settings */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="flex items-center gap-3">
              <Switch
                checked={addForm.data.is_generic}
                onCheckedChange={(checked) => {
                  addForm.setData('is_generic', checked);
                  if (!checked) {
                    addForm.setData('unit_of_measure', '');
                  } else if (!addForm.data.unit_of_measure) {
                    addForm.setData('unit_of_measure', 'lb');
                  }
                }}
                className="data-[state=checked]:bg-violet-600"
              />
              <div>
                <label className="text-sm font-medium text-foreground flex items-center gap-1">
                  <Scale className="h-3.5 w-3.5" />
                  Generic Item
                </label>
                <p className="text-xs text-muted-foreground">
                  Sold by weight, volume, or count
                </p>
              </div>
            </div>

            {addForm.data.is_generic && (
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">
                  Unit of Measure
                </label>
                <Select
                  value={addForm.data.unit_of_measure}
                  onValueChange={(value) => addForm.setData('unit_of_measure', value)}
                >
                  <SelectTrigger className="w-full">
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
            <label className="block text-sm font-medium text-foreground mb-1">
              Notes (optional)
            </label>
            <Input
              type="text"
              value={addForm.data.notes}
              onChange={(e) => addForm.setData('notes', e.target.value)}
              placeholder="Any notes about this item..."
            />
          </div>

          {/* No Lists Warning */}
          {lists.length === 0 && (
            <div className="flex items-center gap-3 p-4 bg-amber-500/10 border border-amber-400/50 rounded-lg">
              <AlertCircle className="h-5 w-5 text-amber-500" />
              <div>
                <p className="text-sm font-medium text-foreground">No shopping lists found</p>
                <p className="text-xs text-muted-foreground">
                  Create a shopping list first to add items.
                </p>
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={addForm.processing || !addForm.data.product_name || !selectedListId || lists.length === 0}
              className="gap-2 bg-green-600 hover:bg-green-700"
            >
              {addForm.processing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Adding...
                </>
              ) : (
                <>
                  <ShoppingCart className="h-4 w-4" />
                  Add to List
                </>
              )}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default AddItemModal;
