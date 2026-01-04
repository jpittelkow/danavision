import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import type { AIJob } from '@/types';

export interface UseJobPollingOptions {
  /**
   * Job ID to poll for (if watching a specific job).
   * If not provided, polls for all active jobs.
   */
  jobId?: number;

  /**
   * Polling interval in milliseconds. Default: 2000 (2 seconds).
   */
  interval?: number;

  /**
   * Whether to automatically start polling. Default: true.
   */
  autoStart?: boolean;

  /**
   * Callback when a job completes.
   */
  onComplete?: (job: AIJob) => void;

  /**
   * Callback when a job fails.
   */
  onFail?: (job: AIJob) => void;

  /**
   * Callback when any job status changes.
   */
  onStatusChange?: (job: AIJob, previousStatus: string) => void;

  /**
   * Callback when polling starts.
   */
  onStart?: () => void;
}

export interface UseJobPollingReturn {
  /**
   * The job being watched (if watching a specific job).
   */
  job: AIJob | null;

  /**
   * All active jobs (if polling for active jobs).
   */
  activeJobs: AIJob[];

  /**
   * Whether polling is currently active.
   */
  isPolling: boolean;

  /**
   * Whether a request is currently in flight.
   */
  isLoading: boolean;

  /**
   * Any error that occurred during polling.
   */
  error: string | null;

  /**
   * Start polling.
   */
  startPolling: () => void;

  /**
   * Stop polling.
   */
  stopPolling: () => void;

  /**
   * Manually refresh job status.
   */
  refresh: () => Promise<void>;
}

/**
 * Hook for polling AI job status.
 * 
 * Can be used in two modes:
 * 1. Single job mode: Pass a jobId to watch a specific job
 * 2. All active jobs mode: Don't pass a jobId to watch all active jobs
 * 
 * Automatically stops polling when job(s) complete.
 * 
 * @example
 * // Watch a specific job
 * const { job, isPolling } = useJobPolling({ jobId: 123, onComplete: (j) => console.log('Done!') });
 * 
 * @example
 * // Watch all active jobs
 * const { activeJobs, startPolling, stopPolling } = useJobPolling({ onComplete: handleJobComplete });
 */
export function useJobPolling(options: UseJobPollingOptions = {}): UseJobPollingReturn {
  const {
    jobId,
    interval = 2000,
    autoStart = true,
    onComplete,
    onFail,
    onStatusChange,
    onStart,
  } = options;

  const [job, setJob] = useState<AIJob | null>(null);
  const [activeJobs, setActiveJobs] = useState<AIJob[]>([]);
  const [isPolling, setIsPolling] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const previousStatusesRef = useRef<Map<number, string>>(new Map());

  const poll = useCallback(async () => {
    if (isLoading) return;

    setIsLoading(true);
    setError(null);

    try {
      if (jobId) {
        // Single job mode
        const response = await axios.get(`/api/ai-jobs/${jobId}`);
        const fetchedJob = response.data.job as AIJob;
        
        const previousStatus = previousStatusesRef.current.get(fetchedJob.id);
        
        if (previousStatus && previousStatus !== fetchedJob.status) {
          onStatusChange?.(fetchedJob, previousStatus);
          
          if (fetchedJob.status === 'completed') {
            onComplete?.(fetchedJob);
          } else if (fetchedJob.status === 'failed') {
            onFail?.(fetchedJob);
          }
        }
        
        previousStatusesRef.current.set(fetchedJob.id, fetchedJob.status);
        setJob(fetchedJob);

        // Stop polling if job is no longer active
        if (!['pending', 'processing'].includes(fetchedJob.status)) {
          stopPolling();
        }
      } else {
        // All active jobs mode
        const response = await axios.get('/api/ai-jobs/active');
        const fetchedJobs = response.data.jobs as AIJob[];

        // Check for status changes
        fetchedJobs.forEach(fetchedJob => {
          const previousStatus = previousStatusesRef.current.get(fetchedJob.id);
          
          if (previousStatus && previousStatus !== fetchedJob.status) {
            onStatusChange?.(fetchedJob, previousStatus);
            
            if (fetchedJob.status === 'completed') {
              onComplete?.(fetchedJob);
            } else if (fetchedJob.status === 'failed') {
              onFail?.(fetchedJob);
            }
          }
          
          previousStatusesRef.current.set(fetchedJob.id, fetchedJob.status);
        });

        setActiveJobs(fetchedJobs);
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to fetch job status';
      setError(message);
      console.error('Job polling error:', err);
    } finally {
      setIsLoading(false);
    }
  }, [jobId, isLoading, onComplete, onFail, onStatusChange]);

  const startPolling = useCallback(() => {
    if (pollingRef.current) return;

    setIsPolling(true);
    onStart?.();

    // Initial poll
    poll();

    // Set up interval
    pollingRef.current = setInterval(poll, interval);
  }, [poll, interval, onStart]);

  const stopPolling = useCallback(() => {
    if (pollingRef.current) {
      clearInterval(pollingRef.current);
      pollingRef.current = null;
    }
    setIsPolling(false);
  }, []);

  const refresh = useCallback(async () => {
    await poll();
  }, [poll]);

  // Auto-start polling
  useEffect(() => {
    if (autoStart) {
      startPolling();
    }

    return () => {
      stopPolling();
    };
  }, [autoStart]); // eslint-disable-line react-hooks/exhaustive-deps

  return {
    job,
    activeJobs,
    isPolling,
    isLoading,
    error,
    startPolling,
    stopPolling,
    refresh,
  };
}

export default useJobPolling;
