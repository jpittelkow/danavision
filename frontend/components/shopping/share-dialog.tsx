"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Trash2, UserPlus } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { getErrorMessage } from "@/lib/utils";
import {
  fetchShares,
  createShare,
  revokeShare,
  type ListShare,
  type CreateShareData,
} from "@/lib/api/shopping";

interface ShareDialogProps {
  listId: number;
  listName: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ShareDialog({
  listId,
  listName,
  open,
  onOpenChange,
}: ShareDialogProps) {
  const queryClient = useQueryClient();
  const [email, setEmail] = useState("");
  const [permission, setPermission] = useState<CreateShareData["permission"]>("view");
  const [message, setMessage] = useState("");

  const { data: sharesResponse, isLoading: sharesLoading } = useQuery({
    queryKey: ["list-shares", listId],
    queryFn: () => fetchShares(listId),
    enabled: open,
  });

  const shares: ListShare[] = sharesResponse?.data?.data ?? [];

  const createMutation = useMutation({
    mutationFn: (data: CreateShareData) => createShare(listId, data),
    onSuccess: () => {
      toast.success("Share invitation sent");
      setEmail("");
      setMessage("");
      queryClient.invalidateQueries({ queryKey: ["list-shares", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to share list"));
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (shareId: number) => revokeShare(shareId),
    onSuccess: () => {
      toast.success("Share revoked");
      queryClient.invalidateQueries({ queryKey: ["list-shares", listId] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to revoke share"));
    },
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim()) return;
    createMutation.mutate({
      email: email.trim(),
      permission,
      message: message.trim() || undefined,
    });
  }

  const statusVariant: Record<string, "default" | "success" | "warning" | "secondary"> = {
    pending: "warning",
    accepted: "success",
    declined: "default",
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Share &quot;{listName}&quot;</DialogTitle>
          <DialogDescription>
            Invite others to view or collaborate on this shopping list.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="share-email">Email address</Label>
            <Input
              id="share-email"
              type="email"
              placeholder="user@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div className="space-y-2">
            <Label>Permission level</Label>
            <Select
              value={permission}
              onValueChange={(v) =>
                setPermission(v as CreateShareData["permission"])
              }
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="view">View only</SelectItem>
                <SelectItem value="edit">Can edit</SelectItem>
                <SelectItem value="admin">Admin</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="share-message">Message (optional)</Label>
            <Textarea
              id="share-message"
              placeholder="Add a personal note..."
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              rows={2}
            />
          </div>

          <DialogFooter>
            <Button
              type="submit"
              disabled={createMutation.isPending || !email.trim()}
              className="gap-2"
            >
              <UserPlus className="h-4 w-4" />
              {createMutation.isPending ? "Sending..." : "Send Invite"}
            </Button>
          </DialogFooter>
        </form>

        {shares.length > 0 && (
          <>
            <Separator />
            <div className="space-y-3">
              <h4 className="text-sm font-medium">Current shares</h4>
              {sharesLoading ? (
                <p className="text-sm text-muted-foreground">Loading...</p>
              ) : (
                <div className="space-y-2">
                  {shares.map((share) => (
                    <div
                      key={share.id}
                      className="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-medium">
                          {share.user?.name ?? share.email}
                        </p>
                        <div className="flex items-center gap-2 mt-0.5">
                          <span className="text-muted-foreground capitalize">
                            {share.permission}
                          </span>
                          <Badge
                            variant={statusVariant[share.status] ?? "default"}
                            className="text-[10px] px-1.5 py-0"
                          >
                            {share.status}
                          </Badge>
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 shrink-0 text-destructive hover:text-destructive"
                        onClick={() => revokeMutation.mutate(share.id)}
                        disabled={revokeMutation.isPending}
                        title="Revoke share"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
