import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { SmartAddQueueItem } from '@/types';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Clock,
  X,
  ChevronRight,
  ImageIcon,
  Search,
  Package,
  Trash2,
  Loader2,
  ListPlus,
  Sparkles,
} from 'lucide-react';

interface Props {
  /** Queue items to display */
  items: SmartAddQueueItem[];
  /** Callback when user wants to review an item */
  onReview: (item: SmartAddQueueItem) => void;
  /** Optional className */
  className?: string;
}

/**
 * Format relative time from a date string.
 */
function formatRelativeTime(dateString: string): string {
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
}

/**
 * Format time until expiration.
 */
function formatExpiresIn(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = date.getTime() - now.getTime();
  const diffDays = Math.ceil(diffMs / 86400000);

  if (diffDays <= 0) return 'Expires soon';
  if (diffDays === 1) return 'Expires tomorrow';
  return `Expires in ${diffDays} days`;
}

/**
 * ReviewQueue Component
 * 
 * Displays a list of pending product identifications that need user review.
 * Users can review (to select and add to list) or dismiss items.
 */
export function ReviewQueue({ items, onReview, className }: Props) {
  const [dismissingIds, setDismissingIds] = useState<Set<number>>(new Set());

  /**
   * Dismiss a queue item.
   */
  const handleDismiss = async (itemId: number) => {
    setDismissingIds(prev => new Set(prev).add(itemId));
    
    try {
      await axios.delete(`/smart-add/queue/${itemId}`);
      // Refresh the page to get updated queue
      router.reload({ only: ['queue', 'queueCount'] });
    } catch (error) {
      console.error('Failed to dismiss queue item:', error);
    } finally {
      setDismissingIds(prev => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  if (items.length === 0) {
    return null;
  }

  return (
    <Card className={cn('mb-6', className)}>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-lg">
            <ListPlus className="h-5 w-5 text-violet-500" />
            Review Queue
            <Badge variant="secondary" className="ml-1">
              {items.length}
            </Badge>
          </CardTitle>
          <p className="text-xs text-muted-foreground">
            Products identified by AI awaiting your review
          </p>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <div className="space-y-3">
          {items.map((item) => (
            <QueueItemCard
              key={item.id}
              item={item}
              onReview={() => onReview(item)}
              onDismiss={() => handleDismiss(item.id)}
              isDismissing={dismissingIds.has(item.id)}
            />
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

interface QueueItemCardProps {
  item: SmartAddQueueItem;
  onReview: () => void;
  onDismiss: () => void;
  isDismissing: boolean;
}

/**
 * Individual queue item card.
 */
function QueueItemCard({ item, onReview, onDismiss, isDismissing }: QueueItemCardProps) {
  const topSuggestion = item.product_data[0];
  const [imageError, setImageError] = useState(false);

  return (
    <div
      className={cn(
        'flex items-center gap-4 p-3 rounded-lg border transition-colors',
        'hover:border-violet-400/50 hover:bg-muted/50',
        'cursor-pointer'
      )}
      onClick={onReview}
    >
      {/* Thumbnail */}
      <div className="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-muted">
        {item.display_image && !imageError ? (
          <img
            src={item.display_image.startsWith('http') 
              ? `/api/proxy-image?url=${encodeURIComponent(item.display_image)}`
              : item.display_image
            }
            alt={item.display_title}
            className="w-full h-full object-cover"
            onError={() => setImageError(true)}
          />
        ) : item.source_type === 'image' ? (
          <div className="w-full h-full flex items-center justify-center bg-violet-500/10">
            <ImageIcon className="h-6 w-6 text-violet-500" />
          </div>
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-violet-500 to-fuchsia-500">
            <span className="text-white font-bold text-lg">
              {(topSuggestion?.brand || item.display_title || '?').substring(0, 2).toUpperCase()}
            </span>
          </div>
        )}
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <p className="font-medium text-foreground line-clamp-1">{item.display_title}</p>
        <div className="flex flex-wrap items-center gap-2 mt-1">
          {item.source_type === 'image' ? (
            <Badge variant="outline" className="text-xs gap-0.5">
              <ImageIcon className="h-2.5 w-2.5" />
              Image
            </Badge>
          ) : (
            <Badge variant="outline" className="text-xs gap-0.5">
              <Search className="h-2.5 w-2.5" />
              Search
            </Badge>
          )}
          <span className="text-xs text-muted-foreground">
            {item.suggestions_count} suggestion{item.suggestions_count !== 1 ? 's' : ''}
          </span>
          {item.providers_used && item.providers_used.length > 0 && (
            <Badge variant="secondary" className="text-xs gap-0.5">
              <Sparkles className="h-2.5 w-2.5" />
              {item.providers_used[0]}
            </Badge>
          )}
        </div>
        <div className="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
          <span className="flex items-center gap-1">
            <Clock className="h-3 w-3" />
            {formatRelativeTime(item.created_at)}
          </span>
          <span>{formatExpiresIn(item.expires_at)}</span>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2 flex-shrink-0" onClick={(e) => e.stopPropagation()}>
        <Button
          variant="ghost"
          size="sm"
          onClick={onDismiss}
          disabled={isDismissing}
          className="text-muted-foreground hover:text-destructive"
          title="Dismiss"
        >
          {isDismissing ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Trash2 className="h-4 w-4" />
          )}
        </Button>
        <Button
          variant="default"
          size="sm"
          onClick={onReview}
          className="gap-1 bg-violet-600 hover:bg-violet-700"
        >
          Review
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}

export default ReviewQueue;
