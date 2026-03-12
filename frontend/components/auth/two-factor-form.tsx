"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useAuth } from "@/lib/auth";
import { getErrorMessage } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface TwoFactorFormProps {
  onSuccess: () => void;
  onCancel: () => void;
}

export function TwoFactorForm({ onSuccess, onCancel }: TwoFactorFormProps) {
  const { verify2FA } = useAuth();
  const [code, setCode] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      await verify2FA(code, false, useRecoveryCode);
      toast.success("Verified successfully!");
      onSuccess();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Invalid code"));
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="code">
          {useRecoveryCode ? "Recovery Code" : "Verification Code"}
        </Label>
        <Input
          id="code"
          type="text"
          placeholder={useRecoveryCode ? "XXXX-XXXX" : "000000"}
          value={code}
          onChange={(e) => setCode(e.target.value)}
          disabled={isLoading}
          autoFocus
          autoComplete="one-time-code"
        />
      </div>

      <Button type="submit" className="w-full" disabled={isLoading || !code}>
        {isLoading ? "Verifying..." : "Verify"}
      </Button>

      <div className="flex justify-between text-sm">
        <button
          type="button"
          className="text-primary hover:underline"
          onClick={() => {
            setUseRecoveryCode(!useRecoveryCode);
            setCode("");
          }}
        >
          {useRecoveryCode
            ? "Use authenticator code"
            : "Use recovery code"}
        </button>
        <button
          type="button"
          className="text-muted-foreground hover:underline"
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </form>
  );
}
