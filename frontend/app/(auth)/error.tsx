"use client";

import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { errorLogger } from "@/lib/error-logger";

export default function AuthError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    errorLogger.report(error);
  }, [error]);

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="text-center space-y-4 max-w-sm">
        <h2 className="text-xl font-semibold">Something went wrong</h2>
        <p className="text-sm text-muted-foreground">
          An error occurred. Please try again.
        </p>
        <Button onClick={reset} variant="outline">
          Try again
        </Button>
      </div>
    </div>
  );
}
