"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Loader2 } from "lucide-react";

interface PasskeyRegisterDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
  registerPasskey: (name: string) => Promise<{ success: boolean; error?: string }>;
}

export function PasskeyRegisterDialog({
  open,
  onOpenChange,
  onSuccess,
  registerPasskey,
}: PasskeyRegisterDialogProps) {
  const [name, setName] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const label = (name || "Passkey").trim();
    setLoading(true);
    try {
      const result = await registerPasskey(label);
      if (result.success) {
        toast.success("Passkey added successfully");
        setName("");
        onOpenChange(false);
        onSuccess();
      } else {
        if (result.error && result.error !== "Cancelled") {
          toast.error(result.error);
        }
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Add Passkey</DialogTitle>
          <DialogDescription>
            Give this passkey a name (e.g. &quot;MacBook&quot; or &quot;Phone&quot;). You&apos;ll then be
            prompted to create the passkey with your device.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="passkey-name">Name</Label>
              <Input
                id="passkey-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. MacBook, iPhone"
                disabled={loading}
                maxLength={255}
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={loading}>
              {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Create Passkey
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
