import React, { useEffect } from 'react';
import { useJobPolling } from '@/hooks/useJobPolling';
import { useJobNotifications, JobNotification } from '@/hooks/useJobNotifications';
import type { AIJob } from '@/types';
import { CheckCircle2, XCircle, Loader2, X, AlertCircle } from 'lucide-react';

/**
 * JobNotifications Component
 * 
 * Global component that displays toast notifications for AI job status changes.
 * Should be placed in the main layout to show notifications across all pages.
 * 
 * Features:
 * - Polls for active jobs every 3 seconds
 * - Shows toast when jobs start, complete, or fail
 * - Auto-dismisses after 5 seconds
 * - Click to dismiss manually
 */
export function JobNotifications() {
  const {
    notifications,
    notifyJobStarted,
    notifyJobCompleted,
    notifyJobFailed,
    notifyJobCancelled,
    dismissNotification,
    clearDismissed,
  } = useJobNotifications();

  // Track jobs we've already notified about
  const [notifiedStarted, setNotifiedStarted] = React.useState<Set<number>>(new Set());

  const handleJobStatusChange = React.useCallback((job: AIJob, previousStatus: string) => {
    if (job.status === 'completed' && previousStatus === 'processing') {
      notifyJobCompleted(job);
    } else if (job.status === 'failed' && previousStatus === 'processing') {
      notifyJobFailed(job);
    } else if (job.status === 'cancelled') {
      notifyJobCancelled(job);
    }
  }, [notifyJobCompleted, notifyJobFailed, notifyJobCancelled]);

  const { activeJobs, isPolling } = useJobPolling({
    interval: 3000,
    autoStart: true,
    onStatusChange: handleJobStatusChange,
  });

  // Notify when new jobs are detected
  useEffect(() => {
    activeJobs.forEach(job => {
      if (job.status === 'processing' && !notifiedStarted.has(job.id)) {
        notifyJobStarted(job);
        setNotifiedStarted(prev => new Set([...prev, job.id]));
      }
    });
  }, [activeJobs, notifiedStarted, notifyJobStarted]);

  // Clean up dismissed notifications periodically
  useEffect(() => {
    const cleanup = setInterval(() => {
      clearDismissed();
    }, 10000);

    return () => clearInterval(cleanup);
  }, [clearDismissed]);

  // Filter out dismissed notifications for display
  const visibleNotifications = notifications.filter(n => !n.dismissed);

  if (visibleNotifications.length === 0) {
    return null;
  }

  return (
    <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
      {visibleNotifications.map(notification => (
        <NotificationToast
          key={notification.id}
          notification={notification}
          onDismiss={() => dismissNotification(notification.id)}
        />
      ))}
    </div>
  );
}

interface NotificationToastProps {
  notification: JobNotification;
  onDismiss: () => void;
}

function NotificationToast({ notification, onDismiss }: NotificationToastProps) {
  const { type, message, job } = notification;

  const getIcon = () => {
    switch (type) {
      case 'started':
        return <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />;
      case 'completed':
        return <CheckCircle2 className="h-5 w-5 text-green-500" />;
      case 'failed':
        return <XCircle className="h-5 w-5 text-red-500" />;
      case 'cancelled':
        return <AlertCircle className="h-5 w-5 text-yellow-500" />;
      default:
        return null;
    }
  };

  const getBgColor = () => {
    switch (type) {
      case 'started':
        return 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800';
      case 'completed':
        return 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800';
      case 'failed':
        return 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800';
      case 'cancelled':
        return 'bg-yellow-50 dark:bg-yellow-950 border-yellow-200 dark:border-yellow-800';
      default:
        return 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700';
    }
  };

  return (
    <div
      className={`flex items-start gap-3 p-4 rounded-lg border shadow-lg animate-slide-in-right ${getBgColor()}`}
      role="alert"
    >
      <div className="flex-shrink-0">{getIcon()}</div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
          {job.type_label}
        </p>
        <p className="text-sm text-gray-600 dark:text-gray-400 truncate">
          {type === 'started' && 'Processing...'}
          {type === 'completed' && (job.input_summary || 'Completed successfully')}
          {type === 'failed' && (job.error_message?.substring(0, 80) || 'An error occurred')}
          {type === 'cancelled' && 'Cancelled by user'}
        </p>
        {job.progress > 0 && job.progress < 100 && (
          <div className="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
            <div
              className="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
              style={{ width: `${job.progress}%` }}
            />
          </div>
        )}
      </div>
      <button
        onClick={onDismiss}
        className="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        aria-label="Dismiss notification"
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}

export default JobNotifications;
