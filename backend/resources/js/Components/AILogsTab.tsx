import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import type { AIRequestLog, AILogStats } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  Loader2,
  XCircle,
  CheckCircle2,
  Clock,
  Trash2,
  RefreshCw,
  FileText,
  Copy,
  ChevronLeft,
  ChevronRight,
  Brain,
  Sparkles,
  Zap,
  Server,
} from 'lucide-react';

const providerIcons: Record<string, React.ReactNode> = {
  claude: <Brain className="h-4 w-4" />,
  openai: <Sparkles className="h-4 w-4" />,
  gemini: <Zap className="h-4 w-4" />,
  local: <Server className="h-4 w-4" />,
};

/**
 * AILogsTab Component
 * 
 * Displays all AI request logs with filtering and detail view.
 * Shows usage statistics and allows users to view full request/response data.
 */
export function AILogsTab() {
  const [logs, setLogs] = useState<AIRequestLog[]>([]);
  const [stats, setStats] = useState<AILogStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedLog, setSelectedLog] = useState<AIRequestLog | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  
  // Filters
  const [providerFilter, setProviderFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  
  // Pagination
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const fetchLogs = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, per_page: 20 };
      if (providerFilter !== 'all') params.provider = providerFilter;
      if (statusFilter !== 'all') params.status = statusFilter;
      if (typeFilter !== 'all') params.request_type = typeFilter;

      const [logsRes, statsRes] = await Promise.all([
        axios.get('/api/ai-logs', { params }),
        axios.get('/api/ai-logs/stats'),
      ]);

      setLogs(logsRes.data.logs);
      setLastPage(logsRes.data.pagination.last_page);
      setTotal(logsRes.data.pagination.total);
      setStats(statsRes.data);
    } catch (err) {
      console.error('Failed to fetch logs:', err);
    } finally {
      setLoading(false);
    }
  }, [page, providerFilter, statusFilter, typeFilter]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const handleViewDetail = async (logId: number) => {
    try {
      const res = await axios.get(`/api/ai-logs/${logId}`);
      setSelectedLog(res.data.log);
      setDetailOpen(true);
    } catch (err) {
      console.error('Failed to fetch log detail:', err);
    }
  };

  const handleDelete = async (logId: number) => {
    if (!confirm('Are you sure you want to delete this log?')) return;
    try {
      await axios.delete(`/api/ai-logs/${logId}`);
      await fetchLogs();
    } catch (err) {
      console.error('Failed to delete log:', err);
    }
  };

  const handleClearAll = async () => {
    if (!confirm('Are you sure you want to delete ALL logs? This cannot be undone.')) return;
    try {
      await axios.delete('/api/ai-logs/all');
      await fetchLogs();
    } catch (err) {
      console.error('Failed to clear logs:', err);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
  };

  return (
    <div className="space-y-6">
      {/* Stats Overview */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Total Requests" value={stats.total_requests} color="blue" />
          <StatCard label="Success Rate" value={`${stats.success_rate}%`} color="green" />
          <StatCard label="Failed" value={stats.failed_requests} color="red" />
          <StatCard label="Total Tokens" value={formatNumber(stats.total_tokens)} color="purple" />
        </div>
      )}

      {/* Provider Breakdown */}
      {stats && Object.keys(stats.by_provider).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Requests by Provider</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-4">
              {Object.entries(stats.by_provider).map(([provider, count]) => (
                <div key={provider} className="flex items-center gap-2">
                  {providerIcons[provider]}
                  <span className="font-medium capitalize">{provider}:</span>
                  <span>{count}</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Logs Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <FileText className="h-5 w-5" />
                Request Logs
              </CardTitle>
              <CardDescription>All AI API requests with full details</CardDescription>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={fetchLogs}>
                <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} />
                Refresh
              </Button>
              <Button variant="outline" size="sm" onClick={handleClearAll}>
                <Trash2 className="h-4 w-4 mr-1" />
                Clear All
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Filters */}
          <div className="flex gap-4 flex-wrap">
            <Select value={providerFilter} onValueChange={v => { setProviderFilter(v); setPage(1); }}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="Provider" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Providers</SelectItem>
                <SelectItem value="claude">Claude</SelectItem>
                <SelectItem value="openai">OpenAI</SelectItem>
                <SelectItem value="gemini">Gemini</SelectItem>
                <SelectItem value="local">Ollama</SelectItem>
              </SelectContent>
            </Select>
            <Select value={statusFilter} onValueChange={v => { setStatusFilter(v); setPage(1); }}>
              <SelectTrigger className="w-[130px]">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="success">Success</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
              </SelectContent>
            </Select>
            <Select value={typeFilter} onValueChange={v => { setTypeFilter(v); setPage(1); }}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem value="completion">Completion</SelectItem>
                <SelectItem value="image_analysis">Image Analysis</SelectItem>
                <SelectItem value="price_aggregation">Price Aggregation</SelectItem>
                <SelectItem value="test_connection">Connection Test</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Logs List */}
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : logs.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <FileText className="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p>No logs found</p>
            </div>
          ) : (
            <>
              <div className="border rounded-lg overflow-hidden">
                <table className="w-full text-sm">
                  <thead className="bg-muted">
                    <tr>
                      <th className="text-left p-3 font-medium">Timestamp</th>
                      <th className="text-left p-3 font-medium">Provider</th>
                      <th className="text-left p-3 font-medium">Type</th>
                      <th className="text-left p-3 font-medium">Status</th>
                      <th className="text-left p-3 font-medium">Duration</th>
                      <th className="text-left p-3 font-medium">Tokens</th>
                      <th className="text-right p-3 font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.map(log => (
                      <tr key={log.id} className="border-t hover:bg-muted/50">
                        <td className="p-3">
                          {new Date(log.created_at).toLocaleString()}
                        </td>
                        <td className="p-3">
                          <div className="flex items-center gap-2">
                            {providerIcons[log.provider]}
                            <span className="capitalize">{log.provider}</span>
                          </div>
                        </td>
                        <td className="p-3">{log.type_label}</td>
                        <td className="p-3">
                          {log.status === 'success' ? (
                            <Badge variant="success"><CheckCircle2 className="h-3 w-3 mr-1" />Success</Badge>
                          ) : (
                            <Badge variant="destructive"><XCircle className="h-3 w-3 mr-1" />Failed</Badge>
                          )}
                        </td>
                        <td className="p-3">{log.formatted_duration}</td>
                        <td className="p-3">{log.total_tokens || '-'}</td>
                        <td className="p-3 text-right">
                          <Button variant="ghost" size="sm" onClick={() => handleViewDetail(log.id)}>
                            View
                          </Button>
                          <Button variant="ghost" size="sm" onClick={() => handleDelete(log.id)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                  Showing {logs.length} of {total} logs
                </p>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage(p => p - 1)}
                    disabled={page === 1}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <span className="text-sm">
                    Page {page} of {lastPage}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage(p => p + 1)}
                    disabled={page === lastPage}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* Log Detail Modal */}
      <Dialog open={detailOpen} onOpenChange={setDetailOpen}>
        <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Request Details</DialogTitle>
            <DialogDescription>
              Full request and response data for this AI API call
            </DialogDescription>
          </DialogHeader>
          {selectedLog && (
            <div className="space-y-4">
              {/* Basic Info */}
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground">Provider:</span>
                  <span className="ml-2 font-medium capitalize">{selectedLog.provider}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Model:</span>
                  <span className="ml-2 font-medium">{selectedLog.model || 'N/A'}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Type:</span>
                  <span className="ml-2 font-medium">{selectedLog.type_label}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Status:</span>
                  <span className="ml-2">
                    {selectedLog.status === 'success' ? (
                      <Badge variant="success">Success</Badge>
                    ) : (
                      <Badge variant="destructive">Failed</Badge>
                    )}
                  </span>
                </div>
                <div>
                  <span className="text-muted-foreground">Duration:</span>
                  <span className="ml-2 font-medium">{selectedLog.formatted_duration}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Tokens:</span>
                  <span className="ml-2 font-medium">
                    {selectedLog.tokens_input || 0} in / {selectedLog.tokens_output || 0} out
                  </span>
                </div>
              </div>

              {/* Error Message */}
              {selectedLog.error_message && (
                <div className="bg-destructive/10 border border-destructive/20 rounded-lg p-3">
                  <p className="text-sm font-medium text-destructive mb-1">Error</p>
                  <p className="text-sm">{selectedLog.error_message}</p>
                </div>
              )}

              {/* Request Data */}
              {selectedLog.request_data && (
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">Request Data</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => copyToClipboard(JSON.stringify(selectedLog.request_data, null, 2))}
                    >
                      <Copy className="h-4 w-4 mr-1" />
                      Copy
                    </Button>
                  </div>
                  <pre className="bg-muted rounded-lg p-3 overflow-x-auto text-xs max-h-48">
                    {JSON.stringify(selectedLog.request_data, null, 2)}
                  </pre>
                </div>
              )}

              {/* Response Data */}
              {selectedLog.response_data && (
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">Response Data</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => copyToClipboard(JSON.stringify(selectedLog.response_data, null, 2))}
                    >
                      <Copy className="h-4 w-4 mr-1" />
                      Copy
                    </Button>
                  </div>
                  <pre className="bg-muted rounded-lg p-3 overflow-x-auto text-xs max-h-48">
                    {JSON.stringify(selectedLog.response_data, null, 2)}
                  </pre>
                </div>
              )}

              {/* SERP Data */}
              {selectedLog.serp_data && (
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">SERP API Data</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => copyToClipboard(JSON.stringify(selectedLog.serp_data, null, 2))}
                    >
                      <Copy className="h-4 w-4 mr-1" />
                      Copy
                    </Button>
                  </div>
                  {selectedLog.serp_data_summary && (
                    <p className="text-sm text-muted-foreground mb-2">
                      {selectedLog.serp_data_summary.results_count} results from {selectedLog.serp_data_summary.engine}
                    </p>
                  )}
                  <pre className="bg-muted rounded-lg p-3 overflow-x-auto text-xs max-h-48">
                    {JSON.stringify(selectedLog.serp_data, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

interface StatCardProps {
  label: string;
  value: number | string;
  color: 'blue' | 'green' | 'red' | 'purple';
}

function StatCard({ label, value, color }: StatCardProps) {
  const colorClasses = {
    blue: 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300',
    green: 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300',
    red: 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300',
    purple: 'bg-purple-50 dark:bg-purple-950 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300',
  };

  return (
    <div className={`rounded-lg border p-4 ${colorClasses[color]}`}>
      <p className="text-sm font-medium mb-1">{label}</p>
      <p className="text-2xl font-bold">{value}</p>
    </div>
  );
}

function formatNumber(num: number): string {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
  return num.toString();
}

export default AILogsTab;
