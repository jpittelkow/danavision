"use client";

import { useState } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { ColumnDef } from "@tanstack/react-table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { DataTable } from "@/components/ui/data-table";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  MoreHorizontal,
  Edit,
  Trash2,
  Users,
  Shield,
  Check,
} from "lucide-react";
import { GroupDialog } from "./group-dialog";
import { MemberManager } from "./member-manager";
import { PermissionMatrix } from "./permission-matrix";

export interface Group {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  is_default: boolean;
  members_count?: number;
  created_at: string;
  updated_at: string;
}

interface GroupTableProps {
  groups: Group[];
  onGroupsUpdated: () => void;
}

export function GroupTable({ groups, onGroupsUpdated }: GroupTableProps) {
  const [editingGroup, setEditingGroup] = useState<Group | null>(null);
  const [groupDialogOpen, setGroupDialogOpen] = useState(false);
  const [memberManagerGroup, setMemberManagerGroup] = useState<Group | null>(null);
  const [permissionMatrixGroup, setPermissionMatrixGroup] = useState<Group | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [groupToDelete, setGroupToDelete] = useState<Group | null>(null);

  const handleEdit = (group: Group) => {
    setEditingGroup(group);
    setGroupDialogOpen(true);
  };

  const handleDelete = (group: Group) => {
    setGroupToDelete(group);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!groupToDelete) return;
    try {
      await api.delete(`/groups/${groupToDelete.id}`);
      toast.success("Group deleted successfully");
      setDeleteDialogOpen(false);
      setGroupToDelete(null);
      onGroupsUpdated();
    } catch (error: unknown) {
      const err = error as Error & { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || err.message || "Failed to delete group");
    }
  };

  const handleSuccess = () => {
    onGroupsUpdated();
    setGroupDialogOpen(false);
    setEditingGroup(null);
  };

  const columns: ColumnDef<Group>[] = [
    {
      accessorKey: "name",
      header: "Name",
      cell: ({ row }) => {
        const group = row.original;
        return (
          <div className="flex items-center gap-2">
            <span className="font-medium">{group.name}</span>
            {group.is_system && (
              <Badge variant="secondary" className="text-xs">
                System
              </Badge>
            )}
          </div>
        );
      },
    },
    {
      accessorKey: "description",
      header: "Description",
      meta: { className: "hidden md:table-cell" },
      cell: ({ row }) => (
        <span className="text-muted-foreground truncate max-w-[200px] block">
          {row.original.description || "—"}
        </span>
      ),
    },
    {
      id: "members",
      header: "Members",
      enableSorting: false,
      cell: ({ row }) => {
        const group = row.original;
        const count = group.members_count ?? 0;
        return (
          <Button
            variant="link"
            className="h-auto p-0 text-primary"
            onClick={() => setMemberManagerGroup(group)}
          >
            {count} member{count !== 1 ? "s" : ""}
          </Button>
        );
      },
    },
    {
      id: "default",
      header: "Default",
      enableSorting: false,
      meta: { className: "hidden md:table-cell" },
      cell: ({ row }) =>
        row.original.is_default ? (
          <Check className="h-4 w-4 text-muted-foreground" />
        ) : (
          "—"
        ),
    },
    {
      id: "actions",
      header: () => <span className="sr-only">Actions</span>,
      enableSorting: false,
      meta: { className: "text-right" },
      cell: ({ row }) => {
        const group = row.original;
        return (
          <div className="flex justify-end">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-11 w-11 min-h-11 min-w-11"
                >
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuItem onClick={() => handleEdit(group)}>
                  <Edit className="mr-2 h-4 w-4" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setMemberManagerGroup(group)}>
                  <Users className="mr-2 h-4 w-4" />
                  Manage Members
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setPermissionMatrixGroup(group)}>
                  <Shield className="mr-2 h-4 w-4" />
                  Manage Permissions
                </DropdownMenuItem>
                {!group.is_system && (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      onClick={() => handleDelete(group)}
                      className="text-destructive"
                    >
                      <Trash2 className="mr-2 h-4 w-4" />
                      Delete
                    </DropdownMenuItem>
                  </>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        );
      },
    },
  ];

  return (
    <>
      <DataTable
        columns={columns}
        data={groups}
        getRowId={(row) => String(row.id)}
      />

      <GroupDialog
        group={editingGroup}
        open={groupDialogOpen}
        onOpenChange={setGroupDialogOpen}
        onSuccess={handleSuccess}
      />

      {memberManagerGroup && (
        <MemberManager
          group={memberManagerGroup}
          open={!!memberManagerGroup}
          onOpenChange={(open) => !open && setMemberManagerGroup(null)}
          onSuccess={onGroupsUpdated}
        />
      )}

      {permissionMatrixGroup && (
        <PermissionMatrix
          group={permissionMatrixGroup}
          open={!!permissionMatrixGroup}
          onOpenChange={(open) => !open && setPermissionMatrixGroup(null)}
          onSuccess={onGroupsUpdated}
        />
      )}

      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Group</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete {groupToDelete?.name}? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={confirmDelete}>
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
