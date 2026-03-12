"use client";

import { useState, useEffect, useCallback } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Download, Loader2 } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { HelpLink } from "@/components/help/help-link";

interface AccessLog {
  id: number;
  user_id: number;
  action: string;
  resource_type: string;
  resource_id: number | null;
  fields_accessed: string[] | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

interface UserOption {
  id: number;
  name: string;
  email: string;
}

interface PaginatedResponse {
  data: AccessLog[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const ACTIONS = ["view", "create", "update", "delete", "export"];
const RESOURCE_TYPES = ["User", "Setting"];

export default function AccessLogsPage() {
  const [logs, setLogs] = useState<AccessLog[]>([]);
  const [users, setUsers] = useState<UserOption[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [usersLoading, setUsersLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [filters, setFilters] = useState({
    user_id: "",
    action: "",
    resource_type: "",
    correlation_id: "",
    date_from: "",
    date_to: "",
  });
  const [isExporting, setIsExporting] = useState(false);

  const fetchUsers = useCallback(async () => {
    setUsersLoading(true);
    try {
      const response = await api.get<{
        data: UserOption[];
        current_page: number;
        last_page: number;
      }>("/users?per_page=200");
      setUsers(response.data.data || []);
    } catch {
      setUsers([]);
    } finally {
      setUsersLoading(false);
    }
  }, []);

  const fetchLogs = useCallback(async () => {
    setIsLoading(true);
    try {
      const params = new URLSearchParams({
        page: currentPage.toString(),
        per_page: "50",
      });
      if (filters.user_id) params.append("user_id", filters.user_id);
      if (filters.action) params.append("action", filters.action);
      if (filters.resource_type) params.append("resource_type", filters.resource_type);
      if (filters.correlation_id) params.append("correlation_id", filters.correlation_id);
      if (filters.date_from) params.append("date_from", filters.date_from);
      if (filters.date_to) params.append("date_to", filters.date_to);

      const response = await api.get<PaginatedResponse>(`/access-logs?${params}`);
      setLogs(response.data.data || []);
      setCurrentPage(response.data.current_page || 1);
      setTotalPages(response.data.last_page || 1);
      setTotal(response.data.total || 0);
    } catch (error: unknown) {
      toast.error(
        error instanceof Error ? error.message : "Failed to load access logs"
      );
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, filters]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleExport = async () => {
    setIsExporting(true);
    try {
      const params = new URLSearchParams();
      if (filters.user_id) params.append("user_id", filters.user_id);
      if (filters.action) params.append("action", filters.action);
      if (filters.resource_type) params.append("resource_type", filters.resource_type);
      if (filters.correlation_id) params.append("correlation_id", filters.correlation_id);
      if (filters.date_from) params.append("date_from", filters.date_from);
      if (filters.date_to) params.append("date_to", filters.date_to);

      const response = await api.get(`/access-logs/export?${params}`, {
        responseType: "blob",
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute(
        "download",
        `access_logs_${new Date().toISOString().split("T")[0]}.csv`
      );
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success("Access logs exported successfully");
    } catch (error: unknown) {
      toast.error(
        error instanceof Error ? error.message : "Failed to export access logs"
      );
    } finally {
      setIsExporting(false);
    }
  };

  const resetFilters = () => {
    setFilters({
      user_id: "",
      action: "",
      resource_type: "",
      correlation_id: "",
      date_from: "",
      date_to: "",
    });
    setCurrentPage(1);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
            Access Logs (HIPAA)
          </h1>
          <p className="text-muted-foreground mt-1">
            Track access to user data and settings for compliance.{" "}
            <HelpLink articleId="access-logs" />
          </p>
        </div>
        <Button onClick={handleExport} disabled={isExporting}>
          {isExporting ? (
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          ) : (
            <Download className="mr-2 h-4 w-4" />
          )}
          Export CSV
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>
            Filter by user, action, resource type, correlation ID, or date range
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div className="space-y-2">
              <Label htmlFor="user">User</Label>
              <Select
                value={filters.user_id || "all"}
                onValueChange={(v) =>
                  setFilters({ ...filters, user_id: v === "all" ? "" : v })
                }
                disabled={usersLoading}
              >
                <SelectTrigger id="user">
                  <SelectValue placeholder="All users" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All users</SelectItem>
                  {users.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name} ({u.email})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="action">Action</Label>
              <Select
                value={filters.action || "all"}
                onValueChange={(v) =>
                  setFilters({ ...filters, action: v === "all" ? "" : v })
                }
              >
                <SelectTrigger id="action">
                  <SelectValue placeholder="All" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  {ACTIONS.map((a) => (
                    <SelectItem key={a} value={a}>
                      {a}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="resource_type">Resource type</Label>
              <Select
                value={filters.resource_type || "all"}
                onValueChange={(v) =>
                  setFilters({
                    ...filters,
                    resource_type: v === "all" ? "" : v,
                  })
                }
              >
                <SelectTrigger id="resource_type">
                  <SelectValue placeholder="All" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  {RESOURCE_TYPES.map((r) => (
                    <SelectItem key={r} value={r}>
                      {r}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="correlation_id">Correlation ID</Label>
              <Input
                id="correlation_id"
                placeholder="Trace ID"
                value={filters.correlation_id}
                onChange={(e) =>
                  setFilters({ ...filters, correlation_id: e.target.value })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="date_from">From date</Label>
              <Input
                id="date_from"
                type="date"
                value={filters.date_from}
                onChange={(e) =>
                  setFilters({ ...filters, date_from: e.target.value })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="date_to">To date</Label>
              <Input
                id="date_to"
                type="date"
                value={filters.date_to}
                onChange={(e) =>
                  setFilters({ ...filters, date_to: e.target.value })
                }
              />
            </div>
          </div>
          <Button variant="outline" className="mt-4" onClick={resetFilters}>
            Clear filters
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Access logs</CardTitle>
          <CardDescription>
            {total} total log{total !== 1 ? "s" : ""}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-16 w-full" />
              ))}
            </div>
          ) : logs.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              No access logs found
            </div>
          ) : (
            <>
              <div className="rounded-md border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead>User</TableHead>
                      <TableHead>Action</TableHead>
                      <TableHead>Resource type</TableHead>
                      <TableHead>Resource ID</TableHead>
                      <TableHead>IP</TableHead>
                      <TableHead>Fields</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {logs.map((log) => (
                      <TableRow key={log.id}>
                        <TableCell className="whitespace-nowrap">
                          {new Date(log.created_at).toLocaleString()}
                        </TableCell>
                        <TableCell>
                          {log.user ? (
                            <div>
                              <div className="font-medium">{log.user.name}</div>
                              <div className="text-sm text-muted-foreground">
                                {log.user.email}
                              </div>
                            </div>
                          ) : (
                            "—"
                          )}
                        </TableCell>
                        <TableCell>{log.action}</TableCell>
                        <TableCell>{log.resource_type}</TableCell>
                        <TableCell>{log.resource_id ?? "—"}</TableCell>
                        <TableCell>{log.ip_address ?? "—"}</TableCell>
                        <TableCell>
                          {log.fields_accessed?.length
                            ? log.fields_accessed.join(", ")
                            : "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {totalPages > 1 && (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mt-4">
                  <div className="text-sm text-muted-foreground">
                    Page {currentPage} of {totalPages}
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        setCurrentPage((p) => Math.max(1, p - 1))
                      }
                      disabled={currentPage === 1}
                    >
                      Previous
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        setCurrentPage((p) =>
                          Math.min(totalPages, p + 1)
                        )
                      }
                      disabled={currentPage === totalPages}
                    >
                      Next
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
