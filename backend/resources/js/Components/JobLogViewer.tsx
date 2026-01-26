import React, { useState, useRef, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  ChevronDown,
  ChevronUp,
  Terminal,
  Copy,
  Check,
  Download,
  Info,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Bug,
} from 'lucide-react';
import type { JobLogEntry, JobLogLevel, JobOutputData } from '@/types';

/**
 * Props for the JobLogViewer component.
 */
interface JobLogViewerProps {
  /** Output data from the job (crawl/discovery, etc.) */
  outputData?: JobOutputData | Record<string, unknown>;
  /** Whether to show expanded by default */
  defaultExpanded?: boolean;
  /** Maximum height of the log viewer */
  maxHeight?: number;
  /** Whether to auto-scroll to bottom on new logs */
  autoScroll?: boolean;
  /** Additional CSS classes */
  className?: string;
}

/**
 * Get icon and color for a log level.
 */
function getLogLevelStyle(level: JobLogLevel): {
  icon: React.ReactNode;
  textColor: string;
  bgColor: string;
} {
  switch (level) {
    case 'success':
      return {
        icon: <CheckCircle2 className="h-3 w-3" />,
        textColor: 'text-green-600 dark:text-green-400',
        bgColor: 'bg-green-500/10',
      };
    case 'warning':
      return {
        icon: <AlertTriangle className="h-3 w-3" />,
        textColor: 'text-yellow-600 dark:text-yellow-400',
        bgColor: 'bg-yellow-500/10',
      };
    case 'error':
      return {
        icon: <XCircle className="h-3 w-3" />,
        textColor: 'text-red-600 dark:text-red-400',
        bgColor: 'bg-red-500/10',
      };
    case 'debug':
      return {
        icon: <Bug className="h-3 w-3" />,
        textColor: 'text-gray-500 dark:text-gray-400',
        bgColor: 'bg-gray-500/10',
      };
    case 'info':
    default:
      return {
        icon: <Info className="h-3 w-3" />,
        textColor: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-500/10',
      };
  }
}

/**
 * Format timestamp for display.
 */
function formatTimestamp(timestamp: string): string {
  try {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    });
  } catch {
    return timestamp;
  }
}

/**
 * Convert legacy string logs to structured log entries.
 */
function convertLegacyLogs(logs: string[]): JobLogEntry[] {
  return logs.map((message, index) => {
    // Try to infer log level from message content
    let level: JobLogLevel = 'info';
    if (message.toLowerCase().includes('error') || message.toLowerCase().includes('failed')) {
      level = 'error';
    } else if (message.toLowerCase().includes('warning') || message.toLowerCase().includes('warn')) {
      level = 'warning';
    } else if (
      message.toLowerCase().includes('success') ||
      message.toLowerCase().includes('completed') ||
      message.toLowerCase().includes('found') ||
      message.toLowerCase().includes('saved')
    ) {
      level = 'success';
    }

    return {
      level,
      message,
      timestamp: new Date(Date.now() - (logs.length - index) * 1000).toISOString(),
    };
  });
}

/**
 * JobLogViewer Component
 *
 * Displays job logs in a console-style format with color coding,
 * timestamps, and expandable/collapsible sections. Supports both
 * structured progress_logs and legacy string[] logs.
 */
