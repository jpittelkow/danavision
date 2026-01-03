import { FormEvent, useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
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
import StreamingSearchResults from '@/Components/StreamingSearchResults';
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
  RefreshCw,
  Edit3,
  Tag,
  Radio,
  Scale,
  Package,
} from 'lucide-react';
import { UNITS_OF_MEASURE, UnitOfMeasure } from '@/types';

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
  is_generic: boolean;
  unit_of_measure: string | null;
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

// Confidence indicator component
function ConfidenceIndicator({ confidence }: { confidence: number }) {
  const getColor = () => {
    if (confidence >= 80) return 'bg-green-500';
    if (confidence >= 60) return 'bg-yellow-500';
    return 'bg-orange-500';
  };

  const getLabel = () => {
    if (confidence >= 80) return 'High confidence';
    if (confidence >= 60) return 'Medium confidence';
    return 'Low confidence';
  };

  return (
    <div className="flex items-center gap-3">
      <div className="flex-1 h-2 bg-muted rounded-full overflow-hidden">
        <div
          className={cn('h-full transition-all duration-500', getColor())}
          style={{ width: `${confidence}%` }}
        />
      </div>
      <span className="text-xs text-muted-foreground whitespace-nowrap">
        {confidence}% - {getLabel()}
      </span>
    </div>
  );
}

