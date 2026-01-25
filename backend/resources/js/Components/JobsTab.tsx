import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { useJobPolling } from '@/hooks/useJobPolling';
import type { AIJob, AIJobStats, CrawlJobOutputData } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { CrawlLogViewer } from '@/Components/CrawlLogViewer';
import {
  Loader2,
  XCircle,
  CheckCircle2,
  Clock,
  Trash2,
  RefreshCw,
  Activity,
  History,
  Ban,
  DollarSign,
  Store,
} from 'lucide-react';

/**
 * JobsTab Component
 * 
 * Displays active and historical AI jobs with real-time status updates.
 * Allows users to view job progress and cancel active jobs.
 */
export function JobsTab() {
  const [jobs, setJobs] = useState<AIJob[]>([]);
  const [stats, setStats] = useState<AIJobStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');

  const { activeJobs, isPolling, refresh: refreshActive } = useJobPolling({
    interval: 3000,
    autoStart: true,
  });

  // Fetch all jobs
  const fetchJobs = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string> = {};
      if (statusFilter !== 'all') {
        params.status = statusFilter;
      }
      if (typeFilter !== 'all') {
        params.type = typeFilter;
      }

      const [jobsRes, statsRes] = await Promise.all([
        axios.get('/api/ai-jobs', { params }),
        axios.get('/api/ai-jobs/stats'),
      ]);

      setJobs(jobsRes.data.jobs);
      setStats(statsRes.data);
    } catch (err) {
      console.error('Failed to fetch jobs:', err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter, typeFilter]);

  useEffect(() => {
    fetchJobs();
  }, [fetchJobs]);

  // Merge active jobs into job list
  const allJobs = React.useMemo(() => {
    const jobMap = new Map(jobs.map(j => [j.id, j]));
    activeJobs.forEach(aj => jobMap.set(aj.id, aj));
    return Array.from(jobMap.values()).sort((a, b) => 
      new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    );
  }, [jobs, activeJobs]);

  const handleCancel = async (jobId: number) => {
    try {
      await axios.post(`/api/ai-jobs/${jobId}/cancel`);
      await fetchJobs();
    } catch (err) {
      console.error('Failed to cancel job:', err);
    }
  };

  const handleDelete = async (jobId: number) => {
    if (!confirm('Are you sure you want to delete this job from history?')) {
      return;
    }
    try {
      await axios.delete(`/api/ai-jobs/${jobId}`);
      await fetchJobs();
    } catch (err) {
      console.error('Failed to delete job:', err);
    }
  };

  const handleClearHistory = async () => {
    if (!confirm('Are you sure you want to clear all completed/failed/cancelled jobs from history?')) {
      return;
    }
    try {
      await axios.delete('/api/ai-jobs/history');
      await fetchJobs();
    } catch (err) {
      console.error('Failed to clear history:', err);
    }
  };

  return (
    <div className="space-y-6">
      {/* Stats Overview */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard label="Active" value={stats.active} color="blue" icon={<Activity className="h-4 w-4" />} />
          <StatCard label="Completed" value={stats.completed} color="green" icon={<CheckCircle2 className="h-4 w-4" />} />
          <StatCard label="Failed" value={stats.failed} color="red" icon={<XCircle className="h-4 w-4" />} />
          <StatCard label="Cancelled" value={stats.cancelled} color="yellow" icon={<Ban className="h-4 w-4" />} />
          <StatCard label="Success Rate" value={`${stats.success_rate}%`} color="purple" icon={<CheckCircle2 className="h-4 w-4" />} />
        </div>
      )}

      {/* Active Jobs Section */}
      {activeJobs.length > 0 && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <Activity className="h-5 w-5 text-blue-500" />
                  Active Jobs
                </CardTitle>
                <CardDescription>Jobs currently running in the background</CardDescription>
              </div>
              <Badge variant="secondary" className="gap-1">
                <Loader2 className="h-3 w-3 animate-spin" />
                {activeJobs.length} active
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {activeJobs.map(job => (
              <JobCard key={job.id} job={job} onCancel={handleCancel} onDelete={handleDelete} />
            ))}
          </CardContent>
        </Card>
      )}

      {/* Job History Section */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <History className="h-5 w-5" />
                Job History
              </CardTitle>
              <CardDescription>Previous AI jobs and their results</CardDescription>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={fetchJobs}>
                <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} />
                Refresh
              </Button>
              <Button variant="outline" size="sm" onClick={handleClearHistory}>
                <Trash2 className="h-4 w-4 mr-1" />
                Clear
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Filters */}
          <div className="flex gap-4">
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-[150px]">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem value="product_identification">Product ID</SelectItem>
                <SelectItem value="price_search">Price Search</SelectItem>
                <SelectItem value="smart_fill">Smart Fill</SelectItem>
                <SelectItem value="price_refresh">Price Refresh</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Job List */}
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : allJobs.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p>No jobs found</p>
            </div>
          ) : (
            <div className="space-y-3">
              {allJobs
                .filter(j => !['pending', 'processing'].includes(j.status))
                .map(job => (
                  <JobCard key={job.id} job={job} onCancel={handleCancel} onDelete={handleDelete} />
                ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

interface StatCardProps {
  label: string;
  value: number | string;
  color: 'blue' | 'green' | 'red' | 'yellow' | 'purple';
  icon: React.ReactNode;
}

function StatCard({ label, value, color, icon }: StatCardProps) {
  const colorClasses = {
    blue: 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300',
    green: 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300',
    red: 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300',
    yellow: 'bg-yellow-50 dark:bg-yellow-950 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300',
    purple: 'bg-purple-50 dark:bg-purple-950 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300',
  };

  return (
    <div className={`rounded-lg border p-4 ${colorClasses[color]}`}>
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-sm font-medium">{label}</span>
      </div>
      <p className="text-2xl font-bold">{value}</p>
    </div>
  );
}

interface JobCardProps {
  job: AIJob;
  onCancel: (id: number) => void;
  onDelete: (id: number) => void;
}

/**
 * Check if a job is a crawl-type job that has detailed logs.
 */
function isCrawlJob(jobType: string): boolean {
  return ['firecrawl_discovery', 'firecrawl_refresh', 'price_search', 'price_refresh'].includes(jobType);
}

/**
 * Get crawl results summary from output data.
 */
function getCrawlSummary(outputData?: CrawlJobOutputData | Record<string, unknown>): {
  resultsCount: number;
  lowestPrice: number | null;
  highestPrice: number | null;
  storesFound: string[];
} | null {
  const data = outputData as CrawlJobOutputData | undefined;
  if (!data || (!data.results_count && !data.results?.length)) {
    return null;
  }

  const storesFound = data.results
    ? [...new Set(data.results.map(r => r.store_name))]
    : [];

  return {
    resultsCount: data.results_count ?? data.results?.length ?? 0,
    lowestPrice: data.lowest_price ?? null,
    highestPrice: data.highest_price ?? null,
    storesFound,
  };
}

function JobCard({ job, onCancel, onDelete }: JobCardProps) {
  const statusBadge = () => {
    switch (job.status) {
      case 'pending':
        return <Badge variant="secondary"><Clock className="h-3 w-3 mr-1" />Pending</Badge>;
      case 'processing':
        return <Badge variant="default" className="bg-blue-500"><Loader2 className="h-3 w-3 mr-1 animate-spin" />Processing</Badge>;
      case 'completed':
        return <Badge variant="success"><CheckCircle2 className="h-3 w-3 mr-1" />Completed</Badge>;
      case 'failed':
        return <Badge variant="destructive"><XCircle className="h-3 w-3 mr-1" />Failed</Badge>;
      case 'cancelled':
        return <Badge variant="secondary"><Ban className="h-3 w-3 mr-1" />Cancelled</Badge>;
      default:
        return <Badge>{job.status}</Badge>;
    }
  };

  const isCrawl = isCrawlJob(job.type);
  const crawlSummary = isCrawl ? getCrawlSummary(job.output_data) : null;

  return (
    <div className="border rounded-lg p-4 bg-card">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className="font-medium text-sm">{job.type_label}</span>
            {statusBadge()}
          </div>
          {job.input_summary && (
            <p className="text-sm text-muted-foreground truncate">{job.input_summary}</p>
          )}
          {job.error_message && (
            <p className="text-sm text-destructive mt-1">{job.error_message}</p>
          )}

          {/* Crawl results summary */}
          {crawlSummary && job.status === 'completed' && (
            <div className="flex flex-wrap items-center gap-3 mt-2 text-xs">
              <span className="flex items-center gap-1 text-green-600 dark:text-green-400">
                <DollarSign className="h-3 w-3" />
                {crawlSummary.resultsCount} price{crawlSummary.resultsCount !== 1 ? 's' : ''} found
              </span>
              {crawlSummary.lowestPrice !== null && (
                <span className="text-muted-foreground">
                  Best: ${crawlSummary.lowestPrice.toFixed(2)}
                </span>
              )}
              {crawlSummary.storesFound.length > 0 && (
                <span className="flex items-center gap-1 text-muted-foreground">
                  <Store className="h-3 w-3" />
                  {crawlSummary.storesFound.slice(0, 3).join(', ')}
                  {crawlSummary.storesFound.length > 3 && ` +${crawlSummary.storesFound.length - 3} more`}
                </span>
              )}
            </div>
          )}

          <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
            <span>{new Date(job.created_at).toLocaleString()}</span>
            {job.formatted_duration && job.formatted_duration !== '-' && (
              <span>Duration: {job.formatted_duration}</span>
            )}
            {job.logs_count > 0 && (
              <span>{job.logs_count} API call{job.logs_count !== 1 ? 's' : ''}</span>
            )}
          </div>
        </div>
        <div className="flex items-center gap-1">
          {job.can_cancel && (
            <Button variant="outline" size="sm" onClick={() => onCancel(job.id)}>
              <XCircle className="h-4 w-4 mr-1" />
              Cancel
            </Button>
          )}
          {!['pending', 'processing'].includes(job.status) && (
            <Button variant="ghost" size="sm" onClick={() => onDelete(job.id)}>
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </div>
      </div>

      {/* Progress bar for active jobs */}
      {job.status === 'processing' && job.progress > 0 && (
        <div className="mt-3 w-full bg-muted rounded-full h-2">
          <div
            className="bg-blue-500 h-2 rounded-full transition-all duration-300"
            style={{ width: `${job.progress}%` }}
          />
        </div>
      )}

      {/* Crawl log viewer for crawl jobs */}
      {isCrawl && job.output_data && (
        <CrawlLogViewer
          outputData={job.output_data}
          defaultExpanded={job.status === 'processing'}
          maxHeight={250}
          autoScroll={job.status === 'processing'}
        />
      )}
    </div>
  );
}

export default JobsTab;
