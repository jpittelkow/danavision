"use client";

import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { errorLogger } from "@/lib/error-logger";

export default function DashboardError({
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
    <div className="flex items-center justify-center p-8">
      <Card className="max-w-md w-full">
        <CardHeader>
          <CardTitle>Something went wrong</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-muted-foreground">
            An error occurred while loading this page. Please try again.
          </p>
          <Button onClick={reset}>Try again</Button>
        </CardContent>
      </Card>
    </div>
  );
}