// Step indicator component
function StepIndicator({ 
  currentStep 
}: { 
  currentStep: 'idle' | 'analyzing' | 'searching' | 'complete' 
}) {
  const steps = [
    { id: 'analyzing', label: 'Analyzing', icon: Sparkles },
    { id: 'searching', label: 'Searching', icon: Search },
    { id: 'complete', label: 'Complete', icon: CheckCircle2 },
  ];

  const getCurrentIndex = () => {
    if (currentStep === 'idle') return -1;
    return steps.findIndex(s => s.id === currentStep);
  };

  const currentIndex = getCurrentIndex();

  return (
    <div className="flex items-center justify-center gap-2 mb-6">
      {steps.map((step, index) => {
        const Icon = step.icon;
        const isActive = index === currentIndex;
        const isComplete = index < currentIndex;
        const isPending = index > currentIndex;

        return (
          <div key={step.id} className="flex items-center gap-2">
            <div
              className={cn(
                'flex items-center gap-2 px-3 py-1.5 rounded-full text-sm transition-all',
                isActive && 'bg-violet-500 text-white',
                isComplete && 'bg-green-500/20 text-green-600 dark:text-green-400',
                isPending && 'bg-muted text-muted-foreground'
              )}
            >
              {isActive && currentStep !== 'complete' ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Icon className="h-4 w-4" />
              )}
              <span className="font-medium">{step.label}</span>
            </div>
            {index < steps.length - 1 && (
              <div
                className={cn(
                  'w-8 h-0.5',
                  index < currentIndex ? 'bg-green-500' : 'bg-muted'
                )}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}

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
  const [isEditingQuery, setIsEditingQuery] = useState(false);
  const [customSearchQuery, setCustomSearchQuery] = useState('');
  
  // Streaming search state
  const [useStreaming, setUseStreaming] = useState(true);
  const [isStreaming, setIsStreaming] = useState(false);
  const [streamingQuery, setStreamingQuery] = useState('');
  const [streamedResults, setStreamedResults] = useState<PriceResult[]>([]);

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
    uploaded_image: '',
    sku: '',
    current_price: '',
    current_retailer: '',
    target_price: '',
    notes: '',
    priority: 'medium',
    is_generic: false,
    unit_of_measure: '' as string,
  });

  const isMobile = isMobileDevice();
  const hasAnalysis = analysis && analysis.product_name;
  const hasResults = (price_results && price_results.length > 0) || streamedResults.length > 0;
  const displayResults = streamedResults.length > 0 ? streamedResults : (price_results || []);

  // Determine current step for indicator
  const getCurrentStep = (): 'idle' | 'analyzing' | 'searching' | 'complete' => {
    if (analyzeForm.processing) return 'analyzing';
    if (searchForm.processing || isStreaming) return 'searching';
    if (hasAnalysis || hasResults) return 'complete';
    return 'idle';
  };

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
    
    if (useStreaming) {
      // Use streaming mode
      setStreamingQuery(searchForm.data.query);
      setStreamedResults([]);
      setIsStreaming(true);
    } else {
      // Use traditional mode
      searchForm.post('/smart-add/search');
    }
  };

  // Handle streaming search completion
  const handleStreamingComplete = (results: PriceResult[]) => {
    setStreamedResults(results);
    setIsStreaming(false);
  };

  // Handle streaming search cancel
  const handleStreamingCancel = () => {
    setIsStreaming(false);
    setStreamingQuery('');
  };

  // Re-search with custom query
  const handleCustomSearch = (query: string) => {
    setIsEditingQuery(false);
    
    if (useStreaming) {
      setStreamingQuery(query);
      setStreamedResults([]);
      setIsStreaming(true);
    } else {
      searchForm.setData('query', query);
      searchForm.post('/smart-add/search');
    }
  };

  // Search using a specific search term chip
  const handleSearchTermClick = (term: string) => {
    if (useStreaming) {
      setStreamingQuery(term);
      setStreamedResults([]);
      setIsStreaming(true);
    } else {
      searchForm.setData('query', term);
      searchForm.post('/smart-add/search');
    }
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
      uploaded_image: uploaded_image || '',
      sku: '',
      current_price: String(result.price),
      current_retailer: result.retailer,
      target_price: '',
      notes: '',
      priority: 'medium',
      is_generic: analysis?.is_generic || false,
      unit_of_measure: analysis?.unit_of_measure || '',
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
    setIsEditingQuery(false);
    setCustomSearchQuery('');
    setIsStreaming(false);
    setStreamingQuery('');
    setStreamedResults([]);
    analyzeForm.reset();
    searchForm.reset();
    addForm.reset();
    router.get('/smart-add');
  };

  // Get proxy URL for external images
  const getProxiedImageUrl = (url: string | undefined) => {
    if (!url) return undefined;
    return `/api/proxy-image?url=${encodeURIComponent(url)}`;
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

        {/* Step Indicator - Show during processing */}
        {(analyzeForm.processing || searchForm.processing || isStreaming || hasAnalysis || hasResults) && (
          <StepIndicator currentStep={getCurrentStep()} />
        )}

        {/* Error Display */}
        {(search_error || analysis?.error) && (
          <Card className="mb-6 border-destructive">
            <CardContent className="flex items-center gap-3 py-4">
              <AlertCircle className="h-5 w-5 text-destructive flex-shrink-0" />
              <p className="text-destructive">{search_error || analysis?.error}</p>
            </CardContent>
          </Card>
        )}

        {/* Input Section - Show when no results yet and not streaming */}
        {!hasAnalysis && !hasResults && !isStreaming && (
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
              <div className="mb-8 space-y-4">
                <form onSubmit={submitTextSearch}>
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
                      disabled={searchForm.processing || isStreaming || !searchForm.data.query.trim()}
                      className="h-14 px-8 rounded-xl bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600"
                    >
                      {(searchForm.processing || isStreaming) ? (
                        <Loader2 className="h-5 w-5 animate-spin" />
                      ) : (
                        'Search'
                      )}
                    </Button>
                  </div>
                </form>
                
                {/* Streaming Toggle */}
                <div className="flex items-center justify-end gap-2">
                  <Switch
                    checked={useStreaming}
                    onCheckedChange={setUseStreaming}
                    className="data-[state=checked]:bg-violet-500"
                  />
                  <span className="text-sm text-muted-foreground flex items-center gap-1">
                    <Radio className="h-3.5 w-3.5" />
                    Real-time results
                  </span>
                </div>
              </div>
            )}
          </>
        )}

        {/* Streaming Search Results - Show immediately when streaming starts */}
        {(isStreaming || (streamingQuery && streamedResults.length > 0)) && (
          <div className="space-y-6">
            <StreamingSearchResults
              query={streamingQuery}
              onComplete={handleStreamingComplete}
              onSelectResult={handleSelectResult}
              selectedResult={selectedResult}
              isActive={isStreaming}
              onCancel={handleStreamingCancel}
            />
          </div>
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
                      </div>

                      {/* Product Name with Edit */}
                      <h2 className="text-xl font-bold text-foreground mb-2">
                        {analysis.product_name}
                      </h2>

                      {/* Item Type Badge */}
                      <div className="flex items-center gap-2 mb-2">
                        {analysis.is_generic ? (
                          <Badge variant="secondary" className="gap-1">
                            <Scale className="h-3 w-3" />
                            Generic Item
                            {analysis.unit_of_measure && (
                              <span className="text-xs opacity-75">
                                (per {analysis.unit_of_measure})
                              </span>
                            )}
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="gap-1">
                            <Package className="h-3 w-3" />
                            Specific Item
                          </Badge>
                        )}
                      </div>

                      {/* Confidence Indicator */}
                      {analysis.confidence > 0 && (
                        <div className="mb-3 max-w-xs">
                          <ConfidenceIndicator confidence={analysis.confidence} />
                        </div>
                      )}

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

                      {/* Alternative Search Terms */}
                      {analysis.search_terms && analysis.search_terms.length > 0 && (
                        <div className="mt-4">
                          <div className="flex items-center gap-2 mb-2">
                            <Tag className="h-4 w-4 text-muted-foreground" />
                            <span className="text-xs text-muted-foreground">
                              Try alternative searches:
                            </span>
                          </div>
                          <div className="flex flex-wrap gap-2">
                            {analysis.search_terms.slice(0, 5).map((term, index) => (
                              <button
                                key={index}
                                type="button"
                                onClick={() => handleSearchTermClick(term)}
                                disabled={searchForm.processing}
                                className={cn(
                                  'px-3 py-1 text-sm rounded-full border transition-colors',
                                  'bg-background hover:bg-violet-500/10 hover:border-violet-400',
                                  'text-foreground hover:text-violet-600 dark:hover:text-violet-400',
                                  searchForm.processing && 'opacity-50 cursor-not-allowed'
                                )}
                              >
                                {term}
                              </button>
                            ))}
                          </div>
                        </div>
                      )}

                      {analysis.providers_used && analysis.providers_used.length > 0 && (
                        <div className="flex items-center gap-2 mt-3 text-xs text-muted-foreground">
                          <Sparkles className="h-3 w-3" />
                          <span>
                            Analyzed by: {analysis.providers_used.join(', ')}
                          </span>
                        </div>
                      )}
                    </div>
                    <div className="flex flex-col gap-2">
                      <Button variant="ghost" size="sm" onClick={resetAll}>
                        <X className="h-4 w-4 mr-1" />
                        New
                      </Button>
                    </div>
                  </div>

                  {/* Editable Search Query */}
                  <div className="mt-4 pt-4 border-t border-border">
                    {isEditingQuery ? (
                      <div className="flex gap-2">
                        <Input
                          type="text"
                          value={customSearchQuery}
                          onChange={(e) => setCustomSearchQuery(e.target.value)}
                          placeholder="Enter custom search query..."
                          className="flex-1"
                          autoFocus
                        />
                        <Button
                          size="sm"
                          onClick={() => handleCustomSearch(customSearchQuery)}
                          disabled={!customSearchQuery.trim() || searchForm.processing}
                        >
                          {searchForm.processing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Search className="h-4 w-4" />
                          )}
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setIsEditingQuery(false);
                            setCustomSearchQuery('');
                          }}
                        >
                          Cancel
                        </Button>
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                          Searched for: <span className="text-foreground font-medium">{search_query || analysis.product_name}</span>
                        </span>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            setCustomSearchQuery(search_query || analysis.product_name || '');
                            setIsEditingQuery(true);
                          }}
                          className="gap-1"
                        >
                          <Edit3 className="h-3 w-3" />
                          Edit Search
                        </Button>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Static Price Results (non-streaming) */}
            {hasResults && !isStreaming && streamedResults.length === 0 && (
              <>
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-semibold text-foreground">
                    {displayResults.length} Price{displayResults.length !== 1 ? 's' : ''} Found
                  </h3>
                  {hasAnalysis && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setCustomSearchQuery(analysis?.product_name || '');
                        setIsEditingQuery(true);
                      }}
                      className="gap-1"
                    >
                      <RefreshCw className="h-3 w-3" />
                      Re-search
                    </Button>
                  )}
                </div>

                <div className="grid gap-4">
                  {displayResults.map((result, index) => (
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
                              src={getProxiedImageUrl(result.image_url)}
                              alt={result.title}
                              className="w-20 h-20 object-contain rounded-lg bg-muted flex-shrink-0"
                              onError={(e) => {
                                // Fallback to original URL if proxy fails
                                const target = e.target as HTMLImageElement;
                                if (!target.src.includes(result.image_url!)) {
                                  target.src = result.image_url!;
                                }
                              }}
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
                  <p className="text-sm text-muted-foreground mt-2 mb-4">
                    Try a different search term or check your price API configuration.
                  </p>
                  {analysis.search_terms && analysis.search_terms.length > 0 && (
                    <div className="flex flex-wrap justify-center gap-2">
                      {analysis.search_terms.slice(0, 3).map((term, index) => (
                        <Button
                          key={index}
                          variant="outline"
                          size="sm"
                          onClick={() => handleSearchTermClick(term)}
                          disabled={searchForm.processing}
                        >
                          Try "{term}"
                        </Button>
                      ))}
                    </div>
                  )}
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
