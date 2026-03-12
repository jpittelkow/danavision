"use client";

import { useState, useEffect, useCallback } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { X, Loader2, Plus, Search } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";
import type { Group } from "./group-table";

interface User {
  id: number;
  name: string;
  email: string;
}

interface Member extends User {}

interface PaginatedMembers {
  data: Member[];
  current_page: number;
  last_page: number;
  total: number;
}

interface PaginatedUsers {
  data: User[];
  current_page: number;
  last_page: number;
  total: number;
}

interface MemberManagerProps {
  group: Group;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

export function MemberManager({
  group,
  open,
  onOpenChange,
  onSuccess,
}: MemberManagerProps) {
  const [members, setMembers] = useState<Member[]>([]);
  const [membersPage, setMembersPage] = useState(1);
  const [membersTotalPages, setMembersTotalPages] = useState(1);
  const [isLoadingMembers, setIsLoadingMembers] = useState(false);
  const [usersToAdd, setUsersToAdd] = useState<User[]>([]);
  const [userSearch, setUserSearch] = useState("");
  const [isLoadingUsers, setIsLoadingUsers] = useState(false);
  const [addingUserId, setAddingUserId] = useState<number | null>(null);
  const [removingUserId, setRemovingUserId] = useState<number | null>(null);
  const [selectedUsers, setSelectedUsers] = useState<User[]>([]);
  const [isAddingBulk, setIsAddingBulk] = useState(false);

  const memberIds = new Set(members.map((m) => m.id));

  const toggleUserSelection = (user: User) => {
    setSelectedUsers((prev) =>
      prev.some((u) => u.id === user.id)
        ? prev.filter((u) => u.id !== user.id)
        : [...prev, user]
    );
  };

  const isUserSelected = (user: User) => selectedUsers.some((u) => u.id === user.id);

  const fetchMembers = useCallback(async () => {
    if (!group?.id) return;
    setIsLoadingMembers(true);
    try {
      const res = await api.get<PaginatedMembers>(
        `/groups/${group.id}/members?page=${membersPage}&per_page=20`
      );
      setMembers(res.data.data || []);
      setMembersTotalPages(res.data.last_page || 1);
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to load members");
    } finally {
      setIsLoadingMembers(false);
    }
  }, [group?.id, membersPage]);

  const fetchUsersToAdd = useCallback(async () => {
    setIsLoadingUsers(true);
    try {
      const params = new URLSearchParams({
        per_page: "100",
        page: "1",
      });
      if (userSearch) params.append("search", userSearch);
      const res = await api.get<PaginatedUsers>(`/users?${params}`);
      const users = res.data.data || [];
      setUsersToAdd(users.filter((u) => !memberIds.has(u.id)));
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to load users");
    } finally {
      setIsLoadingUsers(false);
    }
  }, [group?.id, userSearch, memberIds]);

  useEffect(() => {
    if (open) {
      setMembersPage(1);
    } else {
      setSelectedUsers([]);
    }
  }, [open]);

  useEffect(() => {
    if (open && group?.id) {
      fetchMembers();
    }
  }, [open, group?.id, fetchMembers]);

  useEffect(() => {
    if (open && group?.id) {
      fetchUsersToAdd();
    }
  }, [open, group?.id, fetchUsersToAdd]);

  const handleAddMember = async (user: User) => {
    if (!group?.id) return;
    setAddingUserId(user.id);
    try {
      await api.post(`/groups/${group.id}/members`, { user_ids: [user.id] });
      toast.success(`${user.name} added to group`);
      fetchMembers();
      setUsersToAdd((prev) => prev.filter((u) => u.id !== user.id));
      setSelectedUsers((prev) => prev.filter((u) => u.id !== user.id));
      onSuccess();
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to add member");
    } finally {
      setAddingUserId(null);
    }
  };

  const handleAddSelected = async () => {
    if (!group?.id || selectedUsers.length === 0) return;
    setIsAddingBulk(true);
    try {
      await api.post(`/groups/${group.id}/members`, {
        user_ids: selectedUsers.map((u) => u.id),
      });
      toast.success(
        selectedUsers.length === 1
          ? `${selectedUsers[0].name} added to group`
          : `${selectedUsers.length} users added to group`
      );
      fetchMembers();
      setUsersToAdd((prev) =>
        prev.filter((u) => !selectedUsers.some((s) => s.id === u.id))
      );
      setSelectedUsers([]);
      onSuccess();
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to add members");
    } finally {
      setIsAddingBulk(false);
    }
  };

  const handleRemoveMember = async (user: Member) => {
    if (!group?.id) return;
    setRemovingUserId(user.id);
    try {
      await api.delete(`/groups/${group.id}/members/${user.id}`);
      toast.success(`${user.name} removed from group`);
      fetchMembers();
      onSuccess();
    } catch (err: unknown) {
      const e = err as Error & { response?: { data?: { message?: string } } };
      toast.error(e.message || e.response?.data?.message || "Failed to remove member");
    } finally {
      setRemovingUserId(null);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>Manage Members — {group.name}</DialogTitle>
          <DialogDescription>
            Add or remove users from this group.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-6">
          <div className="space-y-2">
            <Label>Add members</Label>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Search users by name or email..."
                  value={userSearch}
                  onChange={(e) => setUserSearch(e.target.value)}
                  className="pl-9"
                />
              </div>
            </div>
            {isLoadingUsers ? (
              <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Loading users…
              </div>
            ) : usersToAdd.length === 0 ? (
              <p className="py-4 text-sm text-muted-foreground">
                No users to add (all matching users are already in this group).
              </p>
            ) : (
              <>
                {selectedUsers.length > 0 && (
                  <Button
                    size="sm"
                    onClick={handleAddSelected}
                    disabled={isAddingBulk}
                    className="min-h-[44px]"
                  >
                    {isAddingBulk ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <>Add Selected ({selectedUsers.length})</>
                    )}
                  </Button>
                )}
                <div className="max-h-40 overflow-y-auto rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-10">Select</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead className="hidden sm:table-cell">Email</TableHead>
                        <TableHead className="w-[80px] text-right">Action</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {usersToAdd.slice(0, 10).map((user) => (
                        <TableRow key={user.id}>
                          <TableCell>
                            <Checkbox
                              checked={isUserSelected(user)}
                              onCheckedChange={() => toggleUserSelection(user)}
                              aria-label={`Select ${user.name}`}
                            />
                          </TableCell>
                          <TableCell className="font-medium">{user.name}</TableCell>
                          <TableCell className="hidden text-muted-foreground sm:table-cell">
                            {user.email}
                          </TableCell>
                          <TableCell className="text-right">
                            <Button
                              variant="ghost"
                              size="sm"
                              className="min-h-[44px] min-w-[44px]"
                              onClick={() => handleAddMember(user)}
                              disabled={addingUserId === user.id}
                            >
                              {addingUserId === user.id ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                <Plus className="h-4 w-4" />
                              )}
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                  {usersToAdd.length > 10 && (
                    <p className="px-3 py-2 text-xs text-muted-foreground">
                      Showing first 10. Refine search to find others.
                    </p>
                  )}
                </div>
              </>
            )}
          </div>

          <div className="space-y-2">
            <Label>Current members ({members.length})</Label>
            {isLoadingMembers ? (
              <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Loading members…
              </div>
            ) : members.length === 0 ? (
              <p className="py-4 text-sm text-muted-foreground">
                No members in this group yet.
              </p>
            ) : (
              <div className="overflow-x-auto rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead className="hidden sm:table-cell">Email</TableHead>
                      <TableHead className="w-[80px] text-right">Remove</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {members.map((member) => (
                      <TableRow key={member.id}>
                        <TableCell className="font-medium">{member.name}</TableCell>
                        <TableCell className="hidden text-muted-foreground sm:table-cell">
                          {member.email}
                        </TableCell>
                        <TableCell className="text-right">
                          <Button
                            variant="ghost"
                            size="sm"
                            className="min-h-[44px] min-w-[44px] text-destructive hover:text-destructive"
                            onClick={() => handleRemoveMember(member)}
                            disabled={removingUserId === member.id}
                          >
                            {removingUserId === member.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <X className="h-4 w-4" />
                            )}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
            {membersTotalPages > 1 && (
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setMembersPage((p) => Math.max(1, p - 1))}
                  disabled={membersPage === 1}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    setMembersPage((p) => Math.min(membersTotalPages, p + 1))
                  }
                  disabled={membersPage === membersTotalPages}
                >
                  Next
                </Button>
                <span className="flex items-center text-sm text-muted-foreground">
                  Page {membersPage} of {membersTotalPages}
                </span>
              </div>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
