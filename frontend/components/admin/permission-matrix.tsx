"use client";

import { useState, useEffect, useMemo } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
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
import { Loader2 } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import type { Group } from "./group-table";

interface PermissionMatrixProps {
  group: Group;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

interface AvailablePermissionsResponse {
  permissions: string[];
  categories: Record<string, string[]>;
}

interface GroupPermissionsResponse {
  permissions: Array<{ id: number; permission: string; resource_type: string | null; resource_id: number | null }>;
}

function formatPermissionLabel(perm: string): string {
  const parts = perm.split(".");
  const action = parts[parts.length - 1];
  return action.charAt(0).toUpperCase() + action.slice(1);
}

export function PermissionMatrix({
  group,
  open,
  onOpenChange,
  onSuccess,
}: PermissionMatrixProps) {
  const [categories, setCategories] = useState<Record<string, string[]>>({});
  const [selectedPermissions, setSelectedPermissions] = useState<Set<string>>(new Set());
  const [initialPermissions, setInitialPermissions] = useState<Set<string>>(new Set());
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const hasChanges = useMemo(() => {
    if (selectedPermissions.size !== initialPermissions.size) return true;
    for (const p of Array.from(selectedPermissions)) {
      if (!initialPermissions.has(p)) return true;
    }
    for (const p of Array.from(initialPermissions)) {
      if (!selectedPermissions.has(p)) return true;
    }
    return false;
  }, [selectedPermissions, initialPermissions]);

  useEffect(() => {
    if (!open || !group?.id) return;

    const load = async () => {
      setIsLoading(true);
      try {
        const [availRes, groupRes] = await Promise.all([
          api.get<AvailablePermissionsResponse>("/permissions"),
          api.get<GroupPermissionsResponse>(`/groups/${group.id}/permissions`),
        ]);
        const cats = availRes.data.categories || {};
        setCategories(cats);
        const current = new Set(
          (groupRes.data.permissions || []).map((p) => p.permission)
        );
        setSelectedPermissions(new Set(current));
        setInitialPermissions(new Set(current));
      } catch (err: unknown) {
        const e = err as Error & { response?: { data?: { message?: string } } };
        toast.error(e.message || e.response?.data?.message || "Failed to load permissions");
      } finally {
        setIsLoading(false);
      }
    };
    load();
  }, [open, group?.id]);

  const togglePermission = (perm: string, checked: boolean) => {
    setSelectedPermissions((prev) => {
      const next = new Set(prev);
      if (checked) next.add(perm);
      else next.delete(perm);
      return next;
    });
  };

  const handleSave = async () => {
    if (!group?.id) return;
    setIsSaving(true);
    try {
      const permissions = Array.from(selectedPermissions).map((permission) => ({
        permission,
        resource_type: null as string | null,
        resource_id: null as number | null,
      }));
      await api.put(`/groups/${group.id}/permissions`, { permissions });
      toast.success("Permissions updated");
      setInitialPermissions(new Set(selectedPermissions));
      onSuccess();
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to update permissions");
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[640px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Manage Permissions — {group.name}</DialogTitle>
          <DialogDescription>
            Configure which permissions this group has. Changes take effect after saving.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4 py-4">
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : (
            Object.entries(categories).map(([category, perms]) => (
              <Card key={category}>
                <CardHeader className="py-3">
                  <CardTitle className="text-sm font-medium">{category}</CardTitle>
                </CardHeader>
                <CardContent className="pt-0">
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    {perms.map((perm) => (
                      <label
                        key={perm}
                        htmlFor={`perm-${perm}`}
                        className={cn(
                          "flex min-h-[44px] cursor-pointer items-center gap-2 rounded-md border px-3 py-2 transition-colors hover:bg-accent/50",
                          selectedPermissions.has(perm) && "border-primary bg-accent/30"
                        )}
                      >
                        <Checkbox
                          id={`perm-${perm}`}
                          checked={selectedPermissions.has(perm)}
                          onCheckedChange={(checked) => togglePermission(perm, !!checked)}
                        />
                        <span className="text-sm">{formatPermissionLabel(perm)}</span>
                      </label>
                    ))}
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={!hasChanges || isSaving}
            onClick={handleSave}
          >
            {isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Save Permissions
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
