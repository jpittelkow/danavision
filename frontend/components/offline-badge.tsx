"use client";

import { useOnline } from "@/lib/use-online";
import { cn } from "@/lib/utils";

type OfflineBadgeVariant = "offline" | "cached";

interface OfflineBadgeProps {
  /** "offline" = show when user is offline; "cached" = show "Cached" label. Default: show "Offline" when offline. */
  variant?: OfflineBadgeVariant;
  className?: string;
}

/**
 * Small badge indicating offline state or that data is from cache.
 * Use on pages that display cached data when offline (e.g. dashboard, settings).
 */
export function OfflineBadge({ variant, className }: OfflineBadgeProps) {
  const { isOffline } = useOnline();

  if (variant === "cached") {
    return (
      <span
        className={cn(
          "inline-flex items-center rounded-full border border-amber-500/50 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400",
          className
        )}
        title="This data was loaded from cache"
      >
        Cached
      </span>
    );
  }

  if (!isOffline) return null;

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full border border-amber-500/50 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400",
        className
      )}
      title="You are offline; data may be from cache"
    >
      Offline
    </span>
  );
}
