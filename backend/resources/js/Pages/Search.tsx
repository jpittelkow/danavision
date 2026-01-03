import { FormEvent, useState, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { cn, isMobileDevice } from '@/lib/utils';
import { Camera, Upload, Search as SearchIcon, Loader2, X, AlertCircle } from 'lucide-react';

interface SearchResult {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
}

interface SearchHistoryItem {
  id: number;
  query: string;
  search_type: string;
  created_at: string;
}

interface Props extends PageProps {
  recent_searches: SearchHistoryItem[];
  query?: string;
  results?: SearchResult[];
  image_analysis?: {
    product_name: string;
    brand?: string;
    category?: string;
    confidence?: number;
    error?: string;
  };
  search_error?: string;
}

// Helper to safely format a number
const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

export default function Search({ auth, recent_searches, query, results, image_analysis, search_error, flash }: Props) {
  const [searchMode, setSearchMode] = useState<'text' | 'image'>('text');
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);
  
  const textForm = useForm({ query: query || '' });
  const imageForm = useForm({ 
    image: '',
    description: '',
  });

  const submitTextSearch = (e: FormEvent) => {
    e.preventDefault();
    if (!textForm.data.query.trim()) return;
    textForm.post('/search');
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
      imageForm.setData('image', base64);
      setShowConfirmation(true);
    };
    reader.readAsDataURL(file);
  };

  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      handleFileSelect(file);
    }
    // Reset input so same file can be selected again
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

  const handleConfirmSearch = () => {
    setShowConfirmation(false);
    imageForm.post('/search/image');
  };

  const handleCancelSearch = () => {
    setShowConfirmation(false);
    setImagePreview(null);
    imageForm.reset();
  };

  const isMobile = isMobileDevice();

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Search" />
      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        <h1 className="text-3xl font-bold text-foreground mb-2">Search Products</h1>
        <p className="text-muted-foreground mb-8">Find the best prices across retailers</p>

        {/* Error Display */}
        {(search_error || image_analysis?.error) && (
          <Card className="mb-6 border-destructive">
            <CardContent className="flex items-center gap-3 py-4">
              <AlertCircle className="h-5 w-5 text-destructive" />
              <p className="text-destructive">{search_error || image_analysis?.error}</p>
            </CardContent>
          </Card>
        )}

        {/* Search Mode Toggle */}
        <div className="flex gap-2 mb-6">
          <Button
            variant={searchMode === 'text' ? 'default' : 'outline'}
            onClick={() => setSearchMode('text')}
            className="gap-2"
          >
            <SearchIcon className="h-4 w-4" />
            Text Search
          </Button>
          <Button
            variant={searchMode === 'image' ? 'default' : 'outline'}
            onClick={() => setSearchMode('image')}
            className="gap-2"
          >
            <Camera className="h-4 w-4" />
            Image Search
          </Button>
        </div>

        {/* Text Search */}
        {searchMode === 'text' && (
          <form onSubmit={submitTextSearch} className="mb-8">
            <div className="flex gap-2">
              <div className="relative flex-1">
                <SearchIcon className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                <Input
                  type="text"
                  value={textForm.data.query}
                  onChange={(e) => textForm.setData('query', e.target.value)}
                  className="pl-12 h-14 text-lg"
                  placeholder="Search for products..."
                />
              </div>
              <Button
                type="submit"
                disabled={textForm.processing || !textForm.data.query.trim()}
                className="h-14 px-8"
              >
                {textForm.processing ? (
                  <Loader2 className="h-5 w-5 animate-spin" />
                ) : (
                  'Search'
                )}
              </Button>
            </div>
          </form>
        )}

        {/* Image Search */}
        {searchMode === 'image' && !showConfirmation && (
          <div className="mb-8 space-y-4">
            {/* Hidden file inputs */}
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
              {/* Camera button - shown on mobile */}
              {isMobile && (
                <button
                  type="button"
                  onClick={() => cameraInputRef.current?.click()}
                  className={cn(
                    'flex flex-col items-center justify-center gap-3 py-8 px-8 border-2 border-dashed rounded-xl transition-colors',
                    'border-primary/50 bg-primary/5 hover:border-primary hover:bg-primary/10'
                  )}
                >
                  <div className="w-16 h-16 rounded-full bg-primary/20 flex items-center justify-center">
                    <Camera className="h-8 w-8 text-primary" />
                  </div>
                  <span className="text-base font-medium text-primary">Take Photo</span>
                </button>
              )}

              {/* Upload area */}
              <div
                className={cn(
                  'flex-1 border-2 border-dashed rounded-xl p-8 sm:p-12 text-center transition-colors cursor-pointer',
                  isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/30 hover:border-muted-foreground/50 hover:bg-muted/50'
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
                  {imageForm.processing && (
                    <div className="flex items-center gap-2 text-primary">
                      <Loader2 className="h-5 w-5 animate-spin" />
                      <span>Analyzing image...</span>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Image Confirmation Step */}
        {showConfirmation && imagePreview && (
          <Card className="mb-8">
            <CardContent className="p-6">
              <div className="space-y-4">
                <div className="text-center">
                  <h3 className="text-lg font-medium text-foreground mb-2">
                    Confirm Search
                  </h3>
                  <p className="text-sm text-muted-foreground">
                    Add any additional context to help AI identify the product
                  </p>
                </div>

                <div className="flex justify-center">
                  <img
                    src={imagePreview}
                    alt="Upload preview"
                    className="w-64 h-64 object-contain rounded-lg bg-muted border"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-foreground mb-2">
                    Additional context (optional)
                  </label>
                  <textarea
                    value={imageForm.data.description}
                    onChange={(e) => imageForm.setData('description', e.target.value)}
                    placeholder="e.g., This is a wireless headphone, the brand logo is on the side..."
                    rows={3}
                    className="w-full px-4 py-3 rounded-lg border border-input bg-background focus:border-primary focus:ring-1 focus:ring-primary resize-none"
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    Provide any details that might help identify the product.
                  </p>
                </div>

                <div className="flex gap-3 pt-2">
                  <Button
                    variant="outline"
                    onClick={handleCancelSearch}
                    className="flex-1"
                  >
                    <X className="h-4 w-4 mr-2" />
                    Cancel
                  </Button>
                  <Button
                    onClick={handleConfirmSearch}
                    disabled={imageForm.processing}
                    className="flex-1"
                  >
                    {imageForm.processing ? (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    ) : (
                      <SearchIcon className="h-4 w-4 mr-2" />
                    )}
                    Search
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Image Analysis Result */}
        {image_analysis && image_analysis.product_name && !image_analysis.error && (
          <Card className="mb-6 border-primary/50 bg-primary/5">
            <CardContent className="py-4">
              <p className="text-sm text-muted-foreground mb-1">AI identified:</p>
              <p className="font-semibold text-foreground text-lg">{image_analysis.product_name}</p>
              <div className="flex flex-wrap gap-4 mt-2 text-sm text-muted-foreground">
                {image_analysis.brand && (
                  <span>Brand: <span className="text-foreground">{image_analysis.brand}</span></span>
                )}
                {image_analysis.category && (
                  <span>Category: <span className="text-foreground">{image_analysis.category}</span></span>
                )}
                {image_analysis.confidence && (
                  <span>Confidence: <span className="text-foreground">{Math.round(image_analysis.confidence * 100)}%</span></span>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Search Results */}
        {results && results.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-semibold text-foreground mb-4">
              Results for "{query}"
            </h2>
            <div className="space-y-4">
              {results.map((result, index) => (
                <a
                  key={index}
                  href={result.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="block bg-card border border-border rounded-xl p-4 shadow-sm hover:shadow-md hover:border-primary/50 transition-all"
                >
                  <div className="flex gap-4">
                    {result.image_url && (
                      <img
                        src={result.image_url}
                        alt={result.title}
                        className="w-20 h-20 object-contain rounded-lg bg-muted"
                      />
                    )}
                    <div className="flex-1 min-w-0">
                      <h3 className="font-medium text-foreground line-clamp-2">{result.title}</h3>
                      <div className="flex items-center gap-4 mt-2">
                        <span className="text-xl font-bold text-primary">
                          ${formatPrice(result.price)}
                        </span>
                        <span className="text-sm text-muted-foreground bg-muted px-2 py-1 rounded">
                          {result.retailer}
                        </span>
                      </div>
                    </div>
                  </div>
                </a>
              ))}
            </div>
          </div>
        )}

        {results && results.length === 0 && query && !search_error && (
          <Card className="mb-8">
            <CardContent className="py-12 text-center">
              <SearchIcon className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
              <p className="text-muted-foreground">No results found for "{query}"</p>
              <p className="text-sm text-muted-foreground mt-2">
                Try different keywords or check your price API configuration in Settings.
              </p>
            </CardContent>
          </Card>
        )}

        {/* Recent Searches */}
        {recent_searches && recent_searches.length > 0 && (
          <div>
            <h2 className="text-lg font-semibold text-foreground mb-4">Recent Searches</h2>
            <div className="flex flex-wrap gap-2">
              {recent_searches.map((search) => (
                <button
                  key={search.id}
                  onClick={() => {
                    textForm.setData('query', search.query);
                    setSearchMode('text');
                    textForm.post('/search');
                  }}
                  className="bg-card border border-border px-4 py-2 rounded-full text-sm text-foreground hover:bg-muted hover:border-primary/50 transition-colors"
                >
                  {search.search_type === 'image' && <Camera className="h-3 w-3 inline mr-1" />}
                  {search.query}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
