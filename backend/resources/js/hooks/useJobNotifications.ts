import { useCallback, useState } from 'react';
import type { AIJob } from '@/types';

export interface JobNotification {
  id: string;
  job: AIJob;
  type: 'started' | 'completed' | 'failed' | 'cancelled';
  message: string;
  timestamp: Date;
  dismissed: boolean;
}

export interface UseJobNotificationsReturn {
  /**
   * All active notifications.
   */
  notifications: JobNotification[];

  /**
   * Add a notification for a job starting.
   */
  notifyJobStarted: (job: AIJob) => void;

  /**
   * Add a notification for a job completing.
   */
  notifyJobCompleted: (job: AIJob) => void;

  /**
   * Add a notification for a job failing.
   */
  notifyJobFailed: (job: AIJob) => void;

  /**
   * Add a notification for a job being cancelled.
   */
  notifyJobCancelled: (job: AIJob) => void;

  /**
   * Dismiss a notification by ID.
   */
  dismissNotification: (id: string) => void;

  /**
   * Dismiss all notifications.
   */
  dismissAll: () => void;

  /**
   * Clear dismissed notifications from the list.
   */
  clearDismissed: () => void;
}

/**
 * Hook for managing job notifications.
 * 
 * Creates toast-style notifications for job status changes.
 * Notifications auto-dismiss after 5 seconds by default.
 * 
 * @example
 * const { notifications, notifyJobCompleted, dismissNotification } = useJobNotifications();
 * 
 * // In job polling callback:
 * onComplete: (job) => notifyJobCompleted(job)
 */
export function useJobNotifications(): UseJobNotificationsReturn {
  const [notifications, setNotifications] = useState<JobNotification[]>([]);

  const addNotification = useCallback((
    job: AIJob,
    type: JobNotification['type'],
    message: string
  ) => {
    const notification: JobNotification = {
      id: `${job.id}-${type}-${Date.now()}`,
      job,
      type,
      message,
      timestamp: new Date(),
      dismissed: false,
    };

    setNotifications(prev => [...prev, notification]);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      setNotifications(prev =>
        prev.map(n => n.id === notification.id ? { ...n, dismissed: true } : n)
      );
    }, 5000);

    return notification;
  }, []);

  const notifyJobStarted = useCallback((job: AIJob) => {
    const message = `AI job started: ${job.type_label}`;
    addNotification(job, 'started', message);
  }, [addNotification]);

  const notifyJobCompleted = useCallback((job: AIJob) => {
    const message = `AI job completed: ${job.type_label}`;
    addNotification(job, 'completed', message);
  }, [addNotification]);

  const notifyJobFailed = useCallback((job: AIJob) => {
    const errorSummary = job.error_message
      ? job.error_message.substring(0, 100) + (job.error_message.length > 100 ? '...' : '')
      : 'Unknown error';
    const message = `AI job failed: ${errorSummary}`;
    addNotification(job, 'failed', message);
  }, [addNotification]);

  const notifyJobCancelled = useCallback((job: AIJob) => {
    const message = `AI job cancelled: ${job.type_label}`;
    addNotification(job, 'cancelled', message);
  }, [addNotification]);

  const dismissNotification = useCallback((id: string) => {
    setNotifications(prev =>
      prev.map(n => n.id === id ? { ...n, dismissed: true } : n)
    );
  }, []);

  const dismissAll = useCallback(() => {
    setNotifications(prev =>
      prev.map(n => ({ ...n, dismissed: true }))
    );
  }, []);

  const clearDismissed = useCallback(() => {
    setNotifications(prev => prev.filter(n => !n.dismissed));
  }, []);

  return {
    notifications,
    notifyJobStarted,
    notifyJobCompleted,
    notifyJobFailed,
    notifyJobCancelled,
    dismissNotification,
    dismissAll,
    clearDismissed,
  };
}

export default useJobNotifications;
