import { useEffect, useState, useRef } from 'react';
import { Loader2, CheckCircle2, XCircle, Clock, Brain } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Progress } from '@/Components/ui/progress';

/**
 * Step in the identification process
 */
interface ProgressStep {
  id: string;
  label: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed';
  detail?: string;
}

/**
 * Job data from the API
 */
interface JobData {
  id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  progress: number;
  output_data?: {
    status_message?: string;
    progress_logs?: string[];
    results?: unknown[];
  };
  error_message?: string;
  started_at?: string;
  created_at: string;
}

interface Props {
  /** The AI job ID to monitor */
  jobId: number;
  /** Callback when job completes successfully with results */
  onComplete: (results: unknown[], providersUsed: string[]) => void;
  /** Callback when job fails */
  onError: (error: string) => void;
  /** Callback when user cancels */
  onCancel?: () => void;
  /** Optional className */
  className?: string;
}

/**
 * Parse progress logs to extract step information
 */
function parseStepsFromLogs(logs: string[]): ProgressStep[] {
  const steps: ProgressStep[] = [
    { id: 'init', label: 'Initializing AI providers', status: 'pending' },
    { id: 'analyze', label: 'Analyzing input', status: 'pending' },
    { id: 'providers', label: 'Querying AI providers', status: 'pending' },
    { id: 'aggregate', label: 'Aggregating results', status: 'pending' },
    { id: 'images', label: 'Finding product images', status: 'pending' },
  ];

  // Update step statuses based on logs
  logs.forEach(log => {
    const logLower = log.toLowerCase();
    
    if (logLower.includes('initializ') || logLower.includes('starting')) {
      steps[0].status = 'completed';
    }
    if (logLower.includes('analyz')) {
      steps[0].status = 'completed';
      steps[1].status = logLower.includes('complet') ? 'completed' : 'in_progress';
    }
    if (logLower.includes('provider') || logLower.includes('claude') || logLower.includes('openai') || logLower.includes('gemini')) {
      steps[0].status = 'completed';
      steps[1].status = 'completed';
      
      // Extract provider count if available (e.g., "2/3 providers responded")
      const match = log.match(/(\d+)\/(\d+)/);
      if (match) {
        steps[2].detail = `${match[1]}/${match[2]} responded`;
      }
      steps[2].status = logLower.includes('all') || logLower.includes('completed') ? 'completed' : 'in_progress';
    }
    if (logLower.includes('aggregat')) {
      steps[0].status = 'completed';
      steps[1].status = 'completed';
      steps[2].status = 'completed';
      steps[3].status = logLower.includes('complet') ? 'completed' : 'in_progress';
    }
    if (logLower.includes('image') || logLower.includes('enrich')) {
      steps[0].status = 'completed';
      steps[1].status = 'completed';
      steps[2].status = 'completed';
      steps[3].status = 'completed';
      steps[4].status = logLower.includes('complet') || logLower.includes('found') ? 'completed' : 'in_progress';
    }
    if (logLower.includes('error') || logLower.includes('failed')) {
      // Mark current in-progress step as failed
      const inProgressStep = steps.find(s => s.status === 'in_progress');
      if (inProgressStep) {
        inProgressStep.status = 'failed';
      }
    }
  });

  // Mark first pending step as in_progress if no step is currently in_progress
  const hasInProgress = steps.some(s => s.status === 'in_progress');
  if (!hasInProgress && logs.length > 0) {
    const firstPending = steps.find(s => s.status === 'pending');
    if (firstPending) {
      firstPending.status = 'in_progress';
    }
  }

  return steps;
}

/**
 * Format elapsed time in seconds
 */
function formatElapsedTime(startTime: string | null): string {
  if (!startTime) return '0s';
  
  const start = new Date(startTime).getTime();
  const now = Date.now();
  const elapsed = Math.floor((now - start) / 1000);
  
  if (elapsed < 60) return `${elapsed}s`;
  const mins = Math.floor(elapsed / 60);
  const secs = elapsed % 60;
  return `${mins}m ${secs}s`;
}

/**
 * Get icon for step
 */
function getStepIcon(step: ProgressStep) {
  if (step.status === 'completed') {
    return <CheckCircle2 className="h-4 w-4 text-green-500" />;
  }
  if (step.status === 'failed') {
    return <XCircle className="h-4 w-4 text-red-500" />;
  }
  if (step.status === 'in_progress') {
    return <Loader2 className="h-4 w-4 text-violet-500 animate-spin" />;
  }
  // pending
  return <div className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />;
}

/**
 * StatusMonitor Component
 * 
 * Shows detailed progress during AI product identification:
 * - Progress bar with percentage
 * - Current step description
 * - List of steps completed
 * - Elapsed time
 * 
 * Polls for job status updates until complete.
 */