export function JobLogViewer({
  outputData,
  defaultExpanded = false,
  maxHeight = 300,
  autoScroll = true,
  className,
}: JobLogViewerProps) {
  const [expanded, setExpanded] = useState(defaultExpanded);
  const [copied, setCopied] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Extract logs from output data
  const jobData = outputData as JobOutputData | undefined;
  const structuredLogs = jobData?.progress_logs;
  const legacyLogs = jobData?.logs;
  const crawlStats = jobData?.crawl_stats;

  // Get logs array (prefer structured, fall back to legacy)
  const logs: JobLogEntry[] = structuredLogs?.length
    ? structuredLogs
    : legacyLogs?.length
      ? convertLegacyLogs(legacyLogs)
      : [];

  // Auto-scroll to bottom when logs update
  useEffect(() => {
    if (autoScroll && scrollRef.current && expanded) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [logs.length, expanded, autoScroll]);

  // No logs to display
  if (logs.length === 0 && !crawlStats) {
    return null;
  }

  // Copy logs to clipboard
  const handleCopy = async () => {
    const text = logs.map((log) => `[${log.level.toUpperCase()}] ${log.message}`).join('\n');
    await navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  // Download logs as text file
  const handleDownload = () => {
    const text = logs
      .map((log) => `[${formatTimestamp(log.timestamp)}] [${log.level.toUpperCase()}] ${log.message}`)
      .join('\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `job-logs-${Date.now()}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  // Count logs by level
  const logCounts = logs.reduce(
    (acc, log) => {
      acc[log.level] = (acc[log.level] || 0) + 1;
      return acc;
    },
    {} as Record<JobLogLevel, number>
  );

  return (
    <div className={cn('mt-3 border rounded-lg bg-muted/30', className)}>
      {/* Header */}
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full flex items-center justify-between px-3 py-2 hover:bg-muted/50 transition-colors"
      >
        <div className="flex items-center gap-2">
          <Terminal className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm font-medium">Job Logs</span>
          <Badge variant="secondary" className="text-xs">
            {logs.length} entries
          </Badge>
          {logCounts.error > 0 && (
            <Badge variant="destructive" className="text-xs">
              {logCounts.error} error{logCounts.error !== 1 ? 's' : ''}
            </Badge>
          )}
          {logCounts.warning > 0 && (
            <Badge variant="outline" className="text-xs text-yellow-600 border-yellow-300">
              {logCounts.warning} warning{logCounts.warning !== 1 ? 's' : ''}
            </Badge>
          )}
        </div>
        {expanded ? (
          <ChevronUp className="h-4 w-4 text-muted-foreground" />
        ) : (
          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        )}
      </button>

      {/* Expanded content */}
      {expanded && (
        <div className="border-t">
          {/* Stats bar */}
          {crawlStats && (
            <div className="px-3 py-2 bg-muted/50 border-b flex flex-wrap gap-4 text-xs">
              <span>
                <strong>URLs:</strong> {crawlStats.urls_successful}/{crawlStats.urls_attempted}{' '}
                successful
              </span>
              {crawlStats.urls_failed > 0 && (
                <span className="text-red-600">
                  <strong>Failed:</strong> {crawlStats.urls_failed}
                </span>
              )}
              <span>
                <strong>Stores:</strong> {crawlStats.stores_found}
              </span>
              {crawlStats.tier1_results > 0 && (
                <span>
                  <strong>Tier 1:</strong> {crawlStats.tier1_results} prices
                </span>
              )}
              {crawlStats.tier2_results > 0 && (
                <span>
                  <strong>Tier 2:</strong> {crawlStats.tier2_results} prices
                </span>
              )}
              {crawlStats.total_duration_ms > 0 && (
                <span>
                  <strong>Duration:</strong> {(crawlStats.total_duration_ms / 1000).toFixed(1)}s
                </span>
              )}
            </div>
          )}

          {/* Toolbar */}
          <div className="flex items-center justify-end gap-1 px-2 py-1 border-b bg-muted/30">
            <Button variant="ghost" size="sm" onClick={handleCopy} className="h-7 px-2">
              {copied ? <Check className="h-3 w-3 mr-1" /> : <Copy className="h-3 w-3 mr-1" />}
              <span className="text-xs">{copied ? 'Copied' : 'Copy'}</span>
            </Button>
            <Button variant="ghost" size="sm" onClick={handleDownload} className="h-7 px-2">
              <Download className="h-3 w-3 mr-1" />
              <span className="text-xs">Download</span>
            </Button>
          </div>

          {/* Log entries */}
          <div
            ref={scrollRef}
            className="overflow-auto font-mono text-xs"
            style={{ maxHeight }}
          >
            {logs.map((log, index) => {
              const style = getLogLevelStyle(log.level);
              return (
                <div
                  key={index}
                  className={cn(
                    'flex items-start gap-2 px-3 py-1.5 border-b border-muted/50 last:border-b-0',
                    style.bgColor
                  )}
                >
                  <span className="text-muted-foreground shrink-0 w-16">
                    {formatTimestamp(log.timestamp)}
                  </span>
                  <span className={cn('shrink-0', style.textColor)}>{style.icon}</span>
                  <span className={cn('flex-1 break-words', style.textColor)}>{log.message}</span>
                  {log.data && (
                    <span className="text-muted-foreground shrink-0">
                      {JSON.stringify(log.data)}
                    </span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

export default JobLogViewer;
