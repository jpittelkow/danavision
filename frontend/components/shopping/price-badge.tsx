"use client";

import { ArrowDown, ArrowUp } from "lucide-react";
import { cn } from "@/lib/utils";

export interface PriceBadgeProps {
  price: number | null;
  previousPrice?: number | null;
  className?: string;
}

function formatPrice(value: number): string {
  return `$${value.toFixed(2)}`;
}

export function PriceBadge({ price, previousPrice, className }: PriceBadgeProps) {
  if (price === null || price === undefined) {
    return <span className={cn("text-muted-foreground", className)}>--</span>;
  }

  const hasPrevious =
    previousPrice !== null && previousPrice !== undefined && previousPrice !== price;
  const isDropped = hasPrevious && previousPrice > price;
  const isRaised = hasPrevious && previousPrice < price;

  return (
    <span className={cn("inline-flex items-center gap-1 font-medium", className)}>
      <span
        className={cn(
          isDropped && "text-green-600 dark:text-green-400",
          isRaised && "text-red-600 dark:text-red-400"
        )}
      >
        {formatPrice(price)}
      </span>
      {isDropped && (
        <ArrowDown className="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
      )}
      {isRaised && (
        <ArrowUp className="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
      )}
    </span>
  );
}
