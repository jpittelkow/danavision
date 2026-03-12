"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Fingerprint } from "lucide-react";
import { performPasskeyLogin, isPasskeySupported } from "@/lib/use-passkeys";
import { useAuth } from "@/lib/auth";

interface PasskeyLoginButtonProps {
  onSuccess?: () => void;
  onError?: (error: string) => void;
  remember?: boolean;
  className?: string;
  variant?: "default" | "outline" | "secondary" | "ghost" | "link" | "destructive";
}

export function PasskeyLoginButton({
  onSuccess,
  onError,
  remember = false,
  className,
  variant = "outline",
}: PasskeyLoginButtonProps) {
  const [loading, setLoading] = useState(false);
  const fetchUser = useAuth((s) => s.fetchUser);

  const handleClick = async () => {
    if (!isPasskeySupported()) {
      toast.error("Passkeys are not supported in this browser");
      onError?.("Not supported");
      return;
    }
    setLoading(true);
    try {
      const result = await performPasskeyLogin(remember);
      if (result.success) {
        await fetchUser();
        onSuccess?.();
      } else {
        if (result.error !== "Cancelled") {
          toast.error(result.error);
          onError?.(result.error);
        }
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Button
      type="button"
      variant={variant}
      className={className}
      onClick={handleClick}
      disabled={loading}
    >
      {loading ? (
        <span className="animate-pulse">Signing in...</span>
      ) : (
        <>
          <Fingerprint className="mr-2 h-4 w-4" />
          Sign in with Passkey
        </>
      )}
    </Button>
  );
}