export function StatusMonitor({ jobId, onComplete, onError, onCancel, className }: Props) {
  const [job, setJob] = useState<JobData | null>(null);
  const [elapsedTime, setElapsedTime] = useState('0s');
  const [steps, setSteps] = useState<ProgressStep[]>([
    { id: 'init', label: 'Initializing AI providers', status: 'in_progress' },
    { id: 'analyze', label: 'Analyzing input', status: 'pending' },
    { id: 'providers', label: 'Querying AI providers', status: 'pending' },
    { id: 'aggregate', label: 'Aggregating results', status: 'pending' },
    { id: 'images', label: 'Finding product images', status: 'pending' },
  ]);

  // Use refs to avoid stale closures in the polling interval
  const onCompleteRef = useRef(onComplete);
  const onErrorRef = useRef(onError);
  
  useEffect(() => {
    onCompleteRef.current = onComplete;
    onErrorRef.current = onError;
  }, [onComplete, onError]);

  // Poll for job status
  useEffect(() => {
    let intervalId: ReturnType<typeof setInterval> | null = null;
    let mounted = true;

    const fetchJobStatus = async () => {
      try {
        const response = await fetch(`/api/ai-jobs/${jobId}`);
        if (!mounted) return;

        if (response.ok) {
          const data = await response.json();
          setJob(data);

          // Update steps from logs
          if (data.output_data?.progress_logs) {
            setSteps(parseStepsFromLogs(data.output_data.progress_logs));
          }

          // Handle completed/failed states
          if (data.status === 'completed') {
            // Mark all steps complete
            setSteps(prev => prev.map(s => ({ ...s, status: 'completed' as const })));
            
            // Extract results and providers
            const results = data.output_data?.results || [];
            const logs = data.output_data?.progress_logs || [];
            const providersLog = logs.find((l: string) => l.toLowerCase().includes('provider'));
            const providers: string[] = [];
            if (providersLog) {
              if (providersLog.toLowerCase().includes('claude')) providers.push('claude');
              if (providersLog.toLowerCase().includes('openai')) providers.push('openai');
              if (providersLog.toLowerCase().includes('gemini')) providers.push('gemini');
            }
            
            onCompleteRef.current(results, providers);
            if (intervalId) {
              clearInterval(intervalId);
              intervalId = null;
            }
          } else if (data.status === 'failed') {
            onErrorRef.current(data.error_message || 'Product identification failed');
            if (intervalId) {
              clearInterval(intervalId);
              intervalId = null;
            }
          } else if (data.status === 'cancelled') {
            onErrorRef.current('Product identification was cancelled');
            if (intervalId) {
              clearInterval(intervalId);
              intervalId = null;
            }
          }
        } else {
          onErrorRef.current('Failed to fetch job status');
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        }
      } catch (err) {
        if (mounted) {
          onErrorRef.current('Network error while checking job status');
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        }
      }
    };

    // Initial fetch
    fetchJobStatus();
    
    // Poll every 2 seconds
    intervalId = setInterval(fetchJobStatus, 2000);

    return () => {
      mounted = false;
      if (intervalId) {
        clearInterval(intervalId);
      }
    };
  }, [jobId]);

  // Update elapsed time every second
  useEffect(() => {
    const startTime = job?.started_at || job?.created_at;
    if (!startTime) return;

    const updateElapsed = () => {
      setElapsedTime(formatElapsedTime(startTime));
    };

    updateElapsed();
    const intervalId = setInterval(updateElapsed, 1000);

    return () => clearInterval(intervalId);
  }, [job?.started_at, job?.created_at]);

  const progress = job?.progress ?? 5;
  const statusMessage = job?.output_data?.status_message;
  const currentStep = steps.find(s => s.status === 'in_progress');

  return (
    <div className={cn('space-y-6', className)}>
      {/* Header with icon and title */}
      <div className="flex items-center gap-4">
        <div className="relative">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center">
            <Brain className="h-8 w-8 text-white" />
          </div>
          <div className="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-background flex items-center justify-center">
            <Loader2 className="h-4 w-4 text-violet-500 animate-spin" />
          </div>
        </div>
        <div>
          <h3 className="text-lg font-semibold text-foreground">Identifying Product</h3>
          <p className="text-sm text-muted-foreground">
            {statusMessage || currentStep?.label || 'Processing...'}
          </p>
        </div>
      </div>

      {/* Progress bar */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Progress</span>
          <div className="flex items-center gap-2">
            <Clock className="h-4 w-4 text-muted-foreground" />
            <span className="text-muted-foreground">{elapsedTime}</span>
            <span className="font-medium text-violet-500">{progress}%</span>
          </div>
        </div>
        <Progress value={progress} className="h-2" />
      </div>

      {/* Steps list */}
      <div className="space-y-3">
        <h4 className="text-sm font-medium text-foreground">Progress Steps</h4>
        <div className="space-y-2">
          {steps.map((step) => (
            <div
              key={step.id}
              className={cn(
                'flex items-center gap-3 p-2 rounded-lg transition-colors',
                step.status === 'in_progress' && 'bg-violet-500/10',
                step.status === 'completed' && 'bg-green-500/5',
                step.status === 'failed' && 'bg-red-500/10'
              )}
            >
              {getStepIcon(step)}
              <span
                className={cn(
                  'text-sm',
                  step.status === 'pending' && 'text-muted-foreground',
                  step.status === 'in_progress' && 'text-foreground font-medium',
                  step.status === 'completed' && 'text-foreground',
                  step.status === 'failed' && 'text-red-500'
                )}
              >
                {step.label}
              </span>
              {step.detail && (
                <span className="text-xs text-muted-foreground ml-auto">{step.detail}</span>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Cancel button */}
      {onCancel && (
        <button
          onClick={onCancel}
          className="text-sm text-muted-foreground hover:text-foreground transition-colors"
        >
          Cancel
        </button>
      )}
    </div>
  );
}

export default StatusMonitor;
