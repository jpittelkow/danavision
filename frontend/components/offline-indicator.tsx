"use client";

import { useOnline } from "@/lib/use-online";
import { WifiOff } from "lucide-react";

/**
 * Fixed banner shown at the top when the user is offline.
 * Informs the user that some features may be unavailable.
 */
export function OfflineIndicator() {
  const { isOffline } = useOnline();

  if (!isOffline) return null;

  return (
    <div
      className="fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-2 bg-amber-500/95 text-amber-950 px-4 py-2 text-sm font-medium shadow-sm safe-area-top"
      role="status"
      aria-live="polite"
    >
      <WifiOff className="h-4 w-4 shrink-0" aria-hidden />
      <span>You&apos;re offline — some features may be unavailable.</span>
    </div>
  );
}
