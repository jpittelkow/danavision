import { FormEvent, useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { PageProps, ShoppingList } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
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
  CheckCircle2,
  ExternalLink,
  Plus,
  ImageIcon,
  Zap,
  ShoppingCart,
} from 'lucide-react';

interface PriceResult {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
}

interface Analysis {
  product_name: string | null;
  brand: string | null;
  model: string | null;
  category: string | null;
  search_terms: string[];
  confidence: number;
  error: string | null;
  providers_used: string[];
}

interface Props extends PageProps {
  lists: Pick<ShoppingList, 'id' | 'name'>[];
  analysis?: Analysis;
  price_results?: PriceResult[];
  search_error?: string;
  uploaded_image?: string;
  search_query?: string;
}

const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

export default function SmartAdd({
  auth,
  lists,
  analysis,
  price_results,
  search_error,
  uploaded_image,
  search_query,
  flash,
}: Props) {
  const [mode, setMode] = useState<'idle' | 'image' | 'text'>('idle');
  const [imagePreview, setImagePreview] = useState<string | null>(uploaded_image || null);
  const [isDragging, setIsDragging] = useState(false);
  const [selectedResult, setSelectedResult] = useState<PriceResult | null>(null);
  const [selectedListId, setSelectedListId] = useState<string>(
    lists.length > 0 ? String(lists[0].id) : ''
  );

  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const analyzeForm = useForm({
    image: '',
    description: '',
  });

  const searchForm = useForm({
    query: search_query || '',
  });

  const addForm = useForm({
    list_id: selectedListId,
    product_name: '',
    product_query: '',
    product_url: '',
    product_image_url: '',
    sku: '',
    current_price: '',
    current_retailer: '',
    target_price: '',
    notes: '',
    priority: 'medium',
  });

  const isMobile = isMobileDevice();
  const hasAnalysis = analysis && analysis.product_name;
  const hasResults = price_results && price_results.length > 0;

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
      analyzeForm.setData('image', base64);
      setMode('image');
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

  const submitImageAnalysis = (e: FormEvent) => {
    e.preventDefault();
    analyzeForm.post('/smart-add/analyze');
  };

  const submitTextSearch = (e: FormEvent) => {
    e.preventDefault();
    if (!searchForm.data.query.trim()) return;
    searchForm.post('/smart-add/search');
  };

  const handleClearImage = () => {
    setImagePreview(null);
    analyzeForm.reset();
    setMode('idle');
  };

  const handleSelectResult = (result: PriceResult) => {
    setSelectedResult(result);
    addForm.setData({
      list_id: selectedListId,
      product_name: analysis?.product_name || result.title,
      product_query: analysis?.product_name || result.title,
      product_url: result.url,
      product_image_url: result.image_url || '',
      sku: '',
      current_price: String(result.price),
      current_retailer: result.retailer,
      target_price: '',
      notes: '',
      priority: 'medium',
    });
  };

  const handleAddToList = (e: FormEvent) => {
    e.preventDefault();
    addForm.setData('list_id', selectedListId);
    addForm.post('/smart-add/add');
  };

  const resetAll = () => {
    setImagePreview(null);
    setSelectedResult(null);
    setMode('idle');
    analyzeForm.reset();
    searchForm.reset();
    addForm.reset();
    router.get('/smart-add');
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Smart Add" />
      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8 text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 mb-4">
            <Sparkles className="h-8 w-8 text-white" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Smart Add</h1>
          <p className="text-muted-foreground max-w-md mx-auto">
            Upload an image or search for a product. AI will identify it and find the best prices.
          </p>
        </div>

        {/* Error Display */}
        {(search_error || analysis?.error) && (
          <Card className="mb-6 border-destructive">
            <CardContent className="flex items-center gap-3 py-4">
              <AlertCircle className="h-5 w-5 text-destructive flex-shrink-0" />
              <p className="text-destructive">{search_error || analysis?.error}</p>
            </CardContent>
          </Card>
        )}

        {/* Input Section - Show when no results yet */}
        {!hasAnalysis && !hasResults && (
          <>
            {/* Mode Toggle */}
            <div className="flex justify-center gap-2 mb-6">
              <Button
                variant={mode !== 'text' ? 'default' : 'outline'}
                onClick={() => setMode('idle')}
                className="gap-2"
              >
                <Camera className="h-4 w-4" />
                Image
              </Button>
              <Button
                variant={mode === 'text' ? 'default' : 'outline'}
                onClick={() => setMode('text')}
                className="gap-2"
              >
                <Search className="h-4 w-4" />
                Text Search
              </Button>
            </div>

            {/* Image Upload Mode */}
            {mode !== 'text' && (
              <div className="mb-8">
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

                {!imagePreview ? (
                  <div className="flex flex-col sm:flex-row gap-4">
                    {/* Camera button - mobile only */}
                    {isMobile && (
                      <button
                        type="button"
                        onClick={() => cameraInputRef.current?.click()}
                        className={cn(
                          'flex flex-col items-center justify-center gap-3 py-8 px-8 border-2 border-dashed rounded-2xl transition-all',
                          'border-violet-400/50 bg-violet-500/5 hover:border-violet-400 hover:bg-violet-500/10',
                          'hover:scale-[1.02] active:scale-[0.98]'
                        )}
                      >
                        <div className="w-16 h-16 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center">
                          <Camera className="h-8 w-8 text-white" />
                        </div>
                        <span className="text-base font-medium text-violet-600 dark:text-violet-400">
                          Take Photo
                        </span>
                      </button>
                    )}

                    {/* Upload area */}
                    <div
                      className={cn(
                        'flex-1 border-2 border-dashed rounded-2xl p-8 sm:p-12 text-center transition-all cursor-pointer',
                        'hover:scale-[1.01] active:scale-[0.99]',
                        isDragging
                          ? 'border-violet-500 bg-violet-500/10'
                          : 'border-muted-foreground/30 hover:border-violet-400/50 hover:bg-muted/50'
                      )}
                      onDragOver={handleDragOver}
                      onDragLeave={handleDragLeave}
                      onDrop={handleDrop}
                      onClick={() => fileInputRef.current?.click()}
                    >
                      <div className="flex flex-col items-center gap-4">
                        <div className="w-16 h-16 rounded-full bg-muted flex items-center justify-center">
                          <Upload className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <div>
                          <p className="text-lg font-medium text-foreground">
                            {isMobile ? 'Select from gallery' : 'Drop image here or click to upload'}
                          </p>
                          <p className="text-sm text-muted-foreground mt-1">
                            Supports JPG, PNG, WebP up to 10MB
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <form onSubmit={submitImageAnalysis}>
                    <Card className="overflow-hidden">
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
                              onClick={handleClearImage}
                            >
                              <X className="h-4 w-4" />
                            </Button>
                          </div>

                          {/* Analysis Options */}
                          <div className="flex-1 space-y-4">
                            <div>
                              <label className="block text-sm font-medium text-foreground mb-2">
                                Additional context (optional)
                              </label>
                              <textarea
                                value={analyzeForm.data.description}
                                onChange={(e) => analyzeForm.setData('description', e.target.value)}
                                placeholder="e.g., It's a wireless noise-cancelling headphone..."
                                rows={3}
                                className="w-full px-4 py-3 rounded-xl border border-input bg-background focus:border-primary focus:ring-1 focus:ring-primary resize-none"
                              />
                            </div>
                            <Button
                              type="submit"
                              disabled={analyzeForm.processing}
                              className="w-full gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600"
                            >
                              {analyzeForm.processing ? (
                                <>
                                  <Loader2 className="h-4 w-4 animate-spin" />
                                  Analyzing with AI...
                                </>
                              ) : (
                                <>
                                  <Zap className="h-4 w-4" />
                                  Identify Product
                                </>
                              )}
                            </Button>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  </form>
                )}
              </div>
            )}

            {/* Text Search Mode */}
            {mode === 'text' && (
              <form onSubmit={submitTextSearch} className="mb-8">
                <div className="flex gap-2">
                  <div className="relative flex-1">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                    <Input
                      type="text"
                      value={searchForm.data.query}
                      onChange={(e) => searchForm.setData('query', e.target.value)}
                      className="pl-12 h-14 text-lg rounded-xl"
                      placeholder="Search for a product..."
                    />
                  </div>
                  <Button
                    type="submit"
                    disabled={searchForm.processing || !searchForm.data.query.trim()}
                    className="h-14 px-8 rounded-xl bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600"
                  >
                    {searchForm.processing ? (
                      <Loader2 className="h-5 w-5 animate-spin" />
                    ) : (
                      'Search'
                    )}
                  </Button>
                </div>
              </form>
            )}
          </>
        )}

        {/* Results Section */}
        {(hasAnalysis || hasResults) && (
          <div className="space-y-6">
            {/* AI Analysis Result */}
            {hasAnalysis && (
              <Card className="border-violet-400/50 bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5">
                <CardContent className="p-6">
                  <div className="flex items-start gap-4">
                    {uploaded_image && (
                      <img
                        src={uploaded_image}
                        alt="Analyzed product"
                        className="w-24 h-24 object-contain rounded-xl bg-muted flex-shrink-0"
                      />
                    )}
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-2">
                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                        <span className="text-sm font-medium text-green-600 dark:text-green-400">
                          Product Identified
                        </span>
                        {analysis.confidence > 0 && (
                          <Badge variant="secondary" className="ml-2">
                            {analysis.confidence}% confident
                          </Badge>
                        )}
                      </div>
                      <h2 className="text-xl font-bold text-foreground mb-2">
                        {analysis.product_name}
                      </h2>
                      <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                        {analysis.brand && (
                          <span>
                            Brand: <span className="text-foreground">{analysis.brand}</span>
                          </span>
                        )}
                        {analysis.model && (
                          <span>
                            Model: <span className="text-foreground">{analysis.model}</span>
                          </span>
                        )}
                        {analysis.category && (
                          <span>
                            Category: <span className="text-foreground">{analysis.category}</span>
                          </span>
                        )}
                      </div>
                      {analysis.providers_used && analysis.providers_used.length > 0 && (
                        <div className="flex items-center gap-2 mt-3 text-xs text-muted-foreground">
                          <Sparkles className="h-3 w-3" />
                          <span>
                            Analyzed by: {analysis.providers_used.join(', ')}
                          </span>
                        </div>
                      )}
                    </div>
                    <Button variant="ghost" size="sm" onClick={resetAll}>
                      <X className="h-4 w-4 mr-1" />
                      New Search
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Price Results */}
            {hasResults && (
              <>
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-semibold text-foreground">
                    {price_results.length} Price{price_results.length !== 1 ? 's' : ''} Found
                  </h3>
                </div>

                <div className="grid gap-4">
                  {price_results.map((result, index) => (
                    <Card
                      key={index}
                      className={cn(
                        'cursor-pointer transition-all hover:shadow-md',
                        selectedResult === result
                          ? 'border-violet-500 bg-violet-500/5 ring-2 ring-violet-500/20'
                          : 'hover:border-violet-400/50'
                      )}
                      onClick={() => handleSelectResult(result)}
                    >
                      <CardContent className="p-4">
                        <div className="flex gap-4">
                          {result.image_url ? (
                            <img
                              src={result.image_url}
                              alt={result.title}
                              className="w-20 h-20 object-contain rounded-lg bg-muted flex-shrink-0"
                            />
                          ) : (
                            <div className="w-20 h-20 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
                              <ImageIcon className="h-8 w-8 text-muted-foreground" />
                            </div>
                          )}
                          <div className="flex-1 min-w-0">
                            <h4 className="font-medium text-foreground line-clamp-2">
                              {result.title}
                            </h4>
                            <div className="flex items-center gap-3 mt-2">
                              <span className="text-xl font-bold text-primary">
                                ${formatPrice(result.price)}
                              </span>
                              <Badge variant="secondary">{result.retailer}</Badge>
                            </div>
                            <a
                              href={result.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              onClick={(e) => e.stopPropagation()}
                              className="inline-flex items-center gap-1 text-sm text-primary hover:underline mt-2"
                            >
                              View on {result.retailer}
                              <ExternalLink className="h-3 w-3" />
                            </a>
                          </div>
                          {selectedResult === result && (
                            <div className="flex items-center">
                              <CheckCircle2 className="h-6 w-6 text-violet-500" />
                            </div>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </>
            )}

            {/* No Results */}
            {hasAnalysis && !hasResults && !search_error && (
              <Card>
                <CardContent className="py-12 text-center">
                  <ShoppingCart className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                  <p className="text-muted-foreground">
                    No price results found for "{analysis.product_name}"
                  </p>
                  <p className="text-sm text-muted-foreground mt-2">
                    Try a different search or check your price API configuration.
                  </p>
                </CardContent>
              </Card>
            )}

            {/* Add to List Form */}
            {(selectedResult || (hasAnalysis && !hasResults)) && lists.length > 0 && (
              <Card className="border-green-400/50 bg-green-500/5">
                <CardContent className="p-6">
                  <h3 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                    <Plus className="h-5 w-5" />
                    Add to Shopping List
                  </h3>
                  <form onSubmit={handleAddToList} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-foreground mb-1">
                          Shopping List
                        </label>
                        <Select
                          value={selectedListId}
                          onValueChange={setSelectedListId}
                        >
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
                          Product Name
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
                    <Button
                      type="submit"
                      disabled={addForm.processing || !addForm.data.product_name || !selectedListId}
                      className="w-full gap-2 bg-green-600 hover:bg-green-700"
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
                  </form>
                </CardContent>
              </Card>
            )}

            {/* No Lists Warning */}
            {lists.length === 0 && (
              <Card className="border-amber-400/50 bg-amber-500/5">
                <CardContent className="py-6 text-center">
                  <AlertCircle className="h-8 w-8 mx-auto text-amber-500 mb-3" />
                  <p className="text-foreground font-medium">No shopping lists found</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Create a shopping list first to add items.
                  </p>
                  <Button
                    variant="outline"
                    className="mt-4"
                    onClick={() => router.get('/lists/create')}
                  >
                    Create List
                  </Button>
                </CardContent>
              </Card>
            )}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
