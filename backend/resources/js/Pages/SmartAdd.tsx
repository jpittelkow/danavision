import { FormEvent, useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { PageProps, ShoppingList } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { cn, isMobileDevice } from '@/lib/utils';
import {
  Sparkles,
  Camera,
  Upload,
  Search,
  Loader2,
  X,
  AlertCircle,
  Plus,
  ImageIcon,
  Zap,
  ShoppingCart,
  RefreshCw,
  Scale,
  Package,
  Barcode,
  Check,
  ExternalLink,
} from 'lucide-react';

/**
 * Product suggestion from AI identification
 */
interface ProductSuggestion {
  product_name: string;
  brand: string | null;
  model: string | null;
  category: string | null;
  upc: string | null;
  is_generic: boolean;
  unit_of_measure: string | null;
  confidence: number;
  image_url: string | null;
}

interface Props extends PageProps {
  lists: Pick<ShoppingList, 'id' | 'name'>[];
}

type AnalysisState = 'idle' | 'uploading' | 'analyzing' | 'results' | 'error';

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

// Confidence indicator component
function ConfidenceIndicator({ confidence }: { confidence: number }) {
  const getColor = () => {
    if (confidence >= 80) return 'bg-green-500';
    if (confidence >= 60) return 'bg-yellow-500';
    return 'bg-orange-500';
  };

  return (
    <div className="flex items-center gap-2">
      <div className="flex-1 h-1.5 bg-muted rounded-full overflow-hidden max-w-[60px]">
        <div
          className={cn('h-full transition-all duration-500', getColor())}
          style={{ width: `${confidence}%` }}
        />
      </div>
      <span className="text-xs text-muted-foreground">{confidence}%</span>
    </div>
  );
}

export default function SmartAdd({ auth, lists, flash }: Props) {
  const [analysisState, setAnalysisState] = useState<AnalysisState>('idle');
  const [mode, setMode] = useState<'idle' | 'image' | 'text'>('idle');
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [uploadedImage, setUploadedImage] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [userPrompt, setUserPrompt] = useState('');

  // Product suggestions from AI
  const [suggestions, setSuggestions] = useState<ProductSuggestion[]>([]);
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
  const [providersUsed, setProvidersUsed] = useState<string[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Add to list form
  const [selectedListId, setSelectedListId] = useState<string>(
    lists.length > 0 ? String(lists[0].id) : ''
  );

  const addForm = useForm({
    list_id: selectedListId,
    product_name: '',
    product_query: '',
    product_url: '',
    product_image_url: '',
    uploaded_image: '',
    sku: '',
    upc: '',
    target_price: '',
    notes: '',
    priority: 'medium',
    is_generic: false,
    unit_of_measure: '',
  });

  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);
  const isMobile = isMobileDevice();

  /**
   * Handle file selection for image upload
   */
  const handleFileSelect = (file: File) => {
    if (!file.type.startsWith('image/')) {
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      alert('Image must be less than 10MB');
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      const base64 = reader.result as string;
      setImagePreview(base64);
      setUploadedImage(base64);
      setMode('image');
      // Reset previous results
      setSuggestions([]);
      setSelectedIndex(null);
      setErrorMessage(null);
    };
    reader.readAsDataURL(file);
  };

  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      handleFileSelect(file);
    }
    if (e.target) {
      e.target.value = '';
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files[0];
    if (file) {
      handleFileSelect(file);
    }
  };

  /**
   * Submit image for AI product identification
   */
  const submitImageIdentification = async (e: FormEvent) => {
    e.preventDefault();
    if (!uploadedImage) return;

    setAnalysisState('analyzing');
    setSuggestions([]);
    setSelectedIndex(null);
    setErrorMessage(null);

    try {
      const { data } = await axios.post('/smart-add/identify', {
        image: uploadedImage,
        query: userPrompt || null,
      });

      if (data.error && (!data.results || data.results.length === 0)) {
        setErrorMessage(data.error);
        setAnalysisState('error');
      } else if (data.results && data.results.length > 0) {
        setSuggestions(data.results);
        setProvidersUsed(data.providers_used || []);
        setAnalysisState('results');
      } else {
        setErrorMessage('Could not identify the product. Try a different image or add more context.');
        setAnalysisState('error');
      }
    } catch (error: unknown) {
      console.error('Smart Add identify error:', error);
      if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message || error.response?.data?.error || error.message;
        setErrorMessage(`Failed to analyze image: ${message}`);
      } else {
        setErrorMessage(`Failed to analyze image: ${error instanceof Error ? error.message : 'Unknown error'}`);
      }
      setAnalysisState('error');
    }
  };

  /**
   * Submit text search for AI product identification
   */
  const submitTextIdentification = async (e: FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;

    setAnalysisState('analyzing');
    setSuggestions([]);
    setSelectedIndex(null);
    setErrorMessage(null);

    try {
      const { data } = await axios.post('/smart-add/identify', {
        query: searchQuery,
      });

      if (data.error && (!data.results || data.results.length === 0)) {
        setErrorMessage(data.error);
        setAnalysisState('error');
      } else if (data.results && data.results.length > 0) {
        setSuggestions(data.results);
        setProvidersUsed(data.providers_used || []);
        setAnalysisState('results');
      } else {
        setErrorMessage('Could not find any products matching your search.');
        setAnalysisState('error');
      }
    } catch (error: unknown) {
      console.error('Smart Add identify error:', error);
      if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message || error.response?.data?.error || error.message;
        setErrorMessage(`Failed to search: ${message}`);
      } else {
        setErrorMessage(`Failed to search: ${error instanceof Error ? error.message : 'Unknown error'}`);
      }
      setAnalysisState('error');
    }
  };

  /**
   * Handle product selection from suggestions
   */
  const handleSelectProduct = (index: number) => {
    setSelectedIndex(index);
    const product = suggestions[index];

    // Pre-fill the add form with selected product data
    addForm.setData({
      list_id: selectedListId,
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
      is_generic: product.is_generic,
      unit_of_measure: product.unit_of_measure || '',
    });
  };

  /**
   * Submit the add to list form
   */
  const handleSubmitAdd = (e: FormEvent) => {
    e.preventDefault();
    if (!addForm.data.product_name || !selectedListId) return;

    // Use transform to ensure list_id is set at submission time
    addForm.transform((data) => ({
      ...data,
      list_id: selectedListId,
    })).post('/smart-add/add', {
      onSuccess: () => {
        // Reset state after successful add
        resetAll();
      },
    });
  };

  /**
   * Reset all state
   */
  const resetAll = () => {
    setAnalysisState('idle');
    setMode('idle');
    setImagePreview(null);
    setUploadedImage(null);
    setSearchQuery('');
    setUserPrompt('');
    setSuggestions([]);
    setSelectedIndex(null);
    setProvidersUsed([]);
    setErrorMessage(null);
    addForm.reset();
  };

  /**
   * Get proxy URL for external images
   */
  const getProxiedImageUrl = (url: string | undefined | null) => {
    if (!url) return undefined;
    return `/api/proxy-image?url=${encodeURIComponent(url)}`;
  };

  /**
   * Build Google search URL for product verification
   */
  const buildGoogleSearchUrl = (product: ProductSuggestion): string => {
    const searchTerms = [product.brand, product.model, product.product_name]
      .filter(Boolean)
      .join(' ');
    return `https://www.google.com/search?q=${encodeURIComponent(searchTerms)}&tbm=shop`;
  };

  const displayedSuggestions = suggestions.slice(0, 5);

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Smart Add" />
      <div className="p-6 lg:p-8 max-w-3xl mx-auto">
        {/* Header */}
        <div className="mb-8 text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 mb-4">
            <Sparkles className="h-8 w-8 text-white" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Smart Add</h1>
          <p className="text-muted-foreground max-w-md mx-auto">
            Upload an image or search for a product. AI will identify it and you can add it to your list.
          </p>
        </div>

        {/* Error Display */}
        {errorMessage && analysisState === 'error' && (
          <Card className="mb-6 border-destructive">
            <CardContent className="flex items-center gap-3 py-4">
              <AlertCircle className="h-5 w-5 text-destructive flex-shrink-0" />
              <p className="text-destructive">{errorMessage}</p>
              <Button variant="outline" size="sm" onClick={resetAll} className="ml-auto">
                Try Again
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Input Section - Show when no results yet */}
        {analysisState === 'idle' && (
          <>
            {/* Text Search Card */}
            <Card className="mb-6">
              <CardContent className="p-6">
                <form onSubmit={submitTextIdentification} className="flex gap-2">
                  <div className="relative flex-1">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                    <Input
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="pl-12 h-12"
                      placeholder="Enter product name or description..."
                    />
                  </div>
                  <Button
                    type="submit"
                    disabled={!searchQuery.trim()}
                    className="h-12 px-6 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600"
                  >
                    Search
                  </Button>
                </form>
              </CardContent>
            </Card>

            {/* OR Divider */}
            <div className="relative my-6">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-border" />
              </div>
              <div className="relative flex justify-center">
                <span className="px-3 bg-background text-sm text-muted-foreground">OR</span>
              </div>
            </div>

            {/* Image Upload */}
            <Card>
              <CardContent className="p-6">
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={handleImageUpload}
                  className="hidden"
                />
                <input
                  ref={cameraInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  capture="environment"
                  onChange={handleImageUpload}
                  className="hidden"
                />

                <div className="flex flex-col sm:flex-row gap-4">
                  {/* Camera button - mobile only */}
                  {isMobile && (
                    <button
                      type="button"
                      onClick={() => cameraInputRef.current?.click()}
                      className={cn(
                        'flex flex-col items-center justify-center gap-3 py-8 px-8 border-2 border-dashed rounded-2xl transition-all',
                        'border-violet-400/50 bg-violet-500/5 hover:border-violet-400 hover:bg-violet-500/10'
                      )}
                    >
                      <div className="w-14 h-14 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center">
                        <Camera className="h-7 w-7 text-white" />
                      </div>
                      <span className="text-base font-medium text-violet-600 dark:text-violet-400">
                        Take Photo
                      </span>
                    </button>
                  )}

                  {/* Upload area */}
                  <div
                    className={cn(
                      'flex-1 border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer',
                      isDragging
                        ? 'border-violet-500 bg-violet-500/10'
                        : 'border-muted-foreground/30 hover:border-violet-400/50 hover:bg-muted/50'
                    )}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 rounded-full bg-muted flex items-center justify-center">
                        <Upload className="h-7 w-7 text-muted-foreground" />
                      </div>
                      <div>
                        <p className="font-medium text-foreground">
                          {isMobile ? 'Select from gallery' : 'Drop image here or click to upload'}
                        </p>
                        <p className="text-sm text-muted-foreground mt-1">
                          Supports JPG, PNG, WebP up to 10MB
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </>
        )}

        {/* Image Preview & Analyze */}
        {mode === 'image' && imagePreview && analysisState === 'idle' && (
          <Card>
            <CardContent className="p-6">
              <div className="flex flex-col sm:flex-row gap-6">
                {/* Image Preview */}
                <div className="relative flex-shrink-0">
                  <img
                    src={imagePreview}
                    alt="Upload preview"
                    className="w-full sm:w-48 h-48 object-contain rounded-xl bg-muted"
                  />
                  <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    className="absolute top-2 right-2 h-8 w-8"
                    onClick={resetAll}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>

                {/* Analysis Options */}
                <form onSubmit={submitImageIdentification} className="flex-1 space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-2">
                      Additional context (optional)
                    </label>
                    <textarea
                      value={userPrompt}
                      onChange={(e) => setUserPrompt(e.target.value)}
                      placeholder="e.g., It's a wireless noise-cancelling headphone..."
                      rows={3}
                      className="w-full px-4 py-3 rounded-xl border border-input bg-background focus:border-primary focus:ring-1 focus:ring-primary resize-none"
                    />
                  </div>
                  <Button
                    type="submit"
                    className="w-full gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600"
                  >
                    <Zap className="h-4 w-4" />
                    Identify Product
                  </Button>
                </form>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Analyzing State */}
        {analysisState === 'analyzing' && (
          <Card>
            <CardContent className="p-8">
              <div className="flex flex-col items-center gap-6">
                {imagePreview ? (
                  <img
                    src={imagePreview}
                    alt="Analyzing"
                    className="w-48 h-48 object-contain rounded-xl bg-muted shadow-lg"
                  />
                ) : (
                  <div className="w-48 h-48 rounded-xl bg-muted flex items-center justify-center">
                    <Search className="h-12 w-12 text-muted-foreground" />
                  </div>
                )}

                <div className="text-center">
                  <div className="flex items-center justify-center gap-3 mb-2">
                    <Loader2 className="h-5 w-5 text-violet-500 animate-spin" />
                    <span className="text-lg font-medium text-foreground">
                      {imagePreview ? 'Analyzing image...' : 'Searching for products...'}
                    </span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    AI is identifying possible products
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Results State */}
        {analysisState === 'results' && suggestions.length > 0 && (
          <div className="space-y-6">
            {/* Context Card */}
            <div className="grid md:grid-cols-2 gap-6">
              <Card>
                <CardContent className="p-4">
                  {imagePreview ? (
                    <img
                      src={imagePreview}
                      alt="Uploaded"
                      className="w-full h-64 object-contain rounded-lg bg-muted"
                    />
                  ) : (
                    <div className="w-full h-64 rounded-lg bg-muted flex flex-col items-center justify-center p-6 text-center">
                      <Search className="h-12 w-12 text-muted-foreground mb-4" />
                      <p className="text-sm font-medium text-foreground">Searched for</p>
                      <p className="text-lg font-semibold text-primary break-words w-full">
                        "{searchQuery}"
                      </p>
                    </div>
                  )}
                  <Button
                    variant="secondary"
                    size="sm"
                    className="w-full mt-4"
                    onClick={resetAll}
                  >
                    New Search
                  </Button>
                </CardContent>
              </Card>

              {/* Results List */}
              <Card>
                <CardContent className="p-4">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="font-medium text-foreground">
                      {displayedSuggestions.length} Product{displayedSuggestions.length !== 1 ? 's' : ''} Found
                    </h3>
                    {providersUsed.length > 0 && (
                      <Badge variant="outline" className="text-xs gap-1">
                        <Sparkles className="h-3 w-3" />
                        {providersUsed.map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(', ')}
                      </Badge>
                    )}
                  </div>

                  <div className="space-y-2 max-h-[300px] overflow-y-auto">
                    {displayedSuggestions.map((product, index) => (
                      <button
                        key={index}
                        onClick={() => handleSelectProduct(index)}
                        className={cn(
                          'w-full text-left p-3 rounded-lg border-2 transition-colors',
                          selectedIndex === index
                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/10'
                            : 'border-border hover:border-violet-400/50'
                        )}
                      >
                        <div className="flex items-start gap-3">
                          {/* Product Image */}
                          <div className="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-muted relative">
                            {product.image_url ? (
                              <img
                                src={getProxiedImageUrl(product.image_url)}
                                alt={product.product_name}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                  const target = e.target as HTMLImageElement;
                                  target.style.display = 'none';
                                }}
                              />
                            ) : (
                              <div className="w-full h-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center">
                                <span className="text-white font-bold text-lg">
                                  {(product.brand || product.product_name || '?').substring(0, 2).toUpperCase()}
                                </span>
                              </div>
                            )}
                          </div>

                          {/* Radio indicator */}
                          <div
                            className={cn(
                              'w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 mt-0.5',
                              selectedIndex === index
                                ? 'border-violet-500 bg-violet-500'
                                : 'border-muted-foreground/50'
                            )}
                          >
                            {selectedIndex === index && (
                              <Check className="h-3 w-3 text-white" />
                            )}
                          </div>

                          {/* Product info */}
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-foreground line-clamp-1">
                              {product.product_name}
                            </p>
                            <div className="flex flex-wrap items-center gap-2 mt-1">
                              {product.is_generic ? (
                                <Badge variant="secondary" className="text-xs gap-0.5">
                                  <Scale className="h-2.5 w-2.5" />
                                  Generic
                                </Badge>
                              ) : (
                                <Badge variant="outline" className="text-xs gap-0.5">
                                  <Package className="h-2.5 w-2.5" />
                                  Specific
                                </Badge>
                              )}
                              <ConfidenceIndicator confidence={product.confidence} />
                            </div>
                            {product.brand && (
                              <p className="text-xs text-muted-foreground mt-1">
                                {product.brand}{product.model ? ` • ${product.model}` : ''}
                              </p>
                            )}
                          </div>

                          {/* Google search link */}
                          <a
                            href={buildGoogleSearchUrl(product)}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            className="text-muted-foreground hover:text-violet-500 transition-colors p-1"
                            title="Verify on Google"
                          >
                            <ExternalLink className="h-4 w-4" />
                          </a>
                        </div>
                      </button>
                    ))}
                  </div>

                  {/* Try Again button when nothing selected */}
                  {selectedIndex === null && (
                    <div className="mt-4 pt-4 border-t">
                      <p className="text-sm text-muted-foreground mb-2 text-center">
                        None of these correct?
                      </p>
                      <Button
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={resetAll}
                      >
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Try Different Search
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Add to List Form - shown when product is selected */}
            {selectedIndex !== null && (
              <Card>
                <CardContent className="p-6">
                  <h3 className="font-semibold text-foreground mb-4 flex items-center gap-2">
                    <ShoppingCart className="h-5 w-5" />
                    Add to Shopping List
                  </h3>

                  <form onSubmit={handleSubmitAdd} className="space-y-4">
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

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {/* Target Price */}
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
                              {UNITS_OF_MEASURE.map((unit) => (
                                <SelectItem key={unit.value} value={unit.value}>
                                  {unit.label}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      )}
                    </div>

                    {/* UPC if available */}
                    {addForm.data.upc && (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Barcode className="h-4 w-4" />
                        UPC: {addForm.data.upc}
                      </div>
                    )}

                    {/* Notes */}
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
                      <Button type="button" variant="outline" onClick={resetAll}>
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
                            <Plus className="h-4 w-4" />
                            Add to List
                          </>
                        )}
                      </Button>
                    </div>

                    <p className="text-xs text-muted-foreground text-center">
                      Price search will run automatically after adding.
                    </p>
                  </form>
                </CardContent>
              </Card>
            )}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
