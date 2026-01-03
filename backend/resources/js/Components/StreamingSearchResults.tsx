import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import {
  Loader2,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  ImageIcon,
  Search,
  Zap,
} from 'lucide-react';

interface PriceResult {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
  in_stock?: boolean;
}

interface SearchStatus {
  api: string;
  status: string;
}

interface StreamingSearchResultsProps {
  query: string;
  onComplete: (results: PriceResult[]) => void;
  onSelectResult: (result: PriceResult) => void;
  selectedResult: PriceResult | null;
  isActive: boolean;
  onCancel: () => void;
}

const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

// Get proxy URL for external images
const getProxiedImageUrl = (url: string | undefined) => {
  if (!url) return undefined;
  return `/api/proxy-image?url=${encodeURIComponent(url)}`;
};

export function StreamingSearchResults({
  query,
  onComplete,
  onSelectResult,
  selectedResult,
  isActive,
  onCancel,
}: StreamingSearchResultsProps) {
  const [status, setStatus] = useState<SearchStatus | null>(null);
  const [results, setResults] = useState<PriceResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [totalExpected, setTotalExpected] = useState<number | null>(null);

  const startSearch = useCallback(() => {
    if (!query || !isActive) return;

    // Reset state
    setResults([]);
    setStatus(null);
    setError(null);
    setIsSearching(true);
    setIsComplete(false);
    setTotalExpected(null);

    const eventSource = new EventSource(
      `/smart-add/stream-search?query=${encodeURIComponent(query)}`
    );

    eventSource.addEventListener('searching', (event) => {
      const data = JSON.parse(event.data);
      setStatus(data);
      
      // Extract total from status message if available
      const match = data.status.match(/Found (\d+) results/);
      if (match) {
        setTotalExpected(parseInt(match[1], 10));
      }
    });

    eventSource.addEventListener('result', (event) => {
      const data = JSON.parse(event.data);
      setResults((prev) => [...prev, {
        title: data.title,
        price: data.price,
        url: data.url,
        image_url: data.image_url,
        retailer: data.retailer,
        in_stock: data.in_stock,
      }]);
      
      if (data.total) {
        setTotalExpected(data.total);
      }
    });

    eventSource.addEventListener('complete', (event) => {
      const data = JSON.parse(event.data);
      setIsSearching(false);
      setIsComplete(true);
      eventSource.close();
      
      // Notify parent with final results
      // We need to get the current results, but since this is in a callback,
      // we use the functional update pattern
      setResults((currentResults) => {
        onComplete(currentResults);
        return currentResults;
      });
    });

    eventSource.addEventListener('error', (event) => {
      try {
        const data = JSON.parse((event as MessageEvent).data);
        setError(data.message);
      } catch {
        setError('Connection lost. Please try again.');
      }
      setIsSearching(false);
      eventSource.close();
    });

    eventSource.onerror = () => {
      if (!isComplete) {
        setError('Connection lost. Please try again.');
        setIsSearching(false);
      }
      eventSource.close();
    };

    return () => {
      eventSource.close();
    };
  }, [query, isActive, onComplete]);

  useEffect(() => {
    if (isActive && query) {
      const cleanup = startSearch();
      return cleanup;
    }
  }, [isActive, query, startSearch]);

  // Only return null if not active AND no results to display
  // This allows completed searches to still show their results
  if (!isActive && results.length === 0 && !isComplete) {
    return null;
  }

  return (
    <div className="space-y-4">
      {/* Search Status Panel */}
      <Card className="border-violet-400/50 bg-gradient-to-r from-violet-500/5 to-fuchsia-500/5">
        <CardContent className="p-4">
          <div className="flex items-center gap-4">
            {/* Status Icon */}
            <div className={cn(
              'w-12 h-12 rounded-full flex items-center justify-center',
              isSearching && 'bg-violet-500/20',
              isComplete && !error && 'bg-green-500/20',
              error && 'bg-red-500/20'
            )}>
              {isSearching && (
                <Loader2 className="h-6 w-6 text-violet-500 animate-spin" />
              )}
              {isComplete && !error && (
                <CheckCircle2 className="h-6 w-6 text-green-500" />
              )}
              {error && (
                <AlertCircle className="h-6 w-6 text-red-500" />
              )}
            </div>

            {/* Status Text */}
            <div className="flex-1">
              <div className="flex items-center gap-2">
                {status && (
                  <Badge variant="outline" className="gap-1">
                    <Zap className="h-3 w-3" />
                    {status.api}
                  </Badge>
                )}
                {isSearching && (
                  <span className="text-sm text-muted-foreground animate-pulse">
                    {status?.status || 'Searching...'}
                  </span>
                )}
                {isComplete && !error && (
                  <span className="text-sm text-green-600 dark:text-green-400">
                    Search complete
                  </span>
                )}
                {error && (
                  <span className="text-sm text-red-500">{error}</span>
                )}
              </div>

              {/* Progress bar */}
              {isSearching && totalExpected && (
                <div className="mt-2 h-1 bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-violet-500 transition-all duration-300"
                    style={{ 
                      width: `${Math.min((results.length / totalExpected) * 100, 100)}%` 
                    }}
                  />
                </div>
              )}

              {/* Results count */}
              {results.length > 0 && (
                <p className="text-xs text-muted-foreground mt-1">
                  {results.length} result{results.length !== 1 ? 's' : ''} found
                  {totalExpected && results.length < totalExpected && ` of ${totalExpected}`}
                </p>
              )}
            </div>

            {/* Cancel button */}
            {isSearching && (
              <Button variant="ghost" size="sm" onClick={onCancel}>
                Cancel
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Streaming Results */}
      <div className="grid gap-4">
        {results.map((result, index) => (
          <Card
            key={`${result.url}-${index}`}
            className={cn(
              'cursor-pointer transition-all hover:shadow-md animate-in fade-in slide-in-from-bottom-2 duration-300',
              selectedResult?.url === result.url
                ? 'border-violet-500 bg-violet-500/5 ring-2 ring-violet-500/20'
                : 'hover:border-violet-400/50'
            )}
            style={{ animationDelay: `${index * 50}ms` }}
            onClick={() => onSelectResult(result)}
          >
            <CardContent className="p-4">
              <div className="flex gap-4">
                {result.image_url ? (
                  <img
                    src={getProxiedImageUrl(result.image_url)}
                    alt={result.title}
                    className="w-20 h-20 object-contain rounded-lg bg-muted flex-shrink-0"
                    onError={(e) => {
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
                    {result.in_stock === false && (
                      <Badge variant="outline" className="text-muted-foreground">
                        Out of stock
                      </Badge>
                    )}
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
                {selectedResult?.url === result.url && (
                  <div className="flex items-center">
                    <CheckCircle2 className="h-6 w-6 text-violet-500" />
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Empty state while searching */}
      {isSearching && results.length === 0 && (
        <div className="flex flex-col items-center justify-center py-12">
          <div className="relative">
            <Search className="h-12 w-12 text-muted-foreground" />
            <Loader2 className="h-6 w-6 text-violet-500 animate-spin absolute -bottom-1 -right-1" />
          </div>
          <p className="text-muted-foreground mt-4">Searching for prices...</p>
          <p className="text-sm text-muted-foreground mt-1">
            Results will appear as they're found
          </p>
        </div>
      )}

      {/* Error retry */}
      {error && (
        <div className="text-center py-4">
          <Button variant="outline" onClick={startSearch}>
            Try Again
          </Button>
        </div>
      )}
    </div>
  );
}

export default StreamingSearchResults;
