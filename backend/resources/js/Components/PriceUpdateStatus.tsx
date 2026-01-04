import { useEffect, useState } from 'react';
import { Loader2, CheckCircle2, XCircle, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ActiveJob {
  id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  progress: number;
  type: string;
  status_message?: string | null;
  error_message?: string | null;
  logs?: string[] | null;
}

interface Props {
  itemId: number;
  lastCheckedAt: string | null;
  className?: string;
  onRetry?: () => void;
}

/**
 * Format relative time from a date string.
 */
function formatRelativeTime(dateString: string | null): string {
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
}

/**
 * PriceUpdateStatus Component
 * 
 * Displays the current price update status for an item:
 * - Spinner when a job is pending or processing
 * - Green checkmark when prices are up to date
 * - Red X with retry option when update failed
 * 
 * Polls for active job status when a job is in progress.
 */
export function PriceUpdateStatus({ itemId, lastCheckedAt, className, onRetry }: Props) {
  const [activeJob, setActiveJob] = useState<ActiveJob | null>(null);
  const [loading, setLoading] = useState(true);

  // Poll for active job status
  useEffect(() => {
    let intervalId: ReturnType<typeof setInterval> | null = null;
    let mounted = true;

    const fetchActiveJob = async () => {
      try {
        const response = await fetch(`/api/items/${itemId}/active-job`);
        if (!mounted) return;

        if (response.ok) {
          const data = await response.json();
          setActiveJob(data.job || null);

          // If job is active, continue polling (only set interval once)
          if (data.job && ['pending', 'processing'].includes(data.job.status)) {
            if (!intervalId) {
              intervalId = setInterval(fetchActiveJob, 3000); // Poll every 3 seconds
            }
          } else {
            // Job completed or no active job - stop polling
            if (intervalId) {
              clearInterval(intervalId);
              intervalId = null;
            }
          }
        } else {
          setActiveJob(null);
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        }
      } catch (err) {
        if (mounted) {
          setActiveJob(null);
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    };

    fetchActiveJob();

    return () => {
      mounted = false;
      if (intervalId) {
        clearInterval(intervalId);
      }
    };
  }, [itemId]);

  // Don't show anything while initially loading
  if (loading) {
    return null;
  }

  // Job is pending or processing - show spinner with status message
  if (activeJob && ['pending', 'processing'].includes(activeJob.status)) {
    // Determine display text
    let statusText = activeJob.status === 'pending' ? 'Queued...' : 'Updating prices...';
    if (activeJob.status_message) {
      // Truncate long status messages
      statusText = activeJob.status_message.length > 40 
        ? activeJob.status_message.substring(0, 40) + '...'
        : activeJob.status_message;
    }

    return (
      <div className={cn('flex items-center gap-1.5 text-amber-500', className)}>
        <Loader2 className="h-4 w-4 animate-spin" />
        <span className="text-xs">
          {statusText}
          {activeJob.progress > 0 && activeJob.progress < 100 && ` (${activeJob.progress}%)`}
        </span>
      </div>
    );
  }

  // Job failed - show error with retry option
  if (activeJob && activeJob.status === 'failed') {
    // Get a user-friendly error message
    let errorText = 'Update failed';
    if (activeJob.error_message) {
      // Extract first part of error message (before any technical details)
      const errorMsg = activeJob.error_message.split('\n')[0];
      errorText = errorMsg.length > 50 ? errorMsg.substring(0, 50) + '...' : errorMsg;
    }

    return (
      <div className={cn('flex flex-col gap-1', className)}>
        <div className="flex items-center gap-1.5 text-red-500">
          <XCircle className="h-4 w-4" />
          <span className="text-xs">{errorText}</span>
          {onRetry && (
            <button
              onClick={onRetry}
              className="ml-1 text-xs text-primary hover:underline flex items-center gap-0.5"
            >
              <RefreshCw className="h-3 w-3" />
              Retry
            </button>
          )}
        </div>
      </div>
    );
  }

  // No active job - show last updated time with checkmark
  return (
    <div className={cn('flex items-center gap-1.5 text-green-500', className)}>
      <CheckCircle2 className="h-4 w-4" />
      <span className="text-xs text-muted-foreground">
        Updated {formatRelativeTime(lastCheckedAt)}
      </span>
    </div>
  );
}

export default PriceUpdateStatus;
