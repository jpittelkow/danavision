"use client";

import { useState } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { formatBytes, getErrorMessage } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Loader2, Trash2 } from "lucide-react";

interface CleanupSuggestions {
  suggestions: Record<string, { count: number; size: number; size_formatted?: string; description: string }>;
  total_reclaimable: number;
  total_reclaimable_formatted?: string;
  note?: string;
}

interface StorageCleanupCardProps {
  suggestions: CleanupSuggestions;
  isLoading: boolean;
  onCleanupComplete: () => void;
}

export function StorageCleanupCard({ suggestions, isLoading, onCleanupComplete }: StorageCleanupCardProps) {
  const [cleanupConfirmType, setCleanupConfirmType] = useState<string | null>(null);
  const [cleanupSubmitting, setCleanupSubmitting] = useState(false);

  const onCleanup = async (type: string) => {
    setCleanupSubmitting(true);
    try {
      await api.post("/storage-settings/cleanup", { type });
      toast.success("Cleanup completed successfully");
      setCleanupConfirmType(null);
      onCleanupComplete();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Cleanup failed"));
    } finally {
      setCleanupSubmitting(false);
    }
  };

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Trash2 className="h-5 w-5" />
            Cleanup Tools
          </CardTitle>
          <CardDescription>
            Free up space by removing cache, temp files, and old backups
          </CardDescription>
        </CardHeader>
        <CardContent>
          {suggestions.note ? (
            <p className="text-sm text-muted-foreground py-4">{suggestions.note}</p>
          ) : isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <div className="space-y-4">
              {suggestions.total_reclaimable_formatted && suggestions.total_reclaimable > 0 && (
                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                  <span className="font-medium">Total reclaimable: </span>
                  <span>{suggestions.total_reclaimable_formatted}</span>
                </div>
              )}
              <div className="space-y-3">
                {Object.entries(suggestions.suggestions || {}).map(([key, s]) => (
                  <div
                    key={key}
                    className="flex items-center justify-between rounded-lg border p-4"
                  >
                    <div>
                      <div className="font-medium capitalize">{key.replace(/_/g, " ")}</div>
                      <p className="text-sm text-muted-foreground">{s.description}</p>
                      {(s.count ?? 0) > 0 && (
                        <p className="text-sm mt-1">
                          {s.count} file(s) · {s.size_formatted ?? formatBytes(s.size)}
                        </p>
                      )}
                    </div>
                    <Button
                      variant={key === "old_backups" ? "destructive" : "outline"}
                      size="sm"
                      disabled={(s.count ?? 0) === 0}
                      onClick={() => setCleanupConfirmType(key)}
                    >
                      Clean
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={!!cleanupConfirmType} onOpenChange={(open) => !open && setCleanupConfirmType(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Confirm cleanup</DialogTitle>
            <DialogDescription>
              {cleanupConfirmType === "old_backups" && (
                <>This will permanently delete backups beyond the retention policy. This cannot be undone.</>
              )}
              {cleanupConfirmType === "cache" && (
                <>This will clear the framework cache. The application may regenerate cache files as needed.</>
              )}
              {cleanupConfirmType === "temp" && (
                <>This will delete temporary files older than 7 days. Make sure no processes are using them.</>
              )}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCleanupConfirmType(null)} disabled={cleanupSubmitting}>
              Cancel
            </Button>
            <Button
              variant={cleanupConfirmType === "old_backups" ? "destructive" : "default"}
              onClick={() => cleanupConfirmType && onCleanup(cleanupConfirmType)}
              disabled={cleanupSubmitting}
            >
              {cleanupSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Clean
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
