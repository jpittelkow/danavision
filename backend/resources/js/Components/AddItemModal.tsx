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
import { Switch } from '@/Components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Loader2,
  Plus,
  ImageIcon,
  AlertCircle,
  Scale,
  Barcode,
  ShoppingCart,
} from 'lucide-react';

/**
 * Product data passed to the modal
 */
interface ProductData {
  product_name: string;
  brand?: string | null;
  model?: string | null;
  category?: string | null;
  upc?: string | null;
  is_generic?: boolean;
  unit_of_measure?: string | null;
  image_url?: string | null;
}

interface AddItemModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: ProductData | null;
  lists: { id: number; name: string }[];
  uploadedImage?: string;
}

const UNITS_OF_MEASURE = [
  { value: 'lb', label: 'Pound (lb)' },
  { value: 'oz', label: 'Ounce (oz)' },
  { value: 'kg', label: 'Kilogram (kg)' },
  { value: 'g', label: 'Gram (g)' },
  { value: 'gallon', label: 'Gallon' },
  { value: 'liter', label: 'Liter' },
  { value: 'quart', label: 'Quart' },
  { value: 'pint', label: 'Pint' },
  { value: 'fl_oz', label: 'Fluid Ounce' },
  { value: 'each', label: 'Each' },
  { value: 'dozen', label: 'Dozen' },
];

/**
 * Get proxy URL for external images to avoid CORS issues
 */
const getProxiedImageUrl = (url: string | undefined | null) => {
  if (!url) return undefined;
  return `/api/proxy-image?url=${encodeURIComponent(url)}`;
};

/**
 * Simple modal for adding an item to a shopping list.
 * Pre-filled with product data, price search runs after adding.
 */
export function AddItemModal({
  isOpen,
  onClose,
  product,
  lists,
  uploadedImage,
}: AddItemModalProps) {
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
    target_price: '',
    notes: '',
    priority: 'medium',
    is_generic: false,
    unit_of_measure: '',
  });

  // Pre-fill form when modal opens with a product
  useEffect(() => {
    if (product && isOpen) {
      addForm.setData({
        list_id: lists.length > 0 ? String(lists[0].id) : '',
        product_name: product.product_name,
        product_query: product.product_name,
        product_url: '',
        product_image_url: product.image_url || '',
        uploaded_image: uploadedImage || '',
        sku: '',
        upc: product.upc || '',
        target_price: '',
        notes: '',
        priority: 'medium',
        is_generic: product.is_generic || false,
        unit_of_measure: product.unit_of_measure || '',
      });

      if (lists.length > 0) {
        setSelectedListId(String(lists[0].id));
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [product?.product_name, isOpen]);

  // Sync list selection with form data
  useEffect(() => {
    if (selectedListId && addForm.data.list_id !== selectedListId) {
      addForm.setData('list_id', selectedListId);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedListId]);

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
      <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plus className="h-5 w-5" />
            Add to Shopping List
          </DialogTitle>
          <DialogDescription>
            Add this product to your shopping list. Price search will run automatically.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Product Preview */}
          <div className="flex gap-4 p-4 bg-muted/50 rounded-lg">
            {(product.image_url || uploadedImage) ? (
              <img
                src={uploadedImage || getProxiedImageUrl(product.image_url)}
                alt={product.product_name}
                className="w-16 h-16 object-contain rounded-lg bg-background flex-shrink-0"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  if (product.image_url && !target.src.includes(product.image_url)) {
                    target.src = product.image_url;
                  }
                }}
              />
            ) : (
              <div className="w-16 h-16 rounded-lg bg-background flex items-center justify-center flex-shrink-0">
                <ImageIcon className="h-6 w-6 text-muted-foreground" />
              </div>
            )}
            <div className="flex-1 min-w-0">
              <h3 className="font-medium text-foreground line-clamp-2">{product.product_name}</h3>
              {product.brand && (
                <p className="text-sm text-muted-foreground">
                  {product.brand}{product.model ? ` • ${product.model}` : ''}
                </p>
              )}
              {product.upc && (
                <div className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                  <Barcode className="h-3 w-3" />
                  UPC: {product.upc}
                </div>
              )}
            </div>
          </div>

          {/* List Selection */}
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

          {/* Product Name */}
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

          <div className="grid grid-cols-2 gap-4">
            {/* Target Price */}
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">
                Target Price
              </label>
              <Input
                type="number"
                value={addForm.data.target_price}
                onChange={(e) => addForm.setData('target_price', e.target.value)}
                placeholder="Optional"
                step="0.01"
              />
            </div>

            {/* Priority */}
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

          {/* Generic Item Toggle */}
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
            <div className="flex-1">
              <label className="text-sm font-medium text-foreground flex items-center gap-1">
                <Scale className="h-3.5 w-3.5" />
                Generic Item
              </label>
              <p className="text-xs text-muted-foreground">
                Sold by weight, volume, or count
              </p>
            </div>
            {addForm.data.is_generic && (
              <Select
                value={addForm.data.unit_of_measure}
                onValueChange={(value) => addForm.setData('unit_of_measure', value)}
              >
                <SelectTrigger className="w-32">
                  <SelectValue placeholder="Unit" />
                </SelectTrigger>
                <SelectContent>
                  {UNITS_OF_MEASURE.map((unit) => (
                    <SelectItem key={unit.value} value={unit.value}>
                      {unit.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              Notes
            </label>
            <Input
              type="text"
              value={addForm.data.notes}
              onChange={(e) => addForm.setData('notes', e.target.value)}
              placeholder="Optional notes..."
            />
          </div>

          {/* No Lists Warning */}
          {lists.length === 0 && (
            <div className="flex items-center gap-3 p-3 bg-amber-500/10 border border-amber-400/50 rounded-lg">
              <AlertCircle className="h-4 w-4 text-amber-500 flex-shrink-0" />
              <p className="text-sm text-foreground">
                Create a shopping list first to add items.
              </p>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
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

          <p className="text-xs text-muted-foreground text-center">
            Price search will run automatically after adding.
          </p>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default AddItemModal;
