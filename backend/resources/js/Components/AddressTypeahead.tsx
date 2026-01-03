import { useState, useRef, useEffect, useCallback } from 'react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import {
  MapPin,
  Loader2,
  X,
  Search,
} from 'lucide-react';

interface AddressResult {
  display_name: string;
  latitude: number;
  longitude: number;
  street: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  type: string;
}

interface AddressTypeaheadProps {
  value: string;
  latitude?: number | null;
  longitude?: number | null;
  onChange: (address: string, lat: number | null, lon: number | null, postcode: string | null) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
}

export default function AddressTypeahead({
  value,
  latitude,
  longitude,
  onChange,
  placeholder = 'Enter your address...',
  className,
  disabled = false,
}: AddressTypeaheadProps) {
  const [inputValue, setInputValue] = useState(value);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [results, setResults] = useState<AddressResult[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isEditing, setIsEditing] = useState(!value);
  const inputRef = useRef<HTMLInputElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Update input value when prop changes
  useEffect(() => {
    setInputValue(value);
    if (value) {
      setIsEditing(false);
    }
  }, [value]);

  // Debounced search function
  const searchAddress = useCallback(async (query: string) => {
    if (query.length < 3) {
      setResults([]);
      setIsOpen(false);
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/address/search?q=${encodeURIComponent(query)}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (response.status === 429) {
        setError('Please wait a moment before searching again.');
        setResults([]);
        return;
      }

      const data = await response.json();

      if (data.error) {
        setError(data.error);
        setResults([]);
      } else {
        setResults(data.results || []);
        setIsOpen(data.results?.length > 0);
      }
    } catch (err) {
      setError('Failed to search addresses. Please try again.');
      setResults([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Handle input change with debouncing
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setInputValue(newValue);
    setIsEditing(true);

    // Clear existing timer
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    // Set new debounced search (300ms delay)
    debounceTimerRef.current = setTimeout(() => {
      searchAddress(newValue);
    }, 300);
  };

  // Handle selecting an address from results
  const handleSelectAddress = (result: AddressResult) => {
    setInputValue(result.display_name);
    setIsOpen(false);
    setIsEditing(false);
    setResults([]);
    
    // Call onChange with the address, coordinates, and postcode
    onChange(
      result.display_name,
      result.latitude,
      result.longitude,
      result.postcode || null
    );
  };

  // Handle clearing the address
  const handleClear = () => {
    setInputValue('');
    setIsEditing(true);
    setResults([]);
    onChange('', null, null, null);
    inputRef.current?.focus();
  };

  // Handle clicking to edit
  const handleStartEditing = () => {
    setIsEditing(true);
    inputRef.current?.focus();
  };

  // Format address for display
  const formatAddressPreview = (result: AddressResult): string => {
    const parts = [];
    if (result.street) parts.push(result.street);
    if (result.city) parts.push(result.city);
    if (result.state) parts.push(result.state);
    if (result.postcode) parts.push(result.postcode);
    return parts.join(', ') || result.display_name;
  };

  return (
    <div ref={containerRef} className={cn('relative', className)}>
      {/* Display selected address or input */}
      {!isEditing && value ? (
        <div className="flex items-center gap-2 p-3 rounded-md border border-input bg-muted/50">
          <MapPin className="h-4 w-4 text-muted-foreground flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-foreground truncate">
              {value}
            </p>
            {latitude && longitude && (
              <p className="text-xs text-muted-foreground">
                {latitude.toFixed(4)}, {longitude.toFixed(4)}
              </p>
            )}
          </div>
          <div className="flex items-center gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={handleStartEditing}
              disabled={disabled}
              className="h-8 px-2"
            >
              Change
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={handleClear}
              disabled={disabled}
              className="h-8 w-8 text-muted-foreground hover:text-destructive"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        </div>
      ) : (
        <>
          {/* Search input */}
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              ref={inputRef}
              type="text"
              value={inputValue}
              onChange={handleInputChange}
              placeholder={placeholder}
              disabled={disabled}
              className="pl-9 pr-10"
              onFocus={() => {
                if (results.length > 0) setIsOpen(true);
              }}
            />
            {isLoading && (
              <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground" />
            )}
            {!isLoading && inputValue && (
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={handleClear}
                className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
              >
                <X className="h-4 w-4" />
              </Button>
            )}
          </div>

          {/* Error message */}
          {error && (
            <p className="text-xs text-destructive mt-1">{error}</p>
          )}

          {/* Results dropdown */}
          {isOpen && results.length > 0 && (
            <div className="absolute z-50 w-full mt-1 bg-popover border border-border rounded-md shadow-lg max-h-60 overflow-auto">
              {results.map((result, index) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => handleSelectAddress(result)}
                  className={cn(
                    'w-full px-3 py-2 text-left text-sm hover:bg-accent',
                    'flex items-start gap-2 transition-colors',
                    index > 0 && 'border-t border-border/50'
                  )}
                >
                  <MapPin className="h-4 w-4 mt-0.5 text-muted-foreground flex-shrink-0" />
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground truncate">
                      {formatAddressPreview(result)}
                    </p>
                    {result.display_name !== formatAddressPreview(result) && (
                      <p className="text-xs text-muted-foreground truncate">
                        {result.display_name}
                      </p>
                    )}
                  </div>
                </button>
              ))}
            </div>
          )}

          {/* No results message */}
          {isOpen && results.length === 0 && inputValue.length >= 3 && !isLoading && !error && (
            <div className="absolute z-50 w-full mt-1 bg-popover border border-border rounded-md shadow-lg p-3">
              <p className="text-sm text-muted-foreground text-center">
                No addresses found. Try a different search.
              </p>
            </div>
          )}
        </>
      )}
    </div>
  );
}
