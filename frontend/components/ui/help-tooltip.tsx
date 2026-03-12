"use client";

import { HelpCircle } from "lucide-react";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

interface HelpTooltipProps {
  content: string | React.ReactNode;
  side?: "top" | "right" | "bottom" | "left";
  align?: "start" | "center" | "end";
  children?: React.ReactNode;
  className?: string;
  iconClassName?: string;
}

export function HelpTooltip({
  content,
  side = "top",
  align = "center",
  children,
  className,
  iconClassName,
}: HelpTooltipProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        {children ?? (
          <button
            type="button"
            className={cn(
              "inline-flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 rounded-sm",
              className
            )}
            aria-label="Help"
          >
            <HelpCircle className={cn("h-4 w-4", iconClassName)} />
          </button>
        )}
      </TooltipTrigger>
      <TooltipContent
        side={side}
        align={align}
        className="max-w-xs text-sm"
      >
        {content}
      </TooltipContent>
    </Tooltip>
  );
}
